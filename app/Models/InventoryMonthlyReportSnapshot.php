<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $inventory_monthly_report_id
 * @property int $version
 * @property string $source_hash
 * @property array{entity_name:string,from:string,to:string,reports:array<string,array{title:string,columns:list<string>,rows:list<list<string|int|null>>}>,reconciliation:list<array<string,mixed>>} $payload
 * @property array{blockers:list<array<string,mixed>>,totals:array<string,int|string>} $readiness
 */
#[Fillable(['inventory_monthly_report_id', 'version', 'source_hash', 'payload', 'readiness', 'generated_by_user_id', 'generated_by_name'])]
class InventoryMonthlyReportSnapshot extends Model
{
    protected function casts(): array
    {
        return ['version' => 'integer', 'payload' => 'array', 'readiness' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Monthly report snapshots are immutable. Prepare a new snapshot or correction revision.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Monthly report snapshots cannot be deleted.');
        });
    }
}
