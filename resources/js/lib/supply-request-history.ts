type HistoryAction = {
    action: string;
    to_status: string;
    metadata?: { approval_stage?: string | null } | null;
};

export function supplyRequestHistoryDescription(entry: HistoryAction): string {
    switch (entry.action) {
        case 'submit':
            return 'submitted the request.';
        case 'approve':
            if (
                entry.metadata?.approval_stage === 'division' ||
                entry.to_status === 'division_approved'
            ) {
                return 'approved the request as division head.';
            }

            if (entry.metadata?.approval_stage === 'department') {
                return 'approved the request as department head.';
            }

            return 'approved the request.';
        case 'review':
            return entry.to_status === 'ready_for_release'
                ? 'reviewed stock and marked the request ready for release.'
                : 'reviewed stock and marked the request awaiting replenishment.';
        case 'release':
            return entry.to_status === 'partially_released'
                ? 'partially released the requested supplies.'
                : 'released all requested supplies.';
        case 'reject':
            return 'rejected the request.';
        case 'cancel':
            return 'cancelled the request.';
        case 'reassess_after_procurement':
            return entry.to_status === 'ready_for_release'
                ? 'reassessed stock after procurement; the request is ready for release.'
                : 'reassessed stock after procurement; the request awaits replenishment.';
        case 'count_adjustment_requires_review':
            return 'marked the request for stock review after a count adjustment.';
        case 'record_return_slip':
            return 'recorded the return slip reference.';
        default:
            return `recorded ${entry.action.replaceAll('_', ' ')}.`;
    }
}
