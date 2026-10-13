<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $active_count_session_id
 */
#[Fillable(['id', 'active_count_session_id'])]
class InventoryStockControl extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['active_count_session_id' => 'integer'];
    }
}
