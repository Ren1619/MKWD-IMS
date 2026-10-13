<?php

namespace App;

enum SemiExpendablePhysicalStatus: string
{
    case InPropertyCustody = 'in_property_custody';
    case Issued = 'issued';
    case Returned = 'returned';
    case Transferred = 'transferred';
    case ForDisposal = 'for_disposal';
    case Disposed = 'disposed';
    case Missing = 'missing';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
