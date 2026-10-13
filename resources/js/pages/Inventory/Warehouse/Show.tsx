import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, label, store } from '@/routes/inventory/warehouse';
import { date, location, time } from './types';
import type { Batch } from './types';
function PlacementForm({ batch }: { batch: Batch }) {
    const current = batch.latest_placement;
    const form = useForm({
        latest_placement_id: current?.id ?? null,
        warehouse: current?.warehouse ?? '',
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
            className="space-y-4 rounded-lg border p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(store.url(batch.inventory_item_batch_id));
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
            <div className="grid gap-4 sm:grid-cols-2">
                {(['warehouse', 'area', 'rack', 'bin'] as const).map(
                    (field) => (
                        <div key={field} className="space-y-2">
                            <Label htmlFor={field}>
                                {field === 'rack'
                                    ? 'Shelf / rack (optional)'
                                    : field === 'bin'
                                      ? 'Bin (optional)'
                                      : field === 'warehouse'
                                        ? 'Warehouse'
                                        : 'Area / material classification'}
                            </Label>
                            <Input
                                id={field}
                                required={
                                    field === 'warehouse' || field === 'area'
                                }
                                maxLength={
                                    field === 'warehouse' || field === 'area'
                                        ? 150
                                        : 100
                                }
                                value={form.data[field]}
                                onChange={(event) =>
                                    form.setData(field, event.target.value)
                                }
                            />
                            <InputError message={form.errors[field]} />
                        </div>
                    ),
                )}
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
                        key === 'latest_placement_id' || key === 'quantity',
                )
                .map(([key, error]) => (
                    <InputError key={key} message={error} />
                ))}
            <Button
                type="submit"
                disabled={form.processing || !form.data.attested}
            >
                {form.processing ? 'Recording…' : 'Record storage confirmation'}
            </Button>
        </form>
    );
}
export default function WarehouseShow({ batch }: { batch: Batch }) {
    const canPlace =
        batch.quantity_remaining > 0 &&
        !batch.item.deleted_at &&
        batch.item.status === 'active';

    return (
        <>
            <Head title={'Warehouse batch ' + batch.batch_number} />
            <div className="space-y-6 p-4 sm:p-6">
                <Link href={index()} className="text-sm underline">
                    Warehouse storage
                </Link>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">
                        {batch.item.stock_number} / Batch {batch.batch_number}
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
                        Source: {batch.reference_no ?? 'No original reference'}{' '}
                        / {batch.source ?? 'Not specified'}
                    </p>
                    <p className="text-sm">
                        Posted receipt date: {date(batch.received_at)} / Expiry:{' '}
                        {date(batch.expiration_date)}
                    </p>
                    <p className="text-sm">
                        Current location: {location(batch.latest_placement)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        A location confirmation records placement only. It does
                        not approve issuance, establish fitness for use, or
                        change stock balances.
                    </p>
                </section>
                {canPlace ? (
                    <PlacementForm
                        key={batch.latest_placement?.id ?? 'unplaced'}
                        batch={batch}
                    />
                ) : (
                    <p className="rounded-lg border p-4 text-muted-foreground">
                        Placement cannot be changed for depleted batches or
                        inactive/archived catalog items. Prior confirmations
                        remain available below.
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
                                    Observed: {entry.observed_quantity} /
                                    Evidence: {entry.source_evidence_reference}
                                </p>
                                <p className="text-sm">
                                    Source item:{' '}
                                    {entry.source_details.stock_number} /{' '}
                                    {entry.source_details.item_name} /{' '}
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
        </>
    );
}
WarehouseShow.layout = {
    breadcrumbs: [{ title: 'Warehouse Storage', href: index.url() }],
};
