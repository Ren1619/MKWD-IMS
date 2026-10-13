<?php

namespace App\Models;

use Database\Factories\InventoryCountSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $document_no
 * @property string|null $title
 * @property Carbon|null $scheduled_date
 * @property string|null $memorandum_reference
 * @property string|null $team_details
 * @property string|null $status
 * @property int $created_by_user_id
 * @property int|null $verified_by_user_id
 * @property int|null $approved_by_user_id
 * @property Carbon|null $cutoff_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['document_no', 'title', 'scheduled_date', 'memorandum_reference', 'team_details', 'status', 'created_by_user_id', 'verified_by_user_id', 'approved_by_user_id', 'cutoff_at', 'completed_at'])]
class InventoryCountSession extends Model
{
    /** @use HasFactory<InventoryCountSessionFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'cutoff_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return HasMany<InventoryCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    /** @return HasMany<InventoryCountAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(InventoryCountAction::class);
    }
}
