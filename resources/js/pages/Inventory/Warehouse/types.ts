export type Placement = {
    id: number;
    version: number;
    warehouse: string;
    area: string;
    rack: string | null;
    bin: string | null;
    observed_quantity: number;
    source_evidence_reference: string;
    remarks: string;
    recorded_by_name: string;
    created_at: string;
    source_details: {
        stock_number?: string;
        item_name?: string;
        source_reference?: string | null;
        expiration_date?: string | null;
    };
};
export type Batch = {
    inventory_item_batch_id: number;
    batch_number: number;
    quantity_in: number;
    quantity_remaining: number;
    received_at: string;
    expiration_date: string | null;
    reference_no: string | null;
    source: string | null;
    item: {
        stock_number: string;
        name: string;
        unit_of_measure: string;
        status: string;
        deleted_at: string | null;
    };
    latest_placement: Placement | null;
    placements?: Placement[];
};
export function location(placement: Placement | null): string {
    return placement
        ? [placement.warehouse, placement.area, placement.rack, placement.bin]
              .filter(Boolean)
              .join(' / ')
        : 'Not yet recorded';
}
export function date(value: string | null): string {
    return value ? value.slice(0, 10) : 'Not specified';
}
export function time(value: string): string {
    return new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
}
