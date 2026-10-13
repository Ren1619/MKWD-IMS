export type Transfer = {
    id: number;
    document_no: string;
    inventory_asset_id: number;
    status: string;
    transfer_type: string;
    reason: string;
    destination_location: string;
    desired_date: string;
    completed_at: string | null;
    replacement_document_id: number | null;
    source_snapshot: {
        name: string;
        property_number: string | null;
        recipient_name: string;
        recipient_office: string | null;
        document_no: string;
        location: string;
    };
    recipient_snapshot: {
        name: string;
        office: string | null;
        position: string | null;
    };
    actions: {
        id: number;
        action: string;
        actor_name: string;
        attestation: string;
        created_at: string;
        evidence: Record<string, string>;
    }[];
};
export const label = (value: string) =>
    value.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase());
