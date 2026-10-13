<?php

namespace App;

enum AssetCustodyStatus: string
{
    case Available = 'available';
    case Assigned = 'assigned';
    case Borrowed = 'borrowed';
    case TransferredOut = 'transferred_out';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Assigned => 'Assigned',
            self::Borrowed => 'Borrowed',
            self::TransferredOut => 'Transferred to another agency',
        };
    }
}
