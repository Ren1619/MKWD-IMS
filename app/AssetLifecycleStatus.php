<?php

namespace App;

enum AssetLifecycleStatus: string
{
    case Active = 'active';
    case UnderMaintenance = 'under_maintenance';
    case Retired = 'retired';
    case Disposed = 'disposed';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::UnderMaintenance => 'Under maintenance',
            self::Retired => 'Retired',
            self::Disposed => 'Disposed',
            self::Lost => 'Lost',
        };
    }

    public function allowsAssignment(): bool
    {
        return ! in_array($this, [self::Disposed, self::Lost], true);
    }
}
