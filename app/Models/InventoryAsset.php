<?php

namespace App\Models;

use App\AssetAccountingClassification;
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
use Illuminate\Support\Str;

/**
 * @property int $inventory_asset_id
 * @property AssetLifecycleStatus $lifecycle_status
 * @property AssetConditionStatus $condition_status
 * @property int|null $current_custodian_reference_id
 * @property string|float|null $acquisition_cost
 * @property AssetAccountingClassification $accounting_classification
 * @property string|float|null $residual_value_percentage
 * @property int $depreciation_useful_life_months
 * @property Carbon|null $acquisition_date
 * @property Carbon|null $available_for_use_date
 * @property float $depreciation_amount
 * @property float $residual_value
 * @property bool $is_depreciable
 * @property string $custody_status
 * @property bool $is_assignable
 * @property bool $is_borrowable
 */
#[Fillable([
    'category_id',
    'subcategory_id',
    'current_custodian_reference_id',
    'serial_number',
    'property_number',
    'property_tag_uuid',
    'name',
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
    'available_for_use_date',
    'acquisition_cost',
    'accounting_classification',
    'residual_value_percentage',
    'residual_value_basis',
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

    protected $appends = [
        'depreciation_amount',
        'residual_value',
        'book_value',
        'is_depreciable',
        'custody_status',
        'is_assignable',
        'is_borrowable',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryAsset $asset): void {
            $asset->property_tag_uuid ??= (string) Str::uuid();
        });

        static::saving(function (InventoryAsset $asset): void {
            $classification = AssetAccountingClassification::fromAcquisitionCost($asset->acquisition_cost);

            $asset->accounting_classification = $classification;

            if ($classification === AssetAccountingClassification::Ppe) {
                $asset->residual_value_percentage ??= 5;
                $asset->residual_value_basis ??= 'COA default residual value of 5%.';
                $asset->available_for_use_date ??= $asset->acquisition_date;
            } else {
                $asset->residual_value_percentage = null;
                $asset->residual_value_basis = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'available_for_use_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'accounting_classification' => AssetAccountingClassification::class,
            'residual_value_percentage' => 'decimal:2',
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

    public function getIsDepreciableAttribute(): bool
    {
        return $this->accounting_classification === AssetAccountingClassification::Ppe;
    }

    public function getResidualValueAttribute(): float
    {
        if (! $this->is_depreciable) {
            return 0.0;
        }

        $cost = (float) ($this->acquisition_cost ?? 0);
        $percentage = (float) ($this->residual_value_percentage ?? 5);

        return round($cost * ($percentage / 100), 2);
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
        if (! $this->is_depreciable) {
            return 0.0;
        }

        $cost = (float) ($this->acquisition_cost ?? 0);
        $life = (int) $this->depreciation_useful_life_months;
        $availableForUseDate = $this->available_for_use_date ?? $this->acquisition_date;

        if ($cost <= 0 || $life <= 0 || ! $availableForUseDate) {
            return 0.0;
        }

        $firstDepreciationMonth = $availableForUseDate->day <= 15
            ? $availableForUseDate->copy()->endOfMonth()
            : $availableForUseDate->copy()->addMonth()->endOfMonth();
        $today = now();
        $lastCompletedMonth = $today->isLastOfMonth()
            ? $today->copy()->endOfMonth()
            : $today->copy()->subMonth()->endOfMonth();

        if ($lastCompletedMonth->lt($firstDepreciationMonth)) {
            return 0.0;
        }

        $months = (($lastCompletedMonth->year - $firstDepreciationMonth->year) * 12)
            + $lastCompletedMonth->month
            - $firstDepreciationMonth->month
            + 1;
        $months = min($life, $months);

        return round((($cost - $this->residual_value) / $life) * $months, 2);
    }

    public function getBookValueAttribute(): float
    {
        $cost = (float) ($this->acquisition_cost ?? 0);

        if (! $this->is_depreciable) {
            return round($cost, 2);
        }

        return round(max($this->residual_value, $cost - $this->depreciation_amount), 2);
    }

    /** @return BelongsTo<InventoryAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetCategory::class, 'category_id', 'inv_asset_cat_id');
    }

    /** @return BelongsTo<InventoryAssetSubcategory, $this> */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetSubcategory::class, 'subcategory_id', 'inventory_asset_subcategory_id');
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
