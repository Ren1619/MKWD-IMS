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
import { index, create, show } from '@/routes/inventory/monthly_reports';
import type { Paginated } from '@/types/inventory';
import { label, lastCompletedMonth } from './types';
import type { Packet } from './types';
export default function MonthlyIndex({
    documents,
    filters,
}: {
    documents: Paginated<Packet>;
    filters: { search?: string; period_month?: string; status?: string };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [month, setMonth] = useState(filters.period_month ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    return (
        <>
            <Head title="Monthly report packages" />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Monthly report packages
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Reconciled, signed monthly registers and stock
                            master lists with preserved report snapshots.
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        render={<Link href={create()} />}
                    >
                        Prepare monthly package
                    </Button>
                </div>
                <form
                    className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-[1fr_10rem_12rem_auto]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            index.url(),
                            { search, period_month: month, status },
                            { preserveState: true },
                        );
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="monthly-search">
                            Package reference
                        </Label>
                        <Input
                            id="monthly-search"
                            value={search}
                            maxLength={150}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="monthly-month">Month</Label>
                        <Input
                            id="monthly-month"
                            type="month"
                            max={lastCompletedMonth()}
                            value={month}
                            onChange={(e) => setMonth(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="monthly-status">Status</Label>
                        <FormSelect
                            id="monthly-status"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="">All statuses</option>
                            {[
                                'prepared',
                                'checked',
                                'noted',
                                'approved',
                                'transmitted',
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
                <div className="space-y-3 md:hidden">
                    {documents.data.map((doc) => (
                        <article
                            key={doc.id}
                            className="space-y-2 rounded-lg border p-4"
                        >
                            <Link
                                href={show(doc.id)}
                                className="font-medium underline"
                            >
                                {doc.document_no}
                            </Link>
                            <Badge variant="outline">{label(doc.status)}</Badge>
                            <p className="text-sm">
                                {doc.period_month.slice(0, 7)} / Revision{' '}
                                {doc.revision}
                            </p>
                            <p className="text-sm">
                                {doc.classification_name} /{' '}
                                {doc.include_zero
                                    ? 'With zero balances'
                                    : 'Without zero balances'}
                            </p>
                        </article>
                    ))}
                </div>
                <div className="hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Package</TableHead>
                                <TableHead>Month / revision</TableHead>
                                <TableHead>Stock master selection</TableHead>
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
                                    </TableCell>
                                    <TableCell>
                                        {doc.period_month.slice(0, 7)} /{' '}
                                        {doc.revision}
                                    </TableCell>
                                    <TableCell>
                                        {doc.classification_name}
                                        <p className="text-xs">
                                            {doc.include_zero
                                                ? 'With zero balances'
                                                : 'Without zero balances'}{' '}
                                            / Reporting fund: {doc.fund_cluster}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {label(doc.status)}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {documents.data.length === 0 && (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        No monthly packages match these filters.
                    </p>
                )}
                <DataPagination links={documents.links} />
            </div>
        </>
    );
}
MonthlyIndex.layout = {
    breadcrumbs: [{ title: 'Monthly Reports', href: index.url() }],
};
