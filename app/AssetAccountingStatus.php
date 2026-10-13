<?php

namespace App;

enum AssetAccountingStatus: string
{
    case Recognized = 'recognized';
    case Derecognized = 'derecognized';
    case OnHand = 'on_hand';
    case IssuedAndExpensed = 'issued_and_expensed';
    case Disposed = 'disposed';
    case Adjusted = 'adjusted';

    public function label(): string
    {
        return match ($this) {
            self::Recognized => 'Recognized',
            self::Derecognized => 'Derecognized',
            self::OnHand => 'On hand',
            self::IssuedAndExpensed => 'Issued and expensed',
            self::Disposed => 'Disposed',
            self::Adjusted => 'Adjusted',
        };
    }
}
