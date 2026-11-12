<?php

namespace App\Listeners;

use App\Events\RecordsChanged;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrackRecordChanges
{
    /** @param list<Model> $models */
    public function handle(string $event, array $models): void
    {
        if (! config('realtime.enabled')) {
            return;
        }

        $model = $models[0] ?? null;
        if (! $model instanceof Model || ! str_starts_with($model::class, 'App\\Models\\')) {
            return;
        }

        $name = class_basename($model);
        if (Str::startsWith($name, ['Inventory', 'SupplyRequest', 'Procurement', 'PropertyAccountability', 'IssuanceSlip', 'DepartmentLiquidation', 'Warehouse', 'HrisReference'])) {
            RecordsChanged::dispatch('inventory');
        }

        if (in_array($name, ['User', 'AuditLog', 'HrisReference', 'HrisIntegrationSetting'], true)) {
            RecordsChanged::dispatch('administration');
        }

        if ($model instanceof User) {
            RecordsChanged::dispatch('account', (int) $model->getKey());
        }
    }
}
