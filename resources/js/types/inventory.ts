export type HrisReference = {
    id: number;
    external_id?: string;
    type?: string;
    code: string | null;
    name: string;
    email?: string | null;
    is_active?: boolean;
    last_synced_at?: string;
    first_name?: string | null;
    middle_name?: string | null;
    last_name?: string | null;
    name_extension?: string | null;
    office_name?: string | null;
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
    issuable_quantity: number;
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
    is_vehicle: boolean;
    account_code: string | null;
    default_useful_life_months: number;
    default_residual_value_percentage: string;
    is_depreciable: boolean;
    assets_count?: number;
    subcategories_count?: number;
    subcategories?: AssetSubcategory[];
};

export type AssetSubcategory = {
    inventory_asset_subcategory_id: number;
    inventory_asset_category_id: number;
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    assets_count?: number;
};

export type AssetLifecycleStatus =
    | 'active'
    | 'idle'
    | 'under_maintenance'
    | 'retired'
    | 'for_disposal'
    | 'disposed'
    | 'lost'
    | 'transferred_out';

export type AssetConditionStatus =
    'good' | 'fair' | 'needs_repair' | 'defective' | 'non_usable' | 'unknown';

export type AssetCustodyStatus =
    'available' | 'assigned' | 'borrowed' | 'transferred_out';

export type AssetAccountingClassification =
    'ppe' | 'semi_expendable' | 'needs_review';

export type AssetAccountingStatus =
    | 'recognized'
    | 'derecognized'
    | 'on_hand'
    | 'issued_and_expensed'
    | 'disposed'
    | 'adjusted';

export type InventoryAssetEventType =
    | 'registered'
    | 'accepted'
    | 'capital_addition'
    | 'transfer'
    | 'maintenance'
    | 'impairment'
    | 'impairment_reversal'
    | 'physical_count'
    | 'loss'
    | 'unserviceable'
    | 'disposal'
    | 'derecognition'
    | 'assignment'
    | 'return'
    | 'borrowed'
    | 'borrowing_return'
    | 'state_changed'
    | 'accounting_policy_changed'
    | 'receipt'
    | 'issuance'
    | 'ics_created'
    | 'ics_renewed'
    | 'reissue'
    | 'accountability_expired'
    | 'accountability_extinguished'
    | 'relief_requested'
    | 'relief_approved'
    | 'relief_denied';

export type InventoryAssetEvent = {
    id: number;
    event_type: InventoryAssetEventType;
    event_date: string;
    reference_no: string | null;
    amount: string | null;
    quantity: number | null;
    from_location: string | null;
    to_location: string | null;
    condition_status: AssetConditionStatus | null;
    notes: string | null;
    details: Record<string, unknown> | null;
    created_at: string;
    recorded_by: { id: number; name: string } | null;
};

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
    subcategory_id: number | null;
    current_custodian_reference_id: number | null;
    serial_number: string | null;
    property_number: string | null;
    old_property_number: string | null;
    account_code: string | null;
    property_tag_uuid: string;
    name: string;
    unit_of_measure: string;
    fund_cluster: string | null;
    quantity_per_property_card: number;
    quantity_per_physical_count: number;
    brand: string | null;
    manufacturer: string | null;
    model: string | null;
    description: string | null;
    specifications: string | null;
    location: string | null;
    nature_of_occupancy: string | null;
    acquisition_date: string;
    acquisition_method: string | null;
    supplier_name: string | null;
    purchase_order_no: string | null;
    inspection_acceptance_report_no: string | null;
    invoice_no: string | null;
    disbursement_voucher_no: string | null;
    delivery_date: string | null;
    inspection_date: string | null;
    acceptance_date: string | null;
    available_for_use_date: string | null;
    acquisition_cost: string | null;
    directly_attributable_costs: string;
    accounting_classification: AssetAccountingClassification;
    accounting_policy_version: string | null;
    capitalization_threshold_applied: string | null;
    depreciation_method: string | null;
    depreciation_start_rule: string | null;
    accounting_status: AssetAccountingStatus;
    derecognized_at: string | null;
    residual_value_percentage: string | null;
    residual_value_basis: string | null;
    depreciation_useful_life_months: number;
    depreciation_amount: number;
    gross_carrying_amount: number;
    monthly_depreciation: number;
    residual_value: number;
    book_value: number;
    is_depreciable: boolean;
    is_fully_depreciated: boolean;
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
    semi_expendable_value_class: 'low' | 'high' | null;
    physical_status:
        | 'in_property_custody'
        | 'issued'
        | 'returned'
        | 'transferred'
        | 'for_disposal'
        | 'disposed'
        | 'missing'
        | null;
    accountability_status:
        | 'unassigned'
        | 'pending_recipient'
        | 'accountable'
        | 'returned'
        | 'accountability_extinguished'
        | 'pending_relief'
        | 'relieved'
        | 'transferred_out'
        | null;
    accountability_expires_at: string | null;
    useful_life_effective_date: string | null;
    relief_status: 'none' | 'pending' | 'approved' | 'denied' | null;
    incident_reported_at: string | null;
    available_for_reissue: boolean;
    custody_status: AssetCustodyStatus;
    is_assignable: boolean;
    is_borrowable: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    category: AssetCategory;
    subcategory: AssetSubcategory | null;
    current_custodian: HrisReference | null;
    active_borrowing: {
        borrower_name: string | null;
        notes: string | null;
        borrowed_at: string;
        due_at: string | null;
        borrower_reference: HrisReference | null;
    } | null;
    events: InventoryAssetEvent[];
    latest_ics_document: {
        id: number;
        document_no: string;
        status: string;
        issued_at: string;
        renewal_due_at: string | null;
        accountability_expires_at: string | null;
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
