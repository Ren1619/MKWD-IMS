<?php

namespace App\Services;

use App\AssetAccountingClassification;
use App\SemiExpendableValueClass;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

class AssetClassificationService
{
    public function classifyDurableItem(string|int|float $unitCost, string|int|float $unitAttributableCosts = 0): AssetAccountingClassification
    {
        return AssetAccountingClassification::fromAcquisitionCost(
            (string) BigDecimal::of((string) $unitCost)->plus((string) $unitAttributableCosts),
        );
    }

    public function classifySemiExpendableValue(string|int|float $unitCost): SemiExpendableValueClass
    {
        $cost = BigDecimal::of((string) $unitCost);

        if ($cost->isLessThanOrEqualTo(0) || $cost->isGreaterThanOrEqualTo(AssetAccountingClassification::capitalizationThreshold())) {
            throw new InvalidArgumentException('The unit cost is outside the semi-expendable property range.');
        }

        return $cost->isLessThanOrEqualTo((string) config('inventory.semi_expendable.low_value_threshold', '5000.00'))
            ? SemiExpendableValueClass::Low
            : SemiExpendableValueClass::High;
    }
}
