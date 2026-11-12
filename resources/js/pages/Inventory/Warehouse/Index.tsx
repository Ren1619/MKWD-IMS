import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConsumableInventoryNav } from '@/components/consumable-inventory-nav';
import { DataPagination } from '@/components/data-pagination';
import { PageContainer } from '@/components/page-container';
import { RecordChangeBadge } from '@/components/record-change-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardAction,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index } from '@/routes/inventory/warehouse';
import type { PaginationLink } from '@/types/inventory';
import { BatchStorageDialog } from './BatchStorageDialog';
import { ManageStockroomsDialog } from './Registry';
import { date, location } from './types';
import type { Batch, Warehouse } from './types';
type Filters = {
    search?: string;
    warehouse_id?: string;
    placement?: string;
    include_depleted?: boolean | string;
};
export default function WarehouseIndex({
    batches,
    filters,
    warehouses,
}: {
    batches: { data: Batch[]; links: PaginationLink[]; total: number };
    filters: Filters;
    warehouses: Warehouse[];
}) {
    const [selectedBatch, setSelectedBatch] = useState<Batch | null>(null);
    const [filtering, setFiltering] = useState(false);
    const { submitImmediately, submitAfterDelay } = useFilterSubmit();

    return (
        <>
            <Head title="Consumable Inventory — Storage & Batches" />
            <PageContainer>
                <ConsumableInventoryNav active="storage" />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">
                        Consumable inventory
                    </h1>
                </div>
                <p className="text-sm text-muted-foreground">
                    Record where each posted receipt or return batch is stored,
                    review placement history, and print labels. Physical
                    handling and counter-checking remain staff responsibilities.
                </p>
                <Card>
                    <CardHeader>
                        <CardAction>
                            <ManageStockroomsDialog />
                        </CardAction>
                        <CardTitle>Storage &amp; Batches</CardTitle>
                        <CardDescription>
                            {batches.total} stock batches.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <form
                            key={JSON.stringify(filters)}
                            aria-label="Storage filters"
                            aria-busy={filtering}
                            className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);
                                router.get(
                                    index.url(),
                                    {
                                        search: String(
                                            data.get('search') ?? '',
                                        ),
                                        warehouse_id: String(
                                            data.get('warehouse_id') ?? '',
                                        ),
                                        placement: String(
                                            data.get('placement') ?? '',
                                        ),
                                        include_depleted:
                                            data.get('include_depleted') ===
                                            'on'
                                                ? 1
                                                : 0,
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        onStart: () => setFiltering(true),
                                        onFinish: () => setFiltering(false),
                                    },
                                );
                            }}
                        >
                            <div className="grid w-full gap-1.5 sm:w-80">
                                <Label htmlFor="warehouse-search">
                                    Item or source reference
                                </Label>
                                <Input
                                    id="warehouse-search"
                                    name="search"
                                    type="search"
                                    onChange={submitAfterDelay}
                                    placeholder="Search item or reference"
                                    maxLength={150}
                                    defaultValue={filters.search}
                                />
                            </div>
                            <div className="grid w-full gap-1.5 sm:w-44">
                                <Label htmlFor="warehouse-filter">
                                    Stockroom
                                </Label>
                                <FormSelect
                                    id="warehouse-filter"
                                    name="warehouse_id"
                                    className="h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                                    onChange={submitImmediately}
                                    defaultValue={filters.warehouse_id ?? ''}
                                >
                                    <option value="">All stockrooms</option>
                                    {warehouses.map((warehouse) => (
                                        <option
                                            key={warehouse.id}
                                            value={warehouse.id}
                                        >
                                            {warehouse.name}
                                        </option>
                                    ))}
                                </FormSelect>
                            </div>
                            <div className="grid w-full gap-1.5 sm:w-44">
                                <Label htmlFor="placement-filter">
                                    Placement status
                                </Label>
                                <FormSelect
                                    id="placement-filter"
                                    name="placement"
                                    className="h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                                    onChange={submitImmediately}
                                    defaultValue={filters.placement ?? ''}
                                >
                                    <option value="">All placements</option>
                                    <option value="unplaced">
                                        Not yet recorded
                                    </option>
                                    <option value="placed">Recorded</option>
                                </FormSelect>
                            </div>
                            <div className="flex shrink-0 items-center gap-4">
                                <div className="grid w-full gap-1.5 sm:w-44">
                                    <Label htmlFor="include-depleted">
                                        Stock balance
                                    </Label>
                                    <FormSelect
                                        id="include-depleted"
                                        name="include_depleted"
                                        className="h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                                        value={
                                            filters.include_depleted === true ||
                                            filters.include_depleted === '1'
                                                ? 'on'
                                                : ''
                                        }
                                        onChange={submitImmediately}
                                    >
                                        <option value="">
                                            Remaining stock
                                        </option>
                                        <option value="on">
                                            Include depleted batches
                                        </option>
                                    </FormSelect>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        render={
                                            <Link
                                                href={index()}
                                                preserveScroll
                                            />
                                        }
                                    >
                                        Clear filters
                                    </Button>
                                    <Button type="submit" disabled={filtering}>
                                        {filtering
                                            ? 'Applying filters...'
                                            : 'Apply filters'}
                                    </Button>
                                </div>
                            </div>
                        </form>
                        <div className="overflow-x-auto rounded-lg border border-border/70">
                            <Table className="w-full min-w-[900px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <TableRow>
                                        <TableHead>Stock no.</TableHead>
                                        <TableHead>Item / batch</TableHead>
                                        <TableHead>Source reference</TableHead>
                                        <TableHead className="text-right">
                                            Remaining
                                        </TableHead>
                                        <TableHead>Location</TableHead>
                                        <TableHead>Expiry</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {batches.data.map((batch) => (
                                        <TableRow
                                            key={batch.inventory_item_batch_id}
                                            role="link"
                                            tabIndex={0}
                                            aria-label={`View storage for ${batch.item.name}, batch ${batch.batch_number}`}
                                            className="cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                            onClick={(event) => {
                                                if (
                                                    event.target instanceof
                                                        Element &&
                                                    !event.target.closest(
                                                        'button, a, input, select, textarea',
                                                    )
                                                ) {
                                                    setSelectedBatch(batch);
                                                }
                                            }}
                                            onKeyDown={(event) => {
                                                if (
                                                    event.target ===
                                                        event.currentTarget &&
                                                    (event.key === 'Enter' ||
                                                        event.key === ' ')
                                                ) {
                                                    event.preventDefault();
                                                    setSelectedBatch(batch);
                                                }
                                            }}
                                        >
                                            <TableCell className="font-mono text-xs">
                                                {batch.item.stock_number}
                                            </TableCell>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {batch.item.name}
                                                </span>
                                                <p className="text-xs text-muted-foreground">
                                                    Batch {batch.batch_number}
                                                    <RecordChangeBadge
                                                        collection="batches"
                                                        record={batch}
                                                    />
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {batch.reference_no ??
                                                    'Not recorded'}
                                                <p className="text-xs">
                                                    {date(batch.received_at)}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-right font-semibold">
                                                {batch.quantity_remaining}{' '}
                                                {batch.item.unit_of_measure}
                                            </TableCell>
                                            <TableCell className="max-w-xs whitespace-normal text-muted-foreground">
                                                {location(
                                                    batch.latest_placement,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {date(batch.expiration_date)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {batches.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="h-24 text-center text-muted-foreground"
                                            >
                                                No posted batches match these
                                                filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataPagination links={batches.links} />
                    </CardContent>
                </Card>
            </PageContainer>
            {selectedBatch && (
                <BatchStorageDialog
                    key={selectedBatch.inventory_item_batch_id}
                    batch={selectedBatch}
                    warehouses={warehouses}
                    onClose={() => setSelectedBatch(null)}
                />
            )}
        </>
    );
}
WarehouseIndex.layout = {
    breadcrumbs: [
        { title: 'Consumable inventory', href: itemsIndex() },
        { title: 'Storage & Batches', href: index() },
    ],
};
