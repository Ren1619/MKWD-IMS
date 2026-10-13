export type ReturnSource = {
    id: number;
    item_name: string;
    stock_number: string;
    unit_of_measure: string;
    issue_reference: string;
    issue_date: string;
    recipient_name: string;
    department_name: string | null;
    batch_number: number;
    unit_cost: string;
    expiration_date: string | null;
    available_quantity: number;
};
export type ReturnLine = {
    id: number;
    stock_number: string;
    item_name: string;
    unit_of_measure: string;
    source_issue_reference: string | null;
    quantity_declared: number;
    quantity_accepted: number | null;
    unit_cost: string;
    expiration_date: string | null;
    condition: string | null;
    storage_location: string | null;
    inspection_remarks: string | null;
    valuation_basis: string | null;
    posting: {
        inventory_item_batch_id: number;
        quantity: number;
        unit_cost: string;
    } | null;
};
export type ReturnAction = {
    id: number;
    actor_name: string;
    action: string;
    remarks: string;
    created_at: string;
    metadata: Record<string, unknown> | null;
};
export type ReturnDocument = {
    id: number;
    document_no: string;
    return_date: string;
    returned_by_name: string;
    department_name: string;
    source_mode: 'linked' | 'legacy';
    status: string;
    reason: string;
    endorsement_reference: string;
    inspection_reference: string | null;
    legacy_issue_reference: string | null;
    approved_at: string | null;
    accounting_endorsed_at: string | null;
    lines_count?: number;
    lines: ReturnLine[];
    actions: ReturnAction[];
};
export const todayInManila = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
export const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
export const money = (value: string | number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(value));
