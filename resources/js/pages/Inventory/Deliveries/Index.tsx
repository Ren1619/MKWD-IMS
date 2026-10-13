import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import {
    Table,
    TableHeader,
    TableHead,
    TableRow,
    TableBody,
    TableCell,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, create, show } from '@/routes/inventory/deliveries';
import type { Paginated } from '@/types/inventory';
import { title, manilaTime } from './types';
import type { Delivery } from './types';
export default function DeliveryIndex({
    documents,
    filters,
}: {
    documents: Paginated<Delivery>;
    filters: {
        search?: string;
        status?: string;
        procurement_request_id?: number;
    };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    return (
        <>
            <Head title="Delivery inspection" />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Delivery inspection and acceptance
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Shipment evidence, joint inspection, accepted
                            receipts, and supplier returns.
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        render={
                            <Link
                                href={create({
                                    query: {
                                        procurement_request_id:
                                            filters.procurement_request_id,
                                    },
                                })}
                            />
                        }
                    >
                        Record shipment
                    </Button>
                </div>
                <form
                    className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_12rem_auto]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            index.url(),
                            {
                                search,
                                status,
                                procurement_request_id:
                                    filters.procurement_request_id,
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="delivery-search">
                            Shipment, PO, or supplier
                        </Label>
                        <Input
                            id="delivery-search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            maxLength={150}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="delivery-status">Status</Label>
                        <FormSelect
                            id="delivery-status"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">All statuses</option>
                            {[
                                'received',
                                'inspected',
                                'accepted',
                                'rejected',
                                'cancelled',
                            ].map((value) => (
                                <option key={value} value={value}>
                                    {title(value)}
                                </option>
                            ))}
                        </FormSelect>
                    </div>
                    <Button className="self-end">Apply filters</Button>
                </form>
                {filters.procurement_request_id && (
                    <Link href={index()} className="text-sm underline">
                        Show all purchase orders
                    </Link>
                )}
                <div className="space-y-3 md:hidden">
                    {documents.data.map((doc) => (
                        <article
                            className="space-y-2 rounded-lg border p-4"
                            key={doc.id}
                        >
                            <Link
                                className="font-medium underline"
                                href={show(doc.id)}
                            >
                                {doc.document_no}
                            </Link>
                            <Badge variant="outline">{title(doc.status)}</Badge>
                            <p className="text-sm">
                                {doc.purchase_order_no} / {doc.supplier_name}
                            </p>
                            <p className="text-sm">
                                {doc.delivery_reference} /{' '}
                                {manilaTime(doc.received_at)}
                            </p>
                        </article>
                    ))}
                </div>
                <div className="hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Shipment</TableHead>
                                <TableHead>PO / Supplier</TableHead>
                                <TableHead>Physical receipt</TableHead>
                                <TableHead>Lines</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((doc) => (
                                <TableRow key={doc.id}>
                                    <TableCell>
                                        <Link
                                            href={show(doc.id)}
                                            className="font-medium underline"
                                        >
                                            {doc.document_no}
                                        </Link>
                                        <p className="text-xs">
                                            {doc.delivery_reference}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {doc.purchase_order_no}
                                        <p>{doc.supplier_name}</p>
                                    </TableCell>
                                    <TableCell>
                                        {manilaTime(doc.received_at)}
                                    </TableCell>
                                    <TableCell>{doc.lines_count}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {title(doc.status)}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {documents.data.length === 0 && (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        No shipments match these filters.
                    </p>
                )}
                <DataPagination links={documents.links} />
            </div>
        </>
    );
}
DeliveryIndex.layout = {
    breadcrumbs: [{ title: 'Delivery Inspection', href: index.url() }],
};
