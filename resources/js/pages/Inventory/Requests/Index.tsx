import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardList, Plus, ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as procurementIndex } from '@/routes/inventory/procurement';
import { index, store, transition } from '@/routes/inventory/requests';
type Item = {
    inventory_item_id: number;
    name: string;
    stock_number: string;
    unit_of_measure: string;
    quantity: number;
    reorder_point: number;
};
type Line = {
    id: number;
    item_name: string;
    unit_of_measure: string;
    quantity_requested: number;
    quantity_approved: number;
    quantity_reserved: number;
    quantity_released: number;
    is_new_item: boolean;
};
type Action = {
    id: number;
    action: string;
    to_status: string;
    remarks: string | null;
    created_at: string;
    actor: { name: string };
};
type RequestRecord = {
    id: number;
    ris_no: string;
    requester_name: string;
    office_name: string | null;
    purpose: string;
    status: string;
    submitted_at: string;
    lines: Line[];
    actions: Action[];
};
type Page<T> = { data: T[]; current_page: number; last_page: number };
const field = 'rounded-md border bg-background px-3 py-2 text-sm';
export default function RequestsIndex({
    requests,
    items,
    canManage,
}: {
    requests: Page<RequestRecord>;
    items: Item[];
    canManage: boolean;
}) {
    const [newItem, setNewItem] = useState(false);
    const [remarks, setRemarks] = useState('');
    const form = useForm({
        office_name: '',
        responsibility_center_code: '',
        purpose: '',
        date_needed: '',
        lines: [
            {
                inventory_item_id: '',
                is_new_item: false,
                item_name: '',
                specifications: '',
                unit_of_measure: '',
                quantity: 1,
                estimated_unit_cost: '',
                justification: '',
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
    function act(record: RequestRecord, action: string) {
        if (
            !window.confirm(
                `I confirm I am authorized to ${action} ${record.ris_no} and the record is accurate.`,
            )
        ) {
            return;
        }

        router.patch(
            transition.url(record.id),
            { action, attested: true, remarks },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Supply Requests" />
            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Digitized RIS controls
                        </p>
                        <h1 className="text-3xl font-semibold">
                            Supply requests
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Human-authorized requests, reservations, releases,
                            and audit history.
                        </p>
                    </div>
                    {canManage && (
                        <Button render={<Link href={procurementIndex()} />}>
                            {' '}
                            <ShoppingCart /> Procurement controls
                        </Button>
                    )}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="size-5" />
                            Submit a request
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-3 md:grid-cols-3">
                                <input
                                    className={field}
                                    placeholder="Office / division"
                                    value={form.data.office_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'office_name',
                                            e.target.value,
                                        )
                                    }
                                />
                                <input
                                    className={field}
                                    placeholder="Responsibility center"
                                    value={form.data.responsibility_center_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'responsibility_center_code',
                                            e.target.value,
                                        )
                                    }
                                />
                                <input
                                    type="date"
                                    className={field}
                                    value={form.data.date_needed}
                                    onChange={(e) =>
                                        form.setData(
                                            'date_needed',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <textarea
                                required
                                className={field}
                                placeholder="Official purpose"
                                value={form.data.purpose}
                                onChange={(e) =>
                                    form.setData('purpose', e.target.value)
                                }
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={newItem}
                                    onChange={(e) => {
                                        setNewItem(e.target.checked);
                                        form.setData('lines', [
                                            {
                                                ...form.data.lines[0],
                                                is_new_item: e.target.checked,
                                                inventory_item_id: '',
                                            },
                                        ]);
                                    }}
                                />
                                This is an entirely new supply item
                            </label>
                            <div className="grid gap-3 md:grid-cols-4">
                                {newItem ? (
                                    <>
                                        <input
                                            required
                                            className={field}
                                            placeholder="Proposed item name"
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
                                        <input
                                            required
                                            className={field}
                                            placeholder="Unit (pc, box, ream)"
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
                                        onChange={(e) =>
                                            form.setData('lines', [
                                                {
                                                    ...form.data.lines[0],
                                                    inventory_item_id:
                                                        e.target.value,
                                                },
                                            ])
                                        }
                                    >
                                        <option value="">
                                            Select stocked item
                                        </option>
                                        {items.map((item) => (
                                            <option
                                                key={item.inventory_item_id}
                                                value={item.inventory_item_id}
                                            >
                                                {item.name} ({item.quantity}{' '}
                                                available)
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
                            {newItem && (
                                <>
                                    <textarea
                                        required
                                        className={field}
                                        placeholder="Specifications"
                                        value={
                                            form.data.lines[0].specifications
                                        }
                                        onChange={(e) =>
                                            form.setData('lines', [
                                                {
                                                    ...form.data.lines[0],
                                                    specifications:
                                                        e.target.value,
                                                },
                                            ])
                                        }
                                    />
                                    <textarea
                                        required
                                        className={field}
                                        placeholder="Justification and why no existing equivalent is suitable"
                                        value={form.data.lines[0].justification}
                                        onChange={(e) =>
                                            form.setData('lines', [
                                                {
                                                    ...form.data.lines[0],
                                                    justification:
                                                        e.target.value,
                                                },
                                            ])
                                        }
                                    />
                                </>
                            )}
                            {selected && (
                                <p className="text-xs text-muted-foreground">
                                    Current stock: {selected.quantity}{' '}
                                    {selected.unit_of_measure}; reorder point:{' '}
                                    {selected.reorder_point}.
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
                                Submit and attest
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                {canManage && (
                    <input
                        className={field}
                        placeholder="Remarks for the next decision (required by policy when applicable)"
                        value={remarks}
                        onChange={(e) => setRemarks(e.target.value)}
                    />
                )}
                <div className="grid gap-4">
                    {requests.data.map((record) => (
                        <Card key={record.id}>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList className="size-5" />
                                        {record.ris_no}
                                    </CardTitle>
                                    <span className="rounded-full border px-2 py-1 text-xs font-medium">
                                        {record.status.replaceAll('_', ' ')}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-1 text-sm">
                                    <p>
                                        <b>Requester:</b>{' '}
                                        {record.requester_name}
                                        {record.office_name
                                            ? ` — ${record.office_name}`
                                            : ''}
                                    </p>
                                    <p>
                                        <b>Purpose:</b> {record.purpose}
                                    </p>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left">
                                                <th className="py-2">Item</th>
                                                <th>Requested</th>
                                                <th>Approved</th>
                                                <th>Reserved</th>
                                                <th>Released</th>
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
                                                        {line.is_new_item &&
                                                            ' (new item)'}
                                                    </td>
                                                    <td>
                                                        {
                                                            line.quantity_requested
                                                        }
                                                    </td>
                                                    <td>
                                                        {line.quantity_approved}
                                                    </td>
                                                    <td>
                                                        {line.quantity_reserved}
                                                    </td>
                                                    <td>
                                                        {line.quantity_released}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {canManage && (
                                    <div className="flex flex-wrap gap-2">
                                        {record.status === 'submitted' && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    act(record, 'approve')
                                                }
                                            >
                                                Approve
                                            </Button>
                                        )}
                                        {[
                                            'approved',
                                            'awaiting_replenishment',
                                        ].includes(record.status) && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    act(record, 'review')
                                                }
                                            >
                                                Review & reserve
                                            </Button>
                                        )}
                                        {[
                                            'ready_for_release',
                                            'awaiting_replenishment',
                                            'partially_released',
                                        ].includes(record.status) && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    act(record, 'release')
                                                }
                                            >
                                                Release reserved stock
                                            </Button>
                                        )}
                                        {![
                                            'released',
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
                                )}
                                <details>
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Decision history (
                                        {record.actions.length})
                                    </summary>
                                    <div className="mt-2 grid gap-2">
                                        {record.actions.map((action) => (
                                            <div
                                                key={action.id}
                                                className="rounded-md border p-2 text-xs"
                                            >
                                                <b>{action.actor.name}</b> —{' '}
                                                {action.action} →{' '}
                                                {action.to_status} ·{' '}
                                                {new Date(
                                                    action.created_at,
                                                ).toLocaleString()}
                                                {action.remarks && (
                                                    <p>{action.remarks}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </details>
                            </CardContent>
                        </Card>
                    ))}
                    {requests.data.length === 0 && (
                        <p className="text-center text-muted-foreground">
                            No supply requests yet.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
RequestsIndex.layout = {
    breadcrumbs: [{ title: 'Supply Requests', href: index() }],
};
