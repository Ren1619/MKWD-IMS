export type Disposal = {
    id: number;
    document_no: string;
    place_of_storage: string;
    method: string;
    reason: string;
    status: string;
    certifier_snapshot: { name: string };
    certification_reference: string;
    certification_attestation: string;
    lines_count?: number;
    created_at: string;
    lines: {
        id: number;
        inventory_asset_id: number | null;
        quantity: number;
        unit: string;
        description: string;
        source_reference: string;
        proceeds: string | null;
        or_number: string | null;
    }[];
    actions: {
        id: number;
        action: string;
        actor_name: string;
        attestation: string;
        created_at: string;
    }[];
};
export const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase());
