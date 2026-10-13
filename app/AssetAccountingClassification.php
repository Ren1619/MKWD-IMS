<?php

namespace App;

use Brick\Math\BigDecimal;

enum AssetAccountingClassification: string
{
    case Ppe = 'ppe';
    case SemiExpendable = 'semi_expendable';
    case NeedsReview = 'needs_review';

    public static function capitalizationThreshold(): string
    {
        return (string) config('inventory.ppe.capitalization_threshold', '50000.00');
    }

    public function label(): string
    {
        return match ($this) {
            self::Ppe => 'Property, plant and equipment',
            self::SemiExpendable => 'Semi-expendable property',
            self::NeedsReview => 'Needs accounting review',
        };
    }

    public function accountabilityDocumentType(): ?string
    {
        return match ($this) {
            self::Ppe => 'PAR',
            self::SemiExpendable => 'ICS',
            self::NeedsReview => null,
        };
    }

    public static function fromAcquisitionCost(string|float|int|null $cost): self
    {
        if ($cost === null || BigDecimal::of((string) $cost)->isLessThanOrEqualTo(0)) {
            return self::NeedsReview;
        }

        return BigDecimal::of((string) $cost)->isGreaterThanOrEqualTo(self::capitalizationThreshold())
            ? self::Ppe
            : self::SemiExpendable;
    }
}
