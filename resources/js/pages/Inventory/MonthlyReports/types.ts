export type Blocker = {
    kind: string;
    reference: string;
    status: string;
    reason: string;
};
export type Readiness = {
    blockers: Blocker[];
    totals: {
        stock_master_rows: number;
        closing_stock_value: string;
        issues: number;
        adjustments: number;
        reports: number;
    };
};
export type ReportTable = {
    title: string;
    columns: string[];
    rows: (string | number | null)[][];
};
export type Snapshot = {
    id: number;
    version: number;
    source_hash: string;
    created_at: string;
    readiness: Readiness;
    payload: {
        entity_name: string;
        from: string;
        to: string;
        reports: Record<string, ReportTable>;
        reconciliation: Record<string, string | number>[];
    };
};
export type Packet = {
    series_category_id: number | null;
    id: number;
    document_no: string;
    period_month: string;
    revision: number;
    fund_cluster: string;
    classification_name: string;
    include_zero: boolean;
    status: string;
    reconciliation_reference: string;
    correction_reason: string | null;
    approved_at: string | null;
    transmitted_at: string | null;
    signatories: Record<
        string,
        { user_id: number; name: string; role: string }
    >;
    snapshot: Snapshot;
    supersedes: { id: number; document_no: string } | null;
    actions: {
        id: number;
        actor_name: string;
        action: string;
        created_at: string;
        remarks: string;
        inventory_monthly_report_snapshot_id: number;
        metadata: Record<string, unknown> | null;
    }[];
};
export const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
export const manilaTime = (value: string) =>
    new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
export const money = (value: string) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(Number(value));
export const lastCompletedMonth = () => {
    const current = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
    }).format(new Date());
    const [year, month] = current.split('-').map(Number);

    return new Date(Date.UTC(year, month - 2, 1)).toISOString().slice(0, 7);
};
export const areaClass =
    'min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';
