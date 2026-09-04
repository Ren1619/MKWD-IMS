export type HrisReference = {
    id: number;
    external_id?: string;
    type?: string;
    code: string | null;
    name: string;
    email?: string | null;
    is_active?: boolean;
    last_synced_at?: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

export type MajorCategory = {
    inv_mjr_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    class_categories_count?: number;
    class_categories?: ClassCategory[];
};

export type ClassCategory = {
    inv_class_cat_id: number;
    inv_mjr_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    series_categories_count?: number;
    series_categories?: SeriesCategory[];
    major_category?: MajorCategory;
};

export type SeriesCategory = {
    inv_series_cat_id: number;
    inv_class_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    items_count?: number;
    class_category?: ClassCategory;
};

export type InventoryItemBatch = {
    inventory_item_batch_id: number;
    inventory_item_id: number;
    batch_number: number;
    quantity_in: number;
    quantity_remaining: number;
    unit_cost: string;
    received_at: string;
    expiration_date: string | null;
    source: string | null;
    reference_no: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
};

export type InventoryItemStockOutAllocation = {
    inventory_item_stock_out_allocation_id: number;
    inventory_item_stock_out_id: number;
    inventory_item_batch_id: number;
    quantity: number;
    unit_cost: string;
    batch: InventoryItemBatch;
};

export type InventoryItemStockOut = {
    inventory_item_stock_out_id: number;
    inventory_item_id: number;
    recipient_reference_id: number | null;
    recipient_name: string | null;
    ris_no: string | null;
    responsibility_center_code: string | null;
    quantity: number;
    total_cost: string;
    stocked_out_at: string;
    notes: string | null;
    recipient_reference: HrisReference | null;
    allocations: InventoryItemStockOutAllocation[];
};

export type InventoryItem = {
    inventory_item_id: number;
    series_category_id: number;
    accountable_reference_id: number | null;
    name: string;
    stock_number: string | null;
    unit_of_measure: string;
    uacs_object_code: string | null;
    description: string | null;
    quantity: number;
    reorder_point: number;
    reorder_quantity: number | null;
    inventory_value: string;
    next_expiration_date: string | null;
    is_low_stock: boolean;
    expiration_status: 'expired' | 'expiring' | 'current' | null;
    status: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    series_category: SeriesCategory;
    accountable_reference: HrisReference | null;
    batches: InventoryItemBatch[];
};

export type InventoryItemDetailsResponse = {
    item: InventoryItem;
    releases: Paginated<InventoryItemStockOut> & {
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};

export type AssetCategory = {
    inv_asset_cat_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    assets_count?: number;
};

export type AssetLifecycleStatus =
    'active' | 'under_maintenance' | 'retired' | 'disposed' | 'lost';

export type AssetConditionStatus =
    'good' | 'fair' | 'needs_repair' | 'defective' | 'non_usable' | 'unknown';

export type AssetCustodyStatus = 'available' | 'assigned' | 'borrowed';

export type AssetAccountingClassification =
    'ppe' | 'semi_expendable' | 'needs_review';

export type AssetStateOption = {
    value: string;
    label: string;
};

export type AssetStateOptions = {
    lifecycles: AssetStateOption[];
    conditions: AssetStateOption[];
};

export type InventoryAsset = {
    inventory_asset_id: number;
    category_id: number;
    current_custodian_reference_id: number | null;
    serial_number: string;
    property_number: string | null;
    property_tag_uuid: string;
    name: string;
    type: string | null;
    unit_of_measure: string;
    fund_cluster: string | null;
    quantity_per_property_card: number;
    quantity_per_physical_count: number;
    brand: string | null;
    model: string | null;
    description: string | null;
    location: string | null;
    nature_of_occupancy: string | null;
    acquisition_date: string;
    available_for_use_date: string | null;
    acquisition_cost: string | null;
    accounting_classification: AssetAccountingClassification;
    residual_value_percentage: string | null;
    residual_value_basis: string | null;
    depreciation_useful_life_months: number;
    depreciation_amount: number;
    residual_value: number;
    book_value: number;
    is_depreciable: boolean;
    appraised_value: string | null;
    appraisal_date: string | null;
    impairment_losses: string;
    physical_count_remarks: string | null;
    disposal_method: string | null;
    disposal_value: string | null;
    loss_report_no: string | null;
    loss_report_date: string | null;
    loss_type: string | null;
    loss_circumstances: string | null;
    lifecycle_status: AssetLifecycleStatus;
    condition_status: AssetConditionStatus;
    custody_status: AssetCustodyStatus;
    is_assignable: boolean;
    is_borrowable: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    category: AssetCategory;
    current_custodian: HrisReference | null;
    active_borrowing: {
        borrower_name: string | null;
        notes: string | null;
        borrowed_at: string;
        due_at: string | null;
        borrower_reference: HrisReference | null;
    } | null;
};

export type InventoryReportSummary = {
    consumable_types: number;
    stock_on_hand: number;
    low_stock: number;
    consumable_value: number;
    expiring_value: number;
    expired_value: number;
    asset_count: number;
    acquisition_cost: number;
    depreciation: number;
    book_value: number;
};

export type InventoryReportFilters = {
    report: 'consumables' | 'assets';
    search: string;
    records: 'active' | 'archived';
    item_status: string;
    attention: string;
    lifecycle_status: string;
    condition_status: string;
    custody_status: string;
};

export type InventoryReportOptions = {
    lifecycles: AssetStateOption[];
    conditions: AssetStateOption[];
    custody: AssetStateOption[];
};
