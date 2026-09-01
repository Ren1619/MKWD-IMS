<?php

namespace App\Models;

use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use Database\Factories\InventoryAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $inventory_asset_id
 * @property AssetLifecycleStatus $lifecycle_status
 * @property AssetConditionStatus $condition_status
 * @property int|null $current_custodian_reference_id
 * @property string|float|null $acquisition_cost
 * @property int $depreciation_useful_life_months
 * @property Carbon|null $acquisition_date
 * @property float $depreciation_amount
 * @property string $custody_status
 * @property bool $is_assignable
 * @property bool $is_borrowable
 */
#[Fillable([
    'category_id',
    'current_custodian_reference_id',
    'serial_number',
    'property_number',
    'name',
    'type',
    'unit_of_measure',
    'fund_cluster',
    'quantity_per_property_card',
    'quantity_per_physical_count',
    'brand',
    'model',
    'description',
    'location',
    'nature_of_occupancy',
    'acquisition_date',
    'acquisition_cost',
    'depreciation_useful_life_months',
    'appraised_value',
    'appraisal_date',
    'impairment_losses',
    'physical_count_remarks',
    'disposal_method',
    'disposal_value',
    'loss_report_no',
    'loss_report_date',
    'loss_type',
    'loss_circumstances',
    'lifecycle_status',
    'condition_status',
])]
class InventoryAsset extends Model
{
    /** @use HasFactory<InventoryAssetFactory> */
    use HasFactory, SoftDeletes;

    private const DEFAULT_RESIDUAL_RATE = 0.05;

    protected $primaryKey = 'inventory_asset_id';

    protected $attributes = [
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
        'unit_of_measure' => 'unit',
        'quantity_per_property_card' => 1,
        'quantity_per_physical_count' => 1,
        'depreciation_useful_life_months' => 60,
        'impairment_losses' => 0,
    ];

    protected $appends = ['depreciation_amount', 'book_value', 'custody_status', 'is_assignable', 'is_borrowable'];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'depreciation_useful_life_months' => 'integer',
            'quantity_per_property_card' => 'integer',
            'quantity_per_physical_count' => 'integer',
            'appraised_value' => 'decimal:2',
            'appraisal_date' => 'date',
            'impairment_losses' => 'decimal:2',
            'disposal_value' => 'decimal:2',
            'loss_report_date' => 'date',
            'lifecycle_status' => AssetLifecycleStatus::class,
            'condition_status' => AssetConditionStatus::class,
        ];
    }

    public function getCustodyStatusAttribute(): string
    {
        return $this->determineCustodyStatus()->value;
    }

    public function getIsAssignableAttribute(): bool
    {
        return $this->lifecycle_status->allowsAssignment();
    }

    public function getIsBorrowableAttribute(): bool
    {
        return $this->lifecycle_status === AssetLifecycleStatus::Active
            && $this->condition_status->allowsBorrowing()
            && $this->determineCustodyStatus() !== AssetCustodyStatus::Borrowed;
    }

    public function determineCustodyStatus(): AssetCustodyStatus
    {
        $hasActiveBorrowing = $this->relationLoaded('activeBorrowing')
            ? $this->getRelation('activeBorrowing') !== null
            : $this->activeBorrowing()->exists();

        if ($hasActiveBorrowing) {
            return AssetCustodyStatus::Borrowed;
        }

        return $this->current_custodian_reference_id
            ? AssetCustodyStatus::Assigned
            : AssetCustodyStatus::Available;
    }

    public function getDepreciationAmountAttribute(): float
    {
        $cost = (float) ($this->acquisition_cost ?? 0);
        $life = (int) $this->depreciation_useful_life_months;

        if ($cost <= 0 || $life <= 0 || ! $this->acquisition_date) {
            return 0.0;
        }

        $months = min($life, (int) $this->acquisition_date->diffInMonths(now()));
        $residualValue = $cost * self::DEFAULT_RESIDUAL_RATE;

        return round((($cost - $residualValue) / $life) * $months, 2);
    }

    public function getBookValueAttribute(): float
    {
        $cost = (float) ($this->acquisition_cost ?? 0);

        return round(max($cost * self::DEFAULT_RESIDUAL_RATE, $cost - $this->depreciation_amount), 2);
    }

    /** @return BelongsTo<InventoryAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetCategory::class, 'category_id', 'inv_asset_cat_id');
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function currentCustodian(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'current_custodian_reference_id');
    }

    /** @return HasMany<InventoryAssetCustodian, $this> */
    public function custodianAssignments(): HasMany
    {
        return $this->hasMany(InventoryAssetCustodian::class, 'inventory_asset_id', 'inventory_asset_id')
            ->latest('assigned_at');
    }

    /** @return HasMany<PropertyAccountabilityDocument, $this> */
    public function accountabilityDocuments(): HasMany
    {
        return $this->hasMany(PropertyAccountabilityDocument::class, 'inventory_asset_id', 'inventory_asset_id')
            ->latest('issued_at');
    }

    /** @return HasMany<InventoryAssetBorrowing, $this> */
    public function borrowings(): HasMany
    {
        return $this->hasMany(InventoryAssetBorrowing::class, 'inventory_asset_id', 'inventory_asset_id')
            ->latest('borrowed_at');
    }

    /** @return HasOne<InventoryAssetBorrowing, $this> */
    public function activeBorrowing(): HasOne
    {
        return $this->hasOne(InventoryAssetBorrowing::class, 'inventory_asset_id', 'inventory_asset_id')
            ->where('status', 'borrowed')
            ->latestOfMany('inventory_asset_borrowing_id');
    }
}
