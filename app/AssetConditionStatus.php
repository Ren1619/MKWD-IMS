<?php

namespace App;

enum AssetConditionStatus: string
{
    case Good = 'good';
    case Fair = 'fair';
    case NeedsRepair = 'needs_repair';
    case Defective = 'defective';
    case NonUsable = 'non_usable';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::NeedsRepair => 'Needs repair',
            self::Defective => 'Defective',
            self::NonUsable => 'Non-usable',
            self::Unknown => 'Unknown',
        };
    }

    public function allowsBorrowing(): bool
    {
        return in_array($this, [self::Good, self::Fair], true);
    }
}
