import { useHttp } from '@inertiajs/react';
import {
    CalendarDays,
    ClipboardList,
    PackageSearch,
    ReceiptText,
    Warehouse,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show } from '@/routes/inventory/items';
import type {
    InventoryItem,
    InventoryItemDetailsResponse,
    InventoryItemStockOut,
} from '@/types/inventory';

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
});

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not provided';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : dateFormatter.format(date);
}

function releaseRecipient(release: InventoryItemStockOut): string {
    return (
        release.recipient_reference?.name ??
        release.recipient_name ??
        'Unspecified recipient'
    );
}

function LoadingState() {
    return (
        <div className="grid gap-4 p-4 sm:p-6">
            <div className="grid animate-pulse gap-3 sm:grid-cols-3">
                {[0, 1, 2].map((key) => (
                    <div key={key} className="h-24 rounded-xl bg-muted" />
                ))}
            </div>
            <div className="h-52 animate-pulse rounded-xl bg-muted" />
            <div className="h-52 animate-pulse rounded-xl bg-muted" />
        </div>
    );
}

export function InventoryItemDetailsDialog({
    item,
    open,
    onOpenChange,
}: {
    item: InventoryItem;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { get, cancel, processing } = useHttp<
        Record<string, never>,
        InventoryItemDetailsResponse
    >({});
    const [details, setDetails] = useState<InventoryItemDetailsResponse | null>(
        null,
    );
    const [error, setError] = useState<string | null>(null);

    const loadDetails = useCallback(
        (url: string) => {
            void get(url, {
                onSuccess: setDetails,
                onHttpException: () => {
                    setError('The inventory history could not be loaded.');
                },
                onNetworkError: () => {
                    setError(
                        'The server could not be reached. Please try again.',
                    );
                },
            }).catch(() => undefined);
        },
        [get],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        loadDetails(show.url(item));

        return cancel;
    }, [cancel, item, loadDetails, open]);

    const detailedItem = details?.item ?? item;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] gap-0 overflow-hidden p-0 sm:max-w-6xl">
                <DialogHeader className="border-b bg-muted/30 p-5 pr-14 sm:p-6 sm:pr-16">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                detailedItem.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {detailedItem.status}
                        </Badge>
                        {detailedItem.deleted_at && (
                            <Badge variant="outline">Archived</Badge>
                        )}
                        {detailedItem.is_low_stock && (
                            <Badge variant="destructive">Low stock</Badge>
                        )}
                        {detailedItem.expiration_status === 'expired' && (
                            <Badge variant="destructive">Expired stock</Badge>
                        )}
                        {detailedItem.expiration_status === 'expiring' && (
                            <Badge
                                variant="outline"
                                className="border-amber-500/50 text-amber-700 dark:text-amber-400"
                            >
                                Expiring soon
                            </Badge>
                        )}
                        <span className="font-mono text-xs text-muted-foreground">
                            {detailedItem.stock_number ?? 'No stock number'}
                        </span>
                    </div>
                    <DialogTitle className="text-xl">
                        {detailedItem.name}
                    </DialogTitle>
                    <DialogDescription>
                        {detailedItem.series_category?.class_category
                            ?.major_category?.name ?? 'Uncategorized'}{' '}
                        / {detailedItem.series_category?.name ?? 'No series'} ·{' '}
                        Complete receipt and release history
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[calc(92vh-9rem)] overflow-y-auto">
                    {!details && processing && <LoadingState />}

                    {error && !details && (
                        <div className="grid place-items-center gap-3 p-10 text-center">
                            <p className="text-sm text-destructive">{error}</p>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setError(null);
                                    loadDetails(show.url(item));
                                }}
                            >
                                Try again
                            </Button>
                        </div>
                    )}

                    {details && (
                        <div className="grid gap-5 p-4 sm:p-6">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div className="rounded-xl border bg-muted/20 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        On hand
                                    </p>
                                    <p className="mt-1 text-lg font-semibold">
                                        {details.item.quantity}{' '}
                                        {details.item.unit_of_measure}
                                    </p>
                                </div>
                                <div className="rounded-xl border bg-primary/5 p-4 ring-1 ring-primary/15">
                                    <p className="text-xs text-muted-foreground">
                                        Current stock value
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-primary">
                                        {currencyFormatter.format(
                                            Number(
                                                details.item.inventory_value,
                                            ),
                                        )}
                                    </p>
                                </div>
                                <div className="rounded-xl border bg-muted/20 p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Next expiration
                                    </p>
                                    <p className="mt-1 text-lg font-semibold">
                                        {details.item.next_expiration_date
                                            ? formatDate(
                                                  details.item
                                                      .next_expiration_date,
                                              )
                                            : 'No expiry'}
                                    </p>
                                </div>
                            </div>

                            <section className="rounded-xl border bg-card">
                                <div className="flex items-center gap-2 border-b bg-muted/30 px-4 py-3">
                                    <PackageSearch className="size-4 text-muted-foreground" />
                                    <h3 className="font-medium">Item master</h3>
                                </div>
                                <dl className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Unit of measure
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {details.item.unit_of_measure}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            UACS object code
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {details.item.uacs_object_code ??
                                                'Not provided'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Accountable person
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {details.item.accountable_reference
                                                ?.name ?? 'Not assigned'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Receipt batches
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {details.item.batches.length}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Reorder point
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {details.item.reorder_point}{' '}
                                            {details.item.unit_of_measure}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-muted-foreground">
                                            Suggested order
                                        </dt>
                                        <dd className="mt-1 text-sm font-medium">
                                            {details.item.reorder_quantity
                                                ? `${details.item.reorder_quantity} ${details.item.unit_of_measure}`
                                                : 'Not configured'}
                                        </dd>
                                    </div>
                                    <div className="sm:col-span-2 lg:col-span-4">
                                        <dt className="text-xs text-muted-foreground">
                                            Description
                                        </dt>
                                        <dd className="mt-1 text-sm">
                                            {details.item.description ??
                                                'Not provided'}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section className="overflow-hidden rounded-xl border bg-card">
                                <div className="flex items-center gap-2 border-b bg-muted/30 px-4 py-3">
                                    <Warehouse className="size-4 text-muted-foreground" />
                                    <div>
                                        <h3 className="font-medium">
                                            Receipt batches
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Cost, expiry, and source are
                                            retained per delivery.
                                        </p>
                                    </div>
                                </div>
                                <div className="overflow-x-auto">
                                    <Table className="w-full min-w-[950px] text-sm [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                        <TableHeader className="border-b bg-muted/20 text-left text-xs text-muted-foreground uppercase">
                                            <TableRow>
                                                <TableHead>Batch</TableHead>
                                                <TableHead>Received</TableHead>
                                                <TableHead>
                                                    Source / reference
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Unit cost
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Received
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Released
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Remaining
                                                </TableHead>
                                                <TableHead>
                                                    Expiration
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody className="divide-y">
                                            {details.item.batches.map(
                                                (batch) => (
                                                    <TableRow
                                                        key={
                                                            batch.inventory_item_batch_id
                                                        }
                                                    >
                                                        <TableCell className="font-medium">
                                                            #
                                                            {batch.batch_number}
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatDate(
                                                                batch.received_at,
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div>
                                                                {batch.source ??
                                                                    'Not provided'}
                                                            </div>
                                                            <div className="font-mono text-xs text-muted-foreground">
                                                                {batch.reference_no ??
                                                                    'No reference'}
                                                            </div>
                                                            {batch.notes && (
                                                                <div className="mt-1 max-w-xs text-xs text-muted-foreground">
                                                                    {
                                                                        batch.notes
                                                                    }
                                                                </div>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {currencyFormatter.format(
                                                                Number(
                                                                    batch.unit_cost,
                                                                ),
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {batch.quantity_in}
                                                        </TableCell>
                                                        <TableCell className="text-right text-muted-foreground">
                                                            {batch.quantity_in -
                                                                batch.quantity_remaining}
                                                        </TableCell>
                                                        <TableCell className="text-right font-semibold">
                                                            {
                                                                batch.quantity_remaining
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {batch.expiration_date
                                                                ? formatDate(
                                                                      batch.expiration_date,
                                                                  )
                                                                : 'No expiry'}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                            {details.item.batches.length ===
                                                0 && (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={8}
                                                        className="py-8 text-center text-muted-foreground"
                                                    >
                                                        No receipt batches have
                                                        been recorded.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>

                            <section className="overflow-hidden rounded-xl border bg-card">
                                <div className="flex items-center justify-between gap-3 border-b bg-muted/30 px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <ReceiptText className="size-4 text-muted-foreground" />
                                        <div>
                                            <h3 className="font-medium">
                                                Release history
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Each issue shows its FIFO batch
                                                cost allocation.
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {details.releases.total} releases
                                    </span>
                                </div>
                                <div className="divide-y">
                                    {details.releases.data.map((release) => (
                                        <article
                                            key={
                                                release.inventory_item_stock_out_id
                                            }
                                            className="grid gap-3 p-4"
                                        >
                                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                                <div>
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {releaseRecipient(
                                                                release,
                                                            )}
                                                        </span>
                                                        {release.ris_no && (
                                                            <Badge variant="outline">
                                                                {release.ris_no}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                        <span className="inline-flex items-center gap-1">
                                                            <CalendarDays className="size-3.5" />
                                                            {formatDate(
                                                                release.stocked_out_at,
                                                            )}
                                                        </span>
                                                        {release.responsibility_center_code && (
                                                            <span>
                                                                Responsibility:{' '}
                                                                {
                                                                    release.responsibility_center_code
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="text-left sm:text-right">
                                                    <div className="font-semibold">
                                                        {release.quantity}{' '}
                                                        {
                                                            details.item
                                                                .unit_of_measure
                                                        }
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {currencyFormatter.format(
                                                            Number(
                                                                release.total_cost,
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap gap-2">
                                                {release.allocations.map(
                                                    (allocation) => (
                                                        <div
                                                            key={
                                                                allocation.inventory_item_stock_out_allocation_id
                                                            }
                                                            className="rounded-lg border bg-muted/20 px-3 py-2 text-xs"
                                                        >
                                                            <span className="font-medium">
                                                                Batch #
                                                                {
                                                                    allocation
                                                                        .batch
                                                                        .batch_number
                                                                }
                                                            </span>{' '}
                                                            ·{' '}
                                                            {
                                                                allocation.quantity
                                                            }{' '}
                                                            units at{' '}
                                                            {currencyFormatter.format(
                                                                Number(
                                                                    allocation.unit_cost,
                                                                ),
                                                            )}
                                                        </div>
                                                    ),
                                                )}
                                                {release.allocations.length ===
                                                    0 && (
                                                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                                        <ClipboardList className="size-3.5" />
                                                        No reconstructed batch
                                                        allocation is available.
                                                    </span>
                                                )}
                                            </div>
                                            {release.notes && (
                                                <p className="text-xs text-muted-foreground">
                                                    {release.notes}
                                                </p>
                                            )}
                                        </article>
                                    ))}
                                    {details.releases.data.length === 0 && (
                                        <div className="p-8 text-center text-sm text-muted-foreground">
                                            No stock releases recorded.
                                        </div>
                                    )}
                                </div>

                                {details.releases.last_page > 1 && (
                                    <div className="flex items-center justify-between gap-3 border-t bg-muted/20 px-4 py-3">
                                        <span className="text-xs text-muted-foreground">
                                            Page {details.releases.current_page}{' '}
                                            of {details.releases.last_page}
                                        </span>
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    processing ||
                                                    !details.releases
                                                        .prev_page_url
                                                }
                                                onClick={() => {
                                                    if (
                                                        details.releases
                                                            .prev_page_url
                                                    ) {
                                                        loadDetails(
                                                            details.releases
                                                                .prev_page_url,
                                                        );
                                                    }
                                                }}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    processing ||
                                                    !details.releases
                                                        .next_page_url
                                                }
                                                onClick={() => {
                                                    if (
                                                        details.releases
                                                            .next_page_url
                                                    ) {
                                                        loadDetails(
                                                            details.releases
                                                                .next_page_url,
                                                        );
                                                    }
                                                }}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </section>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
