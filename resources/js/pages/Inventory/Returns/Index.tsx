import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, show } from '@/routes/inventory/returns';
import type { Paginated } from '@/types/inventory';
import { label } from './types';
import type { ReturnDocument } from './types';

export default function ReturnsIndex({
    documents,
    filters,
}: {
    documents: Paginated<ReturnDocument>;
    filters: { search?: string; status?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    return (
        <>
            <Head title="Return to stock" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Return to stock
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            RMS records for excess and unused consumables, from
                            inspection through approved receipt.
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        render={<Link href={create()} />}
                    >
                        Prepare return slip
                    </Button>
                </div>
                <form
                    className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_14rem_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            index.url(),
                            { search, status },
                            { preserveState: true },
                        );
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="return-search">
                            Reference, person, or department
                        </Label>
                        <Input
                            id="return-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            maxLength={150}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="return-status">Status</Label>
                        <FormSelect
                            id="return-status"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {[
                                'submitted',
                                'verified',
                                'approved',
                                'rejected',
                                'cancelled',
                            ].map((value) => (
                                <option key={value} value={value}>
                                    {label(value)}
                                </option>
                            ))}
                        </FormSelect>
                    </div>
                    <Button className="self-end">Apply filters</Button>
                </form>
                <div className="grid gap-3 md:hidden">
                    {documents.data.map((document) => (
                        <article
                            key={document.id}
                            className="rounded-lg border p-4"
                        >
                            <div className="flex justify-between gap-3">
                                <Link
                                    href={show(document.id)}
                                    className="font-medium underline"
                                >
                                    {document.document_no}
                                </Link>
                                <Badge variant="secondary">
                                    {label(document.status)}
                                </Badge>
                            </div>
                            <p className="mt-2 text-sm">
                                {document.returned_by_name} ·{' '}
                                {document.department_name}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {document.return_date.slice(0, 10)} ·{' '}
                                {document.lines_count} lines
                            </p>
                        </article>
                    ))}
                </div>
                <div className="hidden overflow-x-auto rounded-lg border md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>RMS number</TableHead>
                                <TableHead>Return date</TableHead>
                                <TableHead>Returned by / department</TableHead>
                                <TableHead>Lines</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((document) => (
                                <TableRow key={document.id}>
                                    <TableCell>
                                        <Link
                                            href={show(document.id)}
                                            className="font-medium underline underline-offset-4"
                                        >
                                            {document.document_no}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {document.return_date.slice(0, 10)}
                                    </TableCell>
                                    <TableCell>
                                        {document.returned_by_name}
                                        <p className="text-xs text-muted-foreground">
                                            {document.department_name}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {document.lines_count}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {label(document.status)}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {documents.data.length === 0 && (
                    <p className="rounded-lg border p-10 text-center text-muted-foreground">
                        No return slips match these filters.
                    </p>
                )}
                <DataPagination links={documents.links} />
            </div>
        </>
    );
}
ReturnsIndex.layout = {
    breadcrumbs: [{ title: 'Return to stock', href: index() }],
};
