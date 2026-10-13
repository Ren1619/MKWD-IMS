<?php

namespace App;

enum InventoryAssetEventType: string
{
    case Registered = 'registered';
    case Accepted = 'accepted';
    case CapitalAddition = 'capital_addition';
    case Transfer = 'transfer';
    case Maintenance = 'maintenance';
    case Impairment = 'impairment';
    case ImpairmentReversal = 'impairment_reversal';
    case PhysicalCount = 'physical_count';
    case Loss = 'loss';
    case Unserviceable = 'unserviceable';
    case Disposal = 'disposal';
    case Derecognition = 'derecognition';
    case Assignment = 'assignment';
    case Return = 'return';
    case Borrowed = 'borrowed';
    case BorrowingReturn = 'borrowing_return';
    case StateChanged = 'state_changed';
    case AccountingPolicyChanged = 'accounting_policy_changed';
    case Receipt = 'receipt';
    case Issuance = 'issuance';
    case IcsCreated = 'ics_created';
    case IcsRenewed = 'ics_renewed';
    case Reissue = 'reissue';
    case AccountabilityExpired = 'accountability_expired';
    case AccountabilityExtinguished = 'accountability_extinguished';
    case ReliefRequested = 'relief_requested';
    case ReliefApproved = 'relief_approved';
    case ReliefDenied = 'relief_denied';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Accepted => 'Accepted',
            self::CapitalAddition => 'Capital addition',
            self::Transfer => 'Location transfer',
            self::Maintenance => 'Maintenance',
            self::Impairment => 'Impairment loss',
            self::ImpairmentReversal => 'Impairment reversal',
            self::PhysicalCount => 'Physical count',
            self::Loss => 'Loss incident',
            self::Unserviceable => 'Declared unserviceable',
            self::Disposal => 'Disposal',
            self::Derecognition => 'Derecognition',
            self::Assignment => 'Custodian assignment',
            self::Return => 'Returned to Property Unit',
            self::Borrowed => 'Borrowed',
            self::BorrowingReturn => 'Borrowing returned',
            self::StateChanged => 'Operational state changed',
            self::AccountingPolicyChanged => 'Accounting estimate changed',
            self::Receipt => 'Received by Property Unit',
            self::Issuance => 'Issued and expensed',
            self::IcsCreated => 'ICS created',
            self::IcsRenewed => 'ICS renewed',
            self::Reissue => 'Reissued',
            self::AccountabilityExpired => 'Low-value accountability expired',
            self::AccountabilityExtinguished => 'Accountability extinguished',
            self::ReliefRequested => 'Relief requested',
            self::ReliefApproved => 'Relief approved',
            self::ReliefDenied => 'Relief denied',
        };
    }

    /** @return list<self> */
    public static function userRecordable(): array
    {
        return [
            self::CapitalAddition,
            self::Transfer,
            self::Maintenance,
            self::Impairment,
            self::ImpairmentReversal,
            self::PhysicalCount,
            self::Loss,
            self::Unserviceable,
        ];
    }

    /** @return list<self> */
    public static function semiExpendableRecordable(): array
    {
        return [
            self::Transfer,
            self::Maintenance,
            self::PhysicalCount,
            self::Loss,
            self::ReliefRequested,
            self::ReliefApproved,
            self::ReliefDenied,
            self::Unserviceable,
        ];
    }
}
