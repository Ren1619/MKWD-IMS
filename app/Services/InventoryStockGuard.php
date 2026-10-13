<?php

namespace App\Services;

use App\Models\InventoryStockControl;
use Illuminate\Validation\ValidationException;

class InventoryStockGuard
{
    public function lock(): InventoryStockControl
    {
        InventoryStockControl::query()->insertOrIgnore(['id' => 1]);

        return InventoryStockControl::query()->whereKey(1)->lockForUpdate()->firstOrFail();
    }

    public function assertMovementAllowed(?int $countSessionId = null): void
    {
        $control = $this->lock();
        if ($control->active_count_session_id !== null && $control->active_count_session_id !== $countSessionId) {
            throw ValidationException::withMessages(['quantity' => 'Stock is frozen for physical counting. Record authorized emergency issues or segregated receipts through the active count session.']);
        }
        if ($countSessionId !== null && $control->active_count_session_id !== $countSessionId) {
            throw ValidationException::withMessages(['action' => 'This count session no longer controls the stock cut-off.']);
        }
    }
}
