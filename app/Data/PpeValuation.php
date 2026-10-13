<?php

namespace App\Data;

final readonly class PpeValuation
{
    public function __construct(
        public string $asOf,
        public string $grossCarryingAmount,
        public string $depreciableAmount,
        public string $residualValue,
        public string $monthlyDepreciation,
        public string $accumulatedDepreciation,
        public string $impairmentLosses,
        public string $carryingAmount,
        public int $depreciationMonths,
        public bool $isDepreciable,
        public bool $isFullyDepreciated,
        public bool $isRecognized,
    ) {}

    /** @return array<string, string|int|bool> */
    public function toArray(): array
    {
        return [
            'as_of' => $this->asOf,
            'gross_carrying_amount' => $this->grossCarryingAmount,
            'depreciable_amount' => $this->depreciableAmount,
            'residual_value' => $this->residualValue,
            'monthly_depreciation' => $this->monthlyDepreciation,
            'accumulated_depreciation' => $this->accumulatedDepreciation,
            'impairment_losses' => $this->impairmentLosses,
            'carrying_amount' => $this->carryingAmount,
            'depreciation_months' => $this->depreciationMonths,
            'is_depreciable' => $this->isDepreciable,
            'is_fully_depreciated' => $this->isFullyDepreciated,
            'is_recognized' => $this->isRecognized,
        ];
    }
}
