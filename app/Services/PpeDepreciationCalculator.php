<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\Data\PpeValuation;
use App\InventoryAssetEventType;
use App\Models\InventoryAsset;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class PpeDepreciationCalculator
{
    public function calculate(
        InventoryAsset $asset,
        DateTimeInterface|string|null $asOf = null,
    ): PpeValuation {
        $valuationDate = $asOf instanceof DateTimeInterface
            ? CarbonImmutable::instance($asOf)->startOfDay()
            : CarbonImmutable::parse($asOf ?? today())->startOfDay();

        $asset->loadMissing('events');
        $events = $asset->events
            ->filter(fn ($event): bool => $event->event_date->lte($valuationDate));
        $accountingPolicy = [
            'available_for_use_date' => $asset->available_for_use_date ?? $asset->acquisition_date,
            'residual_value_percentage' => $asset->residual_value_percentage
                ?? config('inventory.ppe.default_residual_value_percentage', '5.00'),
            'depreciation_useful_life_months' => (int) $asset->depreciation_useful_life_months,
        ];
        $asset->events
            ->where('event_type', InventoryAssetEventType::AccountingPolicyChanged)
            ->filter(fn ($event): bool => $event->event_date->gt($valuationDate))
            ->sortByDesc('event_date')
            ->each(function ($event) use (&$accountingPolicy): void {
                foreach (array_keys($accountingPolicy) as $field) {
                    $accountingPolicy[$field] = data_get(
                        $event->details,
                        "before.{$field}",
                        $accountingPolicy[$field],
                    );
                }
            });
        $derecognition = $events
            ->first(fn ($event): bool => $event->event_type === InventoryAssetEventType::Derecognition);
        $depreciationCutoff = $derecognition
            ? $derecognition->event_date->subDay()
            : $valuationDate;

        $initialCost = $this->decimal($asset->acquisition_cost)
            ->plus($this->decimal($asset->directly_attributable_costs));
        $grossAmount = $initialCost;
        $residualAmount = BigDecimal::zero();
        $accumulatedDepreciation = BigDecimal::zero();
        $monthlyDepreciation = BigDecimal::zero();
        $depreciationMonths = 0;
        $isDepreciable = $asset->accounting_classification === AssetAccountingClassification::Ppe
            && (bool) $asset->is_depreciable
            && (int) $accountingPolicy['depreciation_useful_life_months'] > 0;

        $residualPercentage = $this->decimal(
            $accountingPolicy['residual_value_percentage'],
        );
        $life = (int) $accountingPolicy['depreciation_useful_life_months'];

        if ($isDepreciable) {
            $initialResidual = $this->percentage($initialCost, $residualPercentage);
            $initialDepreciable = $initialCost->minus($initialResidual);
            $availableForUse = CarbonImmutable::parse($accountingPolicy['available_for_use_date']);
            $firstMonth = $this->firstDepreciationMonth($availableForUse);
            $initialMonths = min($life, $this->completedMonths($firstMonth, $depreciationCutoff));
            $initialMonthly = $initialDepreciable->dividedBy($life, 12, RoundingMode::HalfUp);

            $residualAmount = $residualAmount->plus($initialResidual);
            $accumulatedDepreciation = $accumulatedDepreciation
                ->plus($initialMonthly->multipliedBy($initialMonths));
            $depreciationMonths = $initialMonths;

            if ($initialMonths < $life && $initialMonths > 0) {
                $monthlyDepreciation = $monthlyDepreciation->plus($initialMonthly);
            }
        }

        foreach ($events->where('event_type', InventoryAssetEventType::CapitalAddition) as $addition) {
            $additionAmount = $this->decimal($addition->amount);
            $grossAmount = $grossAmount->plus($additionAmount);

            if (! $isDepreciable) {
                continue;
            }

            $additionResidual = $this->percentage($additionAmount, $residualPercentage);
            $additionDepreciable = $additionAmount->minus($additionResidual);
            $additionStart = $this->firstDepreciationMonth($addition->event_date);
            $elapsedBeforeAddition = $this->completedMonths(
                $this->firstDepreciationMonth(CarbonImmutable::parse($accountingPolicy['available_for_use_date'])),
                $addition->event_date->subDay(),
            );
            $remainingLife = (int) data_get(
                $addition->details,
                'remaining_useful_life_months',
                max(1, $life - $elapsedBeforeAddition),
            );
            $additionMonths = min(
                $remainingLife,
                $this->completedMonths($additionStart, $depreciationCutoff),
            );
            $additionMonthly = $additionDepreciable->dividedBy($remainingLife, 12, RoundingMode::HalfUp);

            $residualAmount = $residualAmount->plus($additionResidual);
            $accumulatedDepreciation = $accumulatedDepreciation
                ->plus($additionMonthly->multipliedBy($additionMonths));

            if ($additionMonths < $remainingLife && $additionMonths > 0) {
                $monthlyDepreciation = $monthlyDepreciation->plus($additionMonthly);
            }
        }

        $depreciableAmount = $isDepreciable
            ? $grossAmount->minus($residualAmount)
            : BigDecimal::zero();

        if ($accumulatedDepreciation->isGreaterThan($depreciableAmount)) {
            $accumulatedDepreciation = $depreciableAmount;
        }

        $allImpairmentEvents = $asset->events
            ->filter(fn ($event): bool => in_array($event->event_type, [
                InventoryAssetEventType::Impairment,
                InventoryAssetEventType::ImpairmentReversal,
            ], true));
        $impairmentLosses = $allImpairmentEvents->isEmpty()
            ? $this->decimal($asset->impairment_losses)
            : $events
                ->where('event_type', InventoryAssetEventType::Impairment)
                ->reduce(
                    fn (BigDecimal $total, $event): BigDecimal => $total->plus($this->decimal($event->amount)),
                    BigDecimal::zero(),
                )
                ->minus($events
                    ->where('event_type', InventoryAssetEventType::ImpairmentReversal)
                    ->reduce(
                        fn (BigDecimal $total, $event): BigDecimal => $total->plus($this->decimal($event->amount)),
                        BigDecimal::zero(),
                    ));
        $isRecognized = $derecognition === null;
        $carryingAmount = $grossAmount
            ->minus($accumulatedDepreciation)
            ->minus($impairmentLosses);

        if (! $isRecognized || $carryingAmount->isLessThan(0)) {
            $carryingAmount = BigDecimal::zero();
        }

        return new PpeValuation(
            asOf: $valuationDate->toDateString(),
            grossCarryingAmount: $this->money($grossAmount),
            depreciableAmount: $this->money($depreciableAmount),
            residualValue: $this->money($residualAmount),
            monthlyDepreciation: $this->money($monthlyDepreciation),
            accumulatedDepreciation: $this->money($accumulatedDepreciation),
            impairmentLosses: $this->money($impairmentLosses),
            carryingAmount: $this->money($carryingAmount),
            depreciationMonths: $depreciationMonths,
            isDepreciable: $isDepreciable,
            isFullyDepreciated: $isDepreciable
                && $accumulatedDepreciation->isGreaterThanOrEqualTo($depreciableAmount),
            isRecognized: $isRecognized,
        );
    }

    private function firstDepreciationMonth(CarbonImmutable $date): CarbonImmutable
    {
        $cutoffDay = (int) config('inventory.ppe.first_half_cutoff_day', 15);

        return $date->day <= $cutoffDay
            ? $date->endOfMonth()
            : $date->addMonth()->endOfMonth();
    }

    private function completedMonths(CarbonImmutable $firstMonth, CarbonImmutable $asOf): int
    {
        $lastCompletedMonth = $asOf->isLastOfMonth()
            ? $asOf->endOfMonth()
            : $asOf->subMonth()->endOfMonth();

        if ($lastCompletedMonth->lt($firstMonth)) {
            return 0;
        }

        return (($lastCompletedMonth->year - $firstMonth->year) * 12)
            + $lastCompletedMonth->month
            - $firstMonth->month
            + 1;
    }

    private function percentage(BigDecimal $amount, BigDecimal $percentage): BigDecimal
    {
        return $amount
            ->multipliedBy($percentage)
            ->dividedBy(100, 12, RoundingMode::HalfUp);
    }

    private function decimal(string|float|int|null $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? '0'));
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }
}
