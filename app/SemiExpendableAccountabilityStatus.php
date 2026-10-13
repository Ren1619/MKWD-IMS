<?php

namespace App;

enum SemiExpendableAccountabilityStatus: string
{
    case Unassigned = 'unassigned';
    case PendingRecipient = 'pending_recipient';
    case Accountable = 'accountable';
    case Returned = 'returned';
    case AccountabilityExtinguished = 'accountability_extinguished';
    case PendingRelief = 'pending_relief';
    case Relieved = 'relieved';
    case TransferredOut = 'transferred_out';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
