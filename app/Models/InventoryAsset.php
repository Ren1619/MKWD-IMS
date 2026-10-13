<?php

namespace App\Models;

use App\AssetAccountingClassification;
use App\AssetAccountingStatus;
use App\AssetConditionStatus;
use App\AssetCustodyStatus;
use App\AssetLifecycleStatus;
use App\Data\PpeValuation;
use App\SemiExpendableAccountabilityStatus;
use App\SemiExpendablePhysicalStatus;
use App\SemiExpendableReliefStatus;
use App\SemiExpendableValueClass;
use App\Services\AssetClassificationService;
use App\Services\PpeDepreciationCalculator;
use Brick\Math\BigDecimal;
use Database\Factories\InventoryAssetFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property AssetAccountingStatus $accounting_status
 * @property SemiExpendableValueClass|null $semi_expendable_value_class
 * @property SemiExpendablePhysicalStatus|null $physical_status
 * @property SemiExpendableAccountabilityStatus|null $accountability_status
 * @property SemiExpendableReliefStatus|null $relief_status
 * @property Carbon|null $accountability_expires_at
 * @property Carbon|null $acceptance_date
 * @property Carbon|null $derecognized_at
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
    'procurement_request_line_id',
    'category_id',
    'subcategory_id',
    'current_custodian_reference_id',
    'serial_number',
    'property_number',
    'old_property_number',
    'account_code',
    'property_tag_uuid',
    'name',
    'unit_of_measure',
    'fund_cluster',
    'quantity_per_property_card',
    'quantity_per_physical_count',
    'brand',
    'manufacturer',
    'model',
    'description',
    'specifications',
    'location',
    'nature_of_occupancy',
    'acquisition_date',
    'acquisition_method',
    'supplier_name',
    'purchase_order_no',
    'inspection_acceptance_report_no',
    'invoice_no',
    'disbursement_voucher_no',
    'delivery_date',
    'inspection_date',
    'acceptance_date',
    'available_for_use_date',
    'acquisition_cost',
    'directly_attributable_costs',
    'accounting_classification',
    'accounting_policy_version',
    'capitalization_threshold_applied',
    'depreciation_method',
    'depreciation_start_rule',
    'is_depreciable',
    'accounting_status',
    'derecognized_at',
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
    'semi_expendable_value_class',
    'physical_status',
    'accountability_status',
    'accountability_expires_at',
    'useful_life_effective_date',
    'useful_life_override_reason',
    'useful_life_authorized_by_user_id',
    'relief_status',
    'incident_reported_at',
    'available_for_reissue',
])]
class InventoryAsset extends Model
{
    /** @use HasFactory<InventoryAssetFactory> */
    use HasFactory, SoftDeletes;

    private ?PpeValuation $currentValuationCache = null;

    protected $primaryKey = 'inventory_asset_id';

    protected $attributes = [
        'lifecycle_status' => 'active',
        'condition_status' => 'good',
        'unit_of_measure' => 'unit',
        'quantity_per_property_card' => 1,
        'quantity_per_physical_count' => 1,
        'impairment_losses' => 0,
        'directly_attributable_costs' => 0,
        'accounting_status' => 'recognized',
    ];

    protected $appends = [
        'depreciation_amount',
        'residual_value',
        'book_value',
        'gross_carrying_amount',
        'monthly_depreciation',
        'is_fully_depreciated',
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
            $recognizedCost = BigDecimal::of((string) ($asset->acquisition_cost ?? 0))
                ->plus((string) ($asset->directly_attributable_costs ?? 0));
            $classification = AssetAccountingClassification::fromAcquisitionCost((string) $recognizedCost);

            $asset->accounting_classification = $classification;
            $asset->accounting_policy_version ??= $classification === AssetAccountingClassification::Ppe
                ? config('inventory.ppe.policy_version')
                : config('inventory.semi_expendable.policy_version');
            $asset->capitalization_threshold_applied ??= config('inventory.ppe.capitalization_threshold');

            if ($classification === AssetAccountingClassification::Ppe) {
                $category = $asset->category_id
                    ? InventoryAssetCategory::query()->find($asset->category_id)
                    : null;
                $asset->residual_value_percentage ??= $category->default_residual_value_percentage
                    ?? config('inventory.ppe.default_residual_value_percentage', '5.00');
                $asset->account_code ??= $category?->account_code;
                $asset->residual_value_basis ??= 'COA default residual value of 5%.';
                $asset->available_for_use_date ??= $asset->acquisition_date;
                $asset->depreciation_useful_life_months ??= $category->default_useful_life_months
                    ?? config('inventory.ppe.default_useful_life_months', 60);
                $asset->is_depreciable ??= $category->is_depreciable ?? true;
                $asset->depreciation_method ??= config('inventory.ppe.depreciation_method');
                $asset->depreciation_start_rule ??= config('inventory.ppe.depreciation_start_rule');
            } else {
                $asset->residual_value_percentage = null;
                $asset->residual_value_basis = null;
                $asset->is_depreciable = false;
                $asset->depreciation_method = null;
                $asset->depreciation_start_rule = null;
                $asset->semi_expendable_value_class = app(AssetClassificationService::class)
                    ->classifySemiExpendableValue((string) $recognizedCost);
                $asset->accounting_status = $asset->accounting_status === AssetAccountingStatus::Recognized
                    ? AssetAccountingStatus::OnHand
                    : $asset->accounting_status;
                $asset->physical_status ??= SemiExpendablePhysicalStatus::InPropertyCustody;
                $asset->accountability_status ??= SemiExpendableAccountabilityStatus::Unassigned;
                $asset->useful_life_effective_date ??= $asset->acquisition_date;
                $asset->relief_status ??= SemiExpendableReliefStatus::None;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'delivery_date' => 'date',
            'inspection_date' => 'date',
            'acceptance_date' => 'date',
            'available_for_use_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'directly_attributable_costs' => 'decimal:2',
            'accounting_classification' => AssetAccountingClassification::class,
            'capitalization_threshold_applied' => 'decimal:2',
            'is_depreciable' => 'boolean',
            'accounting_status' => AssetAccountingStatus::class,
            'derecognized_at' => 'date',
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
            'semi_expendable_value_class' => SemiExpendableValueClass::class,
            'physical_status' => SemiExpendablePhysicalStatus::class,
            'accountability_status' => SemiExpendableAccountabilityStatus::class,
            'accountability_expires_at' => 'date',
            'useful_life_effective_date' => 'date',
            'relief_status' => SemiExpendableReliefStatus::class,
            'incident_reported_at' => 'date',
            'available_for_reissue' => 'boolean',
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

    public function getResidualValueAttribute(): float
    {
        return (float) $this->valuation()->residualValue;
    }

    public function determineCustodyStatus(): AssetCustodyStatus
    {
        if ($this->lifecycle_status === AssetLifecycleStatus::TransferredOut) {
            return AssetCustodyStatus::TransferredOut;
        }
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
        return (float) $this->valuation()->accumulatedDepreciation;
    }

    public function getBookValueAttribute(): float
    {
        return (float) $this->valuation()->carryingAmount;
    }

    public function getGrossCarryingAmountAttribute(): float
    {
        return (float) $this->valuation()->grossCarryingAmount;
    }

    public function getMonthlyDepreciationAttribute(): float
    {
        return (float) $this->valuation()->monthlyDepreciation;
    }

    public function getIsFullyDepreciatedAttribute(): bool
    {
        return $this->valuation()->isFullyDepreciated;
    }

    public function valuation(DateTimeInterface|string|null $asOf = null): PpeValuation
    {
        if ($asOf !== null) {
            return app(PpeDepreciationCalculator::class)->calculate($this, $asOf);
        }

        return $this->currentValuationCache ??= app(PpeDepreciationCalculator::class)->calculate($this);
    }

    /** @return BelongsTo<InventoryAssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryAssetCategory::class, 'category_id', 'inv_asset_cat_id');
    }

    /** @return BelongsTo<ProcurementRequestLine, $this> */
    public function procurementRequestLine(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestLine::class);
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

    /** @return HasOne<PropertyAccountabilityDocument, $this> */
    public function latestParDocument(): HasOne
    {
        return $this->hasOne(PropertyAccountabilityDocument::class, 'inventory_asset_id', 'inventory_asset_id')
            ->ofMany(['issued_at' => 'max', 'id' => 'max'], fn (Builder $query) => $query->where('document_type', 'PAR'));
    }

    /** @return HasOne<PropertyAccountabilityDocument, $this> */
    public function latestIcsDocument(): HasOne
    {
        return $this->hasOne(PropertyAccountabilityDocument::class, 'inventory_asset_id', 'inventory_asset_id')
            ->ofMany(['issued_at' => 'max', 'id' => 'max'], fn (Builder $query) => $query->where('document_type', 'ICS'));
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

    /** @return HasMany<InventoryAssetEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(InventoryAssetEvent::class, 'inventory_asset_id', 'inventory_asset_id')
            ->orderByDesc('event_date')
            ->orderByDesc('id');
    }
}
