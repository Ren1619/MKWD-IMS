<?php

namespace App;

enum AssetAccountingClassification: string
{
    case Ppe = 'ppe';
    case SemiExpendable = 'semi_expendable';
    case NeedsReview = 'needs_review';

    public const CAPITALIZATION_THRESHOLD = 50000;

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
        if ($cost === null || (float) $cost <= 0) {
            return self::NeedsReview;
        }

        return (float) $cost >= self::CAPITALIZATION_THRESHOLD
            ? self::Ppe
            : self::SemiExpendable;
    }
}
