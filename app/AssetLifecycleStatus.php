<?php

namespace App;

enum AssetLifecycleStatus: string
{
    case Active = 'active';
    case Idle = 'idle';
    case UnderMaintenance = 'under_maintenance';
    case Retired = 'retired';
    case ForDisposal = 'for_disposal';
    case Disposed = 'disposed';
    case Lost = 'lost';
    case TransferredOut = 'transferred_out';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Idle => 'Idle',
            self::UnderMaintenance => 'Under maintenance',
            self::Retired => 'Retired',
            self::ForDisposal => 'For disposal',
            self::Disposed => 'Disposed',
            self::Lost => 'Lost',
            self::TransferredOut => 'Transferred to another agency',
        };
    }

    public function allowsAssignment(): bool
    {
        return ! in_array($this, [self::ForDisposal, self::Disposed, self::Lost, self::TransferredOut], true);
    }
}
