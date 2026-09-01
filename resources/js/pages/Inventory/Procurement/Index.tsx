import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, FileCheck2, Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, store, transition } from '@/routes/inventory/procurement';
import { index as requestsIndex } from '@/routes/inventory/requests';
type Item = {
    inventory_item_id: number;
    name: string;
    unit_of_measure: string;
    quantity: number;
    reorder_point: number;
    reorder_quantity: number | null;
};
type Series = { inv_series_cat_id: number; name: string };
type Line = {
    id: number;
    item_name: string;
    unit_of_measure: string;
    quantity: number;
    estimated_unit_cost: string;
    quantity_received: number;
    item: null | { name: string };
};
type Action = {
    id: number;
    action: string;
    to_status: string;
    remarks: string | null;
    created_at: string;
    actor: { name: string };
};
type PR = {
    id: number;
    pr_no: string;
    type: string;
    source: string;
    status: string;
    purpose: string;
    funding_source: string | null;
    ppmp_reference: string | null;
    app_reference: string | null;
    purchase_order_no: string | null;
    inspection_acceptance_no: string | null;
    creator: { name: string };
    lines: Line[];
    actions: Action[];
};
type Page<T> = { data: T[] };
const field = 'rounded-md border bg-background px-3 py-2 text-sm';
export default function ProcurementIndex({
    procurementRequests,
    items,
    seriesCategories,
}: {
    procurementRequests: Page<PR>;
    items: Item[];
    seriesCategories: Series[];
}) {
    const [newItem, setNewItem] = useState(false);
    const [remarks, setRemarks] = useState('');
    const [details, setDetails] = useState({
        procurement_mode: '',
        purchase_order_no: '',
        inspection_acceptance_no: '',
        delivery_reference: '',
        received_at: '',
        actual_unit_cost: '',
    });
    const form = useForm({
        type: 'replenishment',
        source: 'manual',
        purpose: '',
        funding_source: '',
        responsibility_center_code: '',
        ppmp_reference: '',
        app_reference: '',
        app_cse_classification: '',
        required_at: '',
        lines: [
            {
                inventory_item_id: '',
                series_category_id: '',
                item_name: '',
                specifications: '',
                unit_of_measure: '',
                quantity: 1,
                estimated_unit_cost: '',
            },
        ],
    });
    const selected = items.find(
        (i) =>
            String(i.inventory_item_id) ===
            String(form.data.lines[0].inventory_item_id),
    );
    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(store.url(), { onSuccess: () => form.reset() });
    }
    function act(record: PR, action: string) {
        if (
            !window.confirm(
                `I attest that I am authorized to ${action} ${record.pr_no} and its supporting records are accurate.`,
            )
        ) {
            return;
        }

        router.patch(
            transition.url(record.id),
            { action, attested: true, remarks, ...details },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Procurement Controls" />
            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Human-authorized procurement
                        </p>
                        <h1 className="text-3xl font-semibold">
                            Procurement controls
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            PPMP/APP, budget, ordering, delivery, inspection,
                            and acceptance attestations.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        render={<Link href={requestsIndex()} />}
                    >
                        <ArrowLeft />
                        Supply requests
                    </Button>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="size-5" />
                            Prepare purchase request
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-3 md:grid-cols-3">
                                <select
                                    className={field}
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                >
                                    <option value="replenishment">
                                        Replenishment
                                    </option>
                                    <option value="new_item">New item</option>
                                    <option value="mixed">Mixed</option>
                                </select>
                                <select
                                    className={field}
                                    value={form.data.source}
                                    onChange={(e) =>
                                        form.setData('source', e.target.value)
                                    }
                                >
                                    <option value="manual">Manual</option>
                                    <option value="low_stock">
                                        Low-stock recommendation
                                    </option>
                                    <option value="request_shortage">
                                        Request shortage
                                    </option>
                                </select>
                                <input
                                    type="date"
                                    className={field}
                                    value={form.data.required_at}
                                    onChange={(e) =>
                                        form.setData(
                                            'required_at',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <textarea
                                required
                                className={field}
                                placeholder="Procurement purpose"
                                value={form.data.purpose}
                                onChange={(e) =>
                                    form.setData('purpose', e.target.value)
                                }
                            />
                            <div className="grid gap-3 md:grid-cols-4">
                                <input
                                    className={field}
                                    placeholder="Funding source"
                                    value={form.data.funding_source}
                                    onChange={(e) =>
                                        form.setData(
                                            'funding_source',
                                            e.target.value,
                                        )
                                    }
                                />
                                <input
                                    className={field}
                                    placeholder="PPMP reference"
                                    value={form.data.ppmp_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'ppmp_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                <input
                                    className={field}
                                    placeholder="APP / updated APP reference"
                                    value={form.data.app_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'app_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                <input
                                    className={field}
                                    placeholder="APP-CSE classification"
                                    value={form.data.app_cse_classification}
                                    onChange={(e) =>
                                        form.setData(
                                            'app_cse_classification',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={newItem}
                                    onChange={(e) => {
                                        setNewItem(e.target.checked);
                                        form.setData(
                                            'type',
                                            e.target.checked
                                                ? 'new_item'
                                                : 'replenishment',
                                        );
                                        form.setData('lines', [
                                            {
                                                ...form.data.lines[0],
                                                inventory_item_id: '',
                                            },
                                        ]);
                                    }}
                                />
                                Procure an entirely new catalog item
                            </label>
                            <div className="grid gap-3 md:grid-cols-5">
                                {newItem ? (
                                    <>
                                        <input
                                            required
                                            className={field}
                                            placeholder="New item name"
                                            value={form.data.lines[0].item_name}
                                            onChange={(e) =>
                                                form.setData('lines', [
                                                    {
                                                        ...form.data.lines[0],
                                                        item_name:
                                                            e.target.value,
                                                    },
                                                ])
                                            }
                                        />
                                        <select
                                            required
                                            className={field}
                                            value={
                                                form.data.lines[0]
                                                    .series_category_id
                                            }
                                            onChange={(e) =>
                                                form.setData('lines', [
                                                    {
                                                        ...form.data.lines[0],
                                                        series_category_id:
                                                            e.target.value,
                                                    },
                                                ])
                                            }
                                        >
                                            <option value="">
                                                Category on acceptance
                                            </option>
                                            {seriesCategories.map((s) => (
                                                <option
                                                    key={s.inv_series_cat_id}
                                                    value={s.inv_series_cat_id}
                                                >
                                                    {s.name}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            required
                                            className={field}
                                            placeholder="Unit"
                                            value={
                                                form.data.lines[0]
                                                    .unit_of_measure
                                            }
                                            onChange={(e) =>
                                                form.setData('lines', [
                                                    {
                                                        ...form.data.lines[0],
                                                        unit_of_measure:
                                                            e.target.value,
                                                    },
                                                ])
                                            }
                                        />
                                    </>
                                ) : (
                                    <select
                                        required
                                        className={field}
                                        value={
                                            form.data.lines[0].inventory_item_id
                                        }
                                        onChange={(e) => {
                                            const item = items.find(
                                                (i) =>
                                                    String(
                                                        i.inventory_item_id,
                                                    ) === e.target.value,
                                            );
                                            form.setData('lines', [
                                                {
                                                    ...form.data.lines[0],
                                                    inventory_item_id:
                                                        e.target.value,
                                                    item_name: item?.name ?? '',
                                                    unit_of_measure:
                                                        item?.unit_of_measure ??
                                                        '',
                                                },
                                            ]);
                                        }}
                                    >
                                        <option value="">Existing item</option>
                                        {items.map((i) => (
                                            <option
                                                key={i.inventory_item_id}
                                                value={i.inventory_item_id}
                                            >
                                                {i.name} — {i.quantity} on hand
                                            </option>
                                        ))}
                                    </select>
                                )}
                                <input
                                    type="number"
                                    min="1"
                                    required
                                    className={field}
                                    value={form.data.lines[0].quantity}
                                    onChange={(e) =>
                                        form.setData('lines', [
                                            {
                                                ...form.data.lines[0],
                                                quantity: Number(
                                                    e.target.value,
                                                ),
                                            },
                                        ])
                                    }
                                />
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    className={field}
                                    placeholder="Estimated unit cost"
                                    value={
                                        form.data.lines[0].estimated_unit_cost
                                    }
                                    onChange={(e) =>
                                        form.setData('lines', [
                                            {
                                                ...form.data.lines[0],
                                                estimated_unit_cost:
                                                    e.target.value,
                                            },
                                        ])
                                    }
                                />
                            </div>
                            {selected && (
                                <p className="text-xs text-muted-foreground">
                                    Suggested reorder:{' '}
                                    {selected.reorder_quantity ??
                                        'not configured'}{' '}
                                    {selected.unit_of_measure}.
                                </p>
                            )}
                            <div>
                                {Object.values(form.errors).map((error, i) => (
                                    <p
                                        key={i}
                                        className="text-sm text-destructive"
                                    >
                                        {String(error)}
                                    </p>
                                ))}
                            </div>
                            <Button
                                className="w-fit"
                                disabled={form.processing}
                            >
                                Prepare draft PR
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Decision inputs</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 md:grid-cols-3">
                        <input
                            className={field}
                            placeholder="Decision remarks"
                            value={remarks}
                            onChange={(e) => setRemarks(e.target.value)}
                        />
                        <input
                            className={field}
                            placeholder="Procurement mode"
                            value={details.procurement_mode}
                            onChange={(e) =>
                                setDetails({
                                    ...details,
                                    procurement_mode: e.target.value,
                                })
                            }
                        />
                        <input
                            className={field}
                            placeholder="Purchase order no."
                            value={details.purchase_order_no}
                            onChange={(e) =>
                                setDetails({
                                    ...details,
                                    purchase_order_no: e.target.value,
                                })
                            }
                        />
                        <input
                            className={field}
                            placeholder="Delivery reference"
                            value={details.delivery_reference}
                            onChange={(e) =>
                                setDetails({
                                    ...details,
                                    delivery_reference: e.target.value,
                                })
                            }
                        />
                        <input
                            type="date"
                            className={field}
                            value={details.received_at}
                            onChange={(e) =>
                                setDetails({
                                    ...details,
                                    received_at: e.target.value,
                                })
                            }
                        />
                        <input
                            className={field}
                            placeholder="IAR / inspection acceptance no."
                            value={details.inspection_acceptance_no}
                            onChange={(e) =>
                                setDetails({
                                    ...details,
                                    inspection_acceptance_no: e.target.value,
                                })
                            }
                        />
                    </CardContent>
                </Card>
                <div className="grid gap-4">
                    {procurementRequests.data.map((record) => (
                        <Card key={record.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <FileCheck2 className="size-5" />
                                        {record.pr_no}
                                    </CardTitle>
                                    <span className="rounded-full border px-2 py-1 text-xs">
                                        {record.status.replaceAll('_', ' ')}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <p className="text-sm">
                                    <b>Prepared by:</b> {record.creator.name} ·{' '}
                                    <b>Purpose:</b> {record.purpose}
                                </p>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left">
                                                <th className="py-2">Item</th>
                                                <th>Quantity</th>
                                                <th>Estimated cost</th>
                                                <th>Received</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {record.lines.map((line) => (
                                                <tr
                                                    className="border-b"
                                                    key={line.id}
                                                >
                                                    <td className="py-2">
                                                        {line.item_name}
                                                    </td>
                                                    <td>
                                                        {line.quantity}{' '}
                                                        {line.unit_of_measure}
                                                    </td>
                                                    <td>
                                                        ₱
                                                        {(
                                                            Number(
                                                                line.estimated_unit_cost,
                                                            ) * line.quantity
                                                        ).toLocaleString()}
                                                    </td>
                                                    <td>
                                                        {line.quantity_received}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {record.status === 'draft' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(record, 'submit')
                                            }
                                        >
                                            Submit
                                        </Button>
                                    )}
                                    {record.status === 'for_budget_review' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(record, 'budget_review')
                                            }
                                        >
                                            Certify budget & plan
                                        </Button>
                                    )}
                                    {record.status === 'for_approval' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(record, 'approve')
                                            }
                                        >
                                            Approve PR
                                        </Button>
                                    )}
                                    {record.status === 'approved' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(record, 'forward')
                                            }
                                        >
                                            Forward to procurement
                                        </Button>
                                    )}
                                    {record.status ===
                                        'forwarded_to_procurement' && (
                                        <Button
                                            size="sm"
                                            onClick={() => act(record, 'order')}
                                        >
                                            Record order
                                        </Button>
                                    )}
                                    {['ordered', 'delivered'].includes(
                                        record.status,
                                    ) && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(record, 'record_delivery')
                                            }
                                        >
                                            Record delivery
                                        </Button>
                                    )}
                                    {record.status === 'delivered' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                act(record, 'accept')
                                            }
                                        >
                                            Inspect & accept into stock
                                        </Button>
                                    )}
                                    {![
                                        'accepted',
                                        'rejected',
                                        'cancelled',
                                    ].includes(record.status) && (
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                act(record, 'reject')
                                            }
                                        >
                                            Reject
                                        </Button>
                                    )}
                                </div>
                                <details>
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Attested decision history (
                                        {record.actions.length})
                                    </summary>
                                    <div className="mt-2 grid gap-2">
                                        {record.actions.map((a) => (
                                            <div
                                                key={a.id}
                                                className="rounded-md border p-2 text-xs"
                                            >
                                                <b>{a.actor.name}</b> —{' '}
                                                {a.action} → {a.to_status} ·{' '}
                                                {new Date(
                                                    a.created_at,
                                                ).toLocaleString()}
                                                {a.remarks && (
                                                    <p>{a.remarks}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </details>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
ProcurementIndex.layout = {
    breadcrumbs: [{ title: 'Procurement Controls', href: index() }],
};
