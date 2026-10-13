import type { ComponentProps } from 'react';
import { index } from '@/routes/inventory/job_orders';
import ProcurementIndex from './Index';

export default function JobOrders(
    props: ComponentProps<typeof ProcurementIndex>,
) {
    return <ProcurementIndex key="JO" {...props} moduleKind="JO" />;
}

JobOrders.layout = { breadcrumbs: [{ title: 'Job Orders', href: index() }] };
