import { Head, Link, router } from '@inertiajs/react';
import { DataPagination } from '@/components/data-pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/inventory/warehouse';
import type { PaginationLink } from '@/types/inventory';
import { date, location } from './types';
import type { Batch } from './types';
type Filters = {
    search?: string;
    warehouse?: string;
    placement?: string;
    include_depleted?: boolean | string;
};
export default function WarehouseIndex({
    batches,
    filters,
}: {
    batches: { data: Batch[]; links: PaginationLink[] };
    filters: Filters;
}) {
    return (
        <>
            <Head title="Warehouse storage" />
            <div className="space-y-6 p-4 sm:p-6">
                <h1 className="text-2xl font-semibold">Warehouse storage</h1>
                <p className="text-sm text-muted-foreground">
                    Record where each posted receipt or return batch is stored,
                    review placement history, and print labels. Physical
                    handling and counter-checking remain staff responsibilities.
                </p>
                <form
                    className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            index.url(),
                            {
                                search: String(data.get('search') ?? ''),
                                warehouse: String(data.get('warehouse') ?? ''),
                                placement: String(data.get('placement') ?? ''),
                                include_depleted:
                                    data.get('include_depleted') === 'on'
                                        ? 1
                                        : 0,
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="warehouse-search">
                            Item or source reference
                        </Label>
                        <Input
                            id="warehouse-search"
                            name="search"
                            maxLength={150}
                            defaultValue={filters.search}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="warehouse-filter">
                            Warehouse (exact name)
                        </Label>
                        <Input
                            id="warehouse-filter"
                            name="warehouse"
                            maxLength={150}
                            defaultValue={filters.warehouse}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="placement-filter">Placement</Label>
                        <FormSelect
                            id="placement-filter"
                            name="placement"
                            defaultValue={filters.placement ?? ''}
                        >
                            <option value="">All placements</option>
                            <option value="unplaced">Not yet recorded</option>
                            <option value="placed">Recorded</option>
                        </FormSelect>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="include_depleted"
                                defaultChecked={
                                    filters.include_depleted === true ||
                                    filters.include_depleted === '1'
                                }
                            />
                            Include depleted batches
                        </label>
                        <Button type="submit">Apply filters</Button>
                        <Link href={index()} className="text-sm underline">
                            Reset
                        </Link>
                    </div>
                </form>
                <div className="space-y-3 md:hidden">
                    {batches.data.map((batch) => (
                        <article
                            key={batch.inventory_item_batch_id}
                            className="space-y-2 rounded-lg border p-4"
                        >
                            <Link
                                href={show(batch.inventory_item_batch_id)}
                                className="font-medium underline"
                            >
                                {batch.item.stock_number} / Batch{' '}
                                {batch.batch_number}
                            </Link>
                            <p>{batch.item.name}</p>
                            <p>
                                {batch.quantity_remaining}{' '}
                                {batch.item.unit_of_measure} remaining /{' '}
                                {batch.reference_no ?? 'No source reference'}
                            </p>
                            <p className="text-sm">
                                {location(batch.latest_placement)}
                            </p>
                            <p className="text-sm">
                                Expiry: {date(batch.expiration_date)}
                            </p>
                        </article>
                    ))}
                </div>
                <div className="hidden overflow-x-auto rounded-lg border md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Stock / batch</TableHead>
                                <TableHead>Source reference</TableHead>
                                <TableHead>Remaining</TableHead>
                                <TableHead>Location</TableHead>
                                <TableHead>Expiry</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {batches.data.map((batch) => (
                                <TableRow key={batch.inventory_item_batch_id}>
                                    <TableCell>
                                        <Link
                                            href={show(
                                                batch.inventory_item_batch_id,
                                            )}
                                            className="font-medium underline"
                                        >
                                            {batch.item.stock_number} / Batch{' '}
                                            {batch.batch_number}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {batch.item.name}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {batch.reference_no ?? 'Not recorded'}
                                        <p className="text-xs">
                                            {date(batch.received_at)}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {batch.quantity_remaining}{' '}
                                        {batch.item.unit_of_measure}
                                    </TableCell>
                                    <TableCell className="max-w-xs whitespace-normal">
                                        {location(batch.latest_placement)}
                                    </TableCell>
                                    <TableCell>
                                        {date(batch.expiration_date)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {batches.data.length === 0 && (
                    <p className="rounded-lg border p-8 text-center text-muted-foreground">
                        No posted batches match these filters.
                    </p>
                )}
                <DataPagination links={batches.links} />
            </div>
        </>
    );
}
WarehouseIndex.layout = {
    breadcrumbs: [{ title: 'Warehouse Storage', href: index.url() }],
};
