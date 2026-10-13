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
 * @property array<string, mixed>|null $metadata
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

    public const TYPE_ROLE = 'role';

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

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     code: string|null,
     *     name: string,
     *     email: string|null,
     *     first_name: string|null,
     *     middle_name: string|null,
     *     last_name: string|null,
     *     name_extension: string|null,
     *     office_name: string|null
     * }
     */
    public function toSelectionOption(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'first_name' => $this->metadata['first_name'] ?? null,
            'middle_name' => $this->metadata['middle_name'] ?? null,
            'last_name' => $this->metadata['last_name'] ?? null,
            'name_extension' => $this->metadata['name_extension'] ?? null,
            'office_name' => $this->officeName(),
        ];
    }

    public function officeName(): ?string
    {
        return data_get($this->metadata, 'position.section.name')
            ?? data_get($this->metadata, 'position.division.name')
            ?? data_get($this->metadata, 'position.department.name');
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
