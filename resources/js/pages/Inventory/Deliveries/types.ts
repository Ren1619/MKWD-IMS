export type OrderLine = {
    id: number;
    item_name: string;
    unit_of_measure: string;
    item_kind: string;
    specifications: string | null;
    unit_cost: string;
    available_quantity: number;
};
export type Order = {
    id: number;
    pr_no: string;
    purchase_order_no: string;
    lines: OrderLine[];
};
export type DeliveryLine = {
    id: number;
    item_name: string;
    unit_of_measure: string;
    specifications: string | null;
    quantity_delivered: number;
    quantity_accepted: number | null;
    unit_cost: string;
    expiration_date: string | null;
    condition: string | null;
    disposition: string | null;
    storage_location: string | null;
    disposition_reference: string | null;
    findings: string | null;
    inventory_item_batch_id: number | null;
    asset_ids: number[] | null;
    order_line: { item_kind: string };
};
export type DeliveryAction = {
    id: number;
    actor_name: string;
    action: string;
    created_at: string;
    remarks: string;
    metadata: Record<string, unknown> | null;
};
export type Delivery = {
    id: number;
    document_no: string;
    purchase_order_no: string;
    supplier_name: string;
    invoice_number: string;
    delivery_reference: string;
    received_at: string;
    receiving_department: string;
    noa_reference: string | null;
    ntp_reference: string | null;
    order_document_basis: string;
    notification_reference: string;
    packaging_findings: string;
    status: string;
    inspection_acceptance_no: string | null;
    receiving_report_no: string | null;
    supplier_return_no: string | null;
    accepted_at: string | null;
    supplier_returned_at: string | null;
    endorsed_at: string | null;
    inspection: Record<string, unknown> | null;
    procurement_request: { id: number; pr_no: string };
    lines_count?: number;
    lines: DeliveryLine[];
    actions: DeliveryAction[];
};
export const title = (text: string) =>
    text.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
export const money = (value: string | number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(value));
export const manilaTime = (value: string) =>
    new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
export const localReceiptTime = () =>
    new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    })
        .format(new Date())
        .replace(' ', 'T');
export const areaClass =
    'min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';
