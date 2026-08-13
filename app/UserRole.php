<?php

namespace App;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case InventoryManager = 'inventory_manager';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::InventoryManager => 'Inventory Manager',
            self::Employee => 'Employee',
        };
    }

    public function canManageInventory(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::InventoryManager => true,
            self::Employee => false,
        };
    }
}
