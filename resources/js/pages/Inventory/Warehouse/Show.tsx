import { Head, Link, useForm } from '@inertiajs/react';
import { ConsumableInventoryNav } from '@/components/consumable-inventory-nav';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { RecordChangeBadge } from '@/components/record-change-badge';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index, label, store } from '@/routes/inventory/warehouse';
import { index as registryIndex } from '@/routes/inventory/warehouse/registry';
import { ManageStockroomsDialog } from './Registry';
import { date, location, time } from './types';
import type { Batch, Warehouse } from './types';
function PlacementForm({
    batch,
    warehouses,
    onSaved,
    onSaving,
    embedded = false,
}: {
    batch: Batch;
    warehouses: Warehouse[];
    onSaved?: () => void;
    onSaving?: (saving: boolean) => void;
    embedded?: boolean;
}) {
    const current = batch.latest_placement;
    const form = useForm({
        latest_placement_id: current?.id ?? null,
        warehouse_id: current?.warehouse_id ? String(current.warehouse_id) : '',
        area: current?.area ?? '',
        rack: current?.rack ?? '',
        bin: current?.bin ?? '',
        observed_quantity: String(batch.quantity_remaining),
        source_evidence_reference: '',
        remarks: '',
        attested: false,
    });

    return (
        <form
            noValidate
            className="space-y-4 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(store.url(batch.inventory_item_batch_id), {
                    preserveScroll: true,
                    onStart: () => onSaving?.(true),
                    onFinish: () => onSaving?.(false),
                    onSuccess: () => onSaved?.(),
                });
            }}
        >
            <h2 className="text-lg font-semibold">
                {current
                    ? 'Record a new placement or correction'
                    : 'Confirm put-away'}
            </h2>
            <p className="text-sm text-muted-foreground">
                Check the physical quantity and description against the posted
                RR, RMS, or other approved source evidence. A discrepancy must
                be resolved through its source procedure before confirmation.
            </p>
            <div className="grid gap-2">
                <Label htmlFor="warehouse-id">Stockroom</Label>
                <FormSelect
                    id="warehouse-id"
                    value={form.data.warehouse_id}
                    aria-invalid={!!form.errors.warehouse_id}
                    onChange={(event) =>
                        form.setData('warehouse_id', event.target.value)
                    }
                >
                    <option value="">Select a registered stockroom</option>
                    {warehouses.map((warehouse) => (
                        <option key={warehouse.id} value={warehouse.id}>
                            {warehouse.name}
                        </option>
                    ))}
                </FormSelect>
                <InputError message={form.errors.warehouse_id} />
                {embedded ? (
                    <ManageStockroomsDialog />
                ) : (
                    <Link href={registryIndex()} className="text-sm underline">
                        Manage stockrooms
                    </Link>
                )}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                {(['area', 'rack', 'bin'] as const).map((field) => (
                    <div key={field} className="space-y-2">
                        <Label htmlFor={field}>
                            {field === 'rack'
                                ? 'Shelf / rack (optional)'
                                : field === 'bin'
                                  ? 'Bin (optional)'
                                  : 'Area / material classification'}
                        </Label>
                        <Input
                            id={field}
                            required={field === 'area'}
                            maxLength={field === 'area' ? 150 : 100}
                            value={form.data[field]}
                            onChange={(event) =>
                                form.setData(field, event.target.value)
                            }
                        />
                        <InputError message={form.errors[field]} />
                    </div>
                ))}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="observed-quantity">
                        Observed remaining batch quantity
                    </Label>
                    <Input
                        id="observed-quantity"
                        type="number"
                        min={1}
                        step={1}
                        required
                        value={form.data.observed_quantity}
                        onChange={(event) =>
                            form.setData(
                                'observed_quantity',
                                event.target.value,
                            )
                        }
                    />
                    <InputError message={form.errors.observed_quantity} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="source-evidence">
                        Signed source / counter-check evidence
                    </Label>
                    <Input
                        id="source-evidence"
                        required
                        maxLength={255}
                        value={form.data.source_evidence_reference}
                        onChange={(event) =>
                            form.setData(
                                'source_evidence_reference',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        message={form.errors.source_evidence_reference}
                    />
                </div>
            </div>
            <div className="space-y-2">
                <Label htmlFor="placement-remarks">
                    Counter-check result / reason for move or correction
                </Label>
                <textarea
                    id="placement-remarks"
                    required
                    maxLength={3000}
                    className="min-h-24 w-full rounded-md border bg-background p-3 text-sm"
                    value={form.data.remarks}
                    onChange={(event) =>
                        form.setData('remarks', event.target.value)
                    }
                />
                <InputError message={form.errors.remarks} />
            </div>
            <label className="flex items-start gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.attested}
                    onChange={(event) =>
                        form.setData('attested', event.target.checked)
                    }
                />
                I checked the physical batch and supporting source records and
                confirm this storage location.
            </label>
            <InputError message={form.errors.attested} />
            {Object.entries(form.errors)
                .filter(
                    ([key]) =>
                        key === 'latest_placement_id' ||
                        key === 'quantity' ||
                        key === 'warehouse',
                )
                .map(([key, error]) => (
                    <InputError key={key} message={error} />
                ))}
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Recording…' : 'Record storage confirmation'}
            </Button>
        </form>
    );
}
export type BatchStorageProps = { batch: Batch; warehouses: Warehouse[] };

export function BatchStorageContent({
    batch,
    warehouses,
    embedded = false,
    onSaved,
    onSaving,
}: BatchStorageProps & {
    embedded?: boolean;
    onSaved?: () => void;
    onSaving?: (saving: boolean) => void;
}) {
    const canPlace =
        batch.quantity_remaining > 0 &&
        !batch.item.deleted_at &&
        batch.item.status === 'active';

    return (
        <div className="grid gap-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">
                    {batch.item.stock_number} / Batch {batch.batch_number}
                    <RecordChangeBadge collection="batch" record={batch} />
                </h1>
                <a
                    href={label.url(batch.inventory_item_batch_id)}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-md border px-4 py-2 text-sm"
                >
                    Print batch label
                </a>
            </div>
            <section className="space-y-2 rounded-lg border p-4">
                <h2 className="text-lg font-medium">{batch.item.name}</h2>
                <p>
                    {batch.quantity_remaining} {batch.item.unit_of_measure}{' '}
                    remaining from {batch.quantity_in} received.
                </p>
                <p className="text-sm">
                    Source: {batch.reference_no ?? 'No original reference'} /{' '}
                    {batch.source ?? 'Not specified'}
                </p>
                <p className="text-sm">
                    Posted receipt date: {date(batch.received_at)} / Expiry:{' '}
                    {date(batch.expiration_date)}
                </p>
                <p className="text-sm">
                    Current location: {location(batch.latest_placement)}
                </p>
                <p className="text-sm text-muted-foreground">
                    A location confirmation records placement only. It does not
                    approve issuance, establish fitness for use, or change stock
                    balances.
                </p>
            </section>
            {canPlace ? (
                <PlacementForm
                    key={batch.latest_placement?.id ?? 'unplaced'}
                    batch={batch}
                    warehouses={warehouses}
                    embedded={embedded}
                    onSaved={onSaved}
                    onSaving={onSaving}
                />
            ) : (
                <p className="rounded-lg border p-4 text-muted-foreground">
                    Placement cannot be changed for depleted batches or
                    inactive/archived catalog items. Prior confirmations remain
                    available below.
                </p>
            )}
            <section className="space-y-3">
                <h2 className="text-lg font-semibold">
                    Storage confirmation history
                </h2>
                {batch.placements?.length ? (
                    batch.placements.map((entry) => (
                        <article
                            key={entry.id}
                            className="space-y-2 rounded-lg border p-4"
                        >
                            <p className="font-medium">
                                Placement {entry.version}: {location(entry)}
                            </p>
                            <p className="text-sm">
                                {entry.recorded_by_name} /{' '}
                                {time(entry.created_at)}
                            </p>
                            <p className="text-sm">
                                Observed: {entry.observed_quantity} / Evidence:{' '}
                                {entry.source_evidence_reference}
                            </p>
                            <p className="text-sm">
                                Source item: {entry.source_details.stock_number}{' '}
                                / {entry.source_details.item_name} /{' '}
                                {entry.source_details.source_reference ??
                                    'No original reference'}
                            </p>
                            <p className="text-sm whitespace-pre-wrap">
                                {entry.remarks}
                            </p>
                        </article>
                    ))
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No storage confirmation has been recorded.
                    </p>
                )}
            </section>
        </div>
    );
}

export default function WarehouseShow(props: BatchStorageProps) {
    return (
        <>
            <Head title={'Stockroom batch ' + props.batch.batch_number} />
            <PageContainer>
                <ConsumableInventoryNav active="storage" />
                <Link href={index()} className="text-sm underline">
                    Stockroom storage
                </Link>
                <BatchStorageContent {...props} />
            </PageContainer>
        </>
    );
}
WarehouseShow.layout = {
    breadcrumbs: [
        { title: 'Consumable inventory', href: itemsIndex() },
        { title: 'Storage & Batches', href: index() },
    ],
};
