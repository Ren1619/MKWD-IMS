import type { ComponentProps } from 'react';
import { index } from '@/routes/inventory/purchase_requests';
import ProcurementIndex from './Index';

export default function PurchaseRequests(
    props: ComponentProps<typeof ProcurementIndex>,
) {
    return <ProcurementIndex key="PR" {...props} moduleKind="PR" />;
}

PurchaseRequests.layout = {
    breadcrumbs: [{ title: 'Purchase Requests', href: index() }],
};
