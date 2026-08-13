<?php

namespace App\Models;

use Database\Factories\HrisReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $external_id
 * @property string $type
 * @property string|null $code
 * @property string $name
 * @property string|null $email
 * @property bool $is_active
 */
#[Fillable([
    'external_id',
    'type',
    'code',
    'name',
    'email',
    'parent_external_id',
    'metadata',
    'is_active',
    'source_updated_at',
    'last_synced_at',
])]
class HrisReference extends Model
{
    /** @use HasFactory<HrisReferenceFactory> */
    use HasFactory;

    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_DEPARTMENT = 'department';

    public const TYPE_DIVISION = 'division';

    public const TYPE_UNIT = 'unit';

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<InventoryItem, $this> */
    public function accountableItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'accountable_reference_id');
    }

    /** @return HasMany<InventoryAsset, $this> */
    public function custodialAssets(): HasMany
    {
        return $this->hasMany(InventoryAsset::class, 'current_custodian_reference_id');
    }
}
