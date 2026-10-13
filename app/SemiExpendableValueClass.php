<?php

namespace App;

enum SemiExpendableValueClass: string
{
    case Low = 'low';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low-valued',
            self::High => 'High-valued',
        };
    }
}
