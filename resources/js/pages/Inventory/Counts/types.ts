export type CountSession = {
    id: number;
    document_no: string;
    title: string;
    scheduled_date: string;
    memorandum_reference: string;
    team_details: string;
    status: string;
    cutoff_at: string | null;
    completed_at: string | null;
    lines_count?: number;
};
export type CountEntry = {
    id: number;
    quantity: number;
    observed_quantity: number | null;
    after_count_issues_at_observation: number;
    condition: string;
    location: string;
    aisle: string | null;
    bin: string | null;
    counter_name: string;
    checker_name: string;
    validator_name: string;
    remarks: string | null;
    created_at: string;
};
export type CountLine = {
    id: number;
    tag_no: string;
    stock_number: string;
    item_name: string;
    unit_of_measure: string;
    book_quantity: number;
    unit_cost: string;
    before_count_issues: number;
    after_count_issues: number;
    segregated_receipts: number;
    adjustment_unit_cost: string | null;
    valuation_basis: string | null;
    latest_entry: CountEntry | null;
    entries: CountEntry[];
};
export const dateInManila = () =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date());
export const statusLabel = (status: string) =>
    status.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
