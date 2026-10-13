<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string,mixed>|null $metadata */
#[Fillable(['inventory_monthly_report_id', 'inventory_monthly_report_snapshot_id', 'actor_user_id', 'actor_name', 'action', 'from_status', 'to_status', 'remarks', 'metadata'])]
class InventoryMonthlyReportAction extends Model
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Monthly report sign-off evidence cannot be rewritten.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Monthly report sign-off evidence cannot be deleted.');
        });
    }
}
