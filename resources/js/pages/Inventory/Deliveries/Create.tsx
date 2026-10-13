import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/inventory/deliveries';
import { areaClass, localReceiptTime } from './types';
import type { Order } from './types';
type IntakeLine = {
    line_id: number;
    quantity_delivered: string;
    unit_cost: string;
    expiration_date: string;
};
export default function DeliveryCreate({
    orders,
    selectedOrderId,
}: {
    orders: Order[];
    selectedOrderId: number | null;
}) {
    const form = useForm({
        procurement_request_id: selectedOrderId ? String(selectedOrderId) : '',
        idempotency_key: crypto.randomUUID(),
        supplier_name: '',
        invoice_number: '',
        delivery_reference: '',
        received_at: localReceiptTime(),
        receiving_department: '',
        noa_reference: '',
        ntp_reference: '',
        order_document_basis: '',
        notification_reference: '',
        packaging_findings: '',
        lines: [] as IntakeLine[],
        attested: false,
    });
    const order = orders.find(
        (order) => order.id === Number(form.data.procurement_request_id),
    );
    const updateLine = (
        id: number,
        field: 'quantity_delivered' | 'unit_cost' | 'expiration_date',
        value: string,
    ) =>
        form.setData(
            'lines',
            form.data.lines.map((line) =>
                line.line_id === id ? { ...line, [field]: value } : line,
            ),
        );

    return (
        <>
            <Head title="Record shipment" />
            <div className="space-y-6 p-4 sm:p-6">
                <Link href={index()} className="text-sm underline">
                    Delivery inspection
                </Link>
                <h1 className="text-2xl font-semibold">
                    Record a delivered shipment
                </h1>
                <p className="text-sm text-muted-foreground">
                    Record physically delivered quantities. Inspection and
                    signed acceptance must precede stock posting. Manila local
                    time is used for receipt.
                </p>
                <form
                    className="space-y-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            lines: data.lines.map((line) => ({
                                ...line,
                                expiration_date: line.expiration_date || null,
                            })),
                        }));
                        form.post(store.url());
                    }}
                >
                    <section className="space-y-3 rounded-lg border p-4">
                        <Label htmlFor="delivery-order">
                            Recorded purchase order
                        </Label>
                        <FormSelect
                            id="delivery-order"
                            required
                            value={form.data.procurement_request_id}
                            onChange={(e) => {
                                form.setData(
                                    'procurement_request_id',
                                    e.target.value,
                                );
                                form.setData('lines', []);
                            }}
                        >
                            <option value="">Select PO / PR</option>
                            {orders.map((order) => (
                                <option value={order.id} key={order.id}>
                                    {order.purchase_order_no} / {order.pr_no}
                                </option>
                            ))}
                        </FormSelect>
                        {!orders.length && (
                            <p className="text-sm text-muted-foreground">
                                Record a purchase order before receiving a
                                shipment.
                            </p>
                        )}
                    </section>
                    {order && (
                        <section className="space-y-3 rounded-lg border p-4">
                            <h2 className="font-semibold">
                                Materials physically delivered
                            </h2>
                            {order.lines.map((line) => {
                                const selected = form.data.lines.find(
                                    (input) => input.line_id === line.id,
                                );

                                return (
                                    <article
                                        key={line.id}
                                        className="space-y-3 rounded-lg border p-3"
                                    >
                                        <label className="flex items-start gap-2 text-sm font-medium">
                                            <input
                                                type="checkbox"
                                                disabled={
                                                    !selected &&
                                                    line.available_quantity ===
                                                        0
                                                }
                                                checked={!!selected}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'lines',
                                                        e.target.checked
                                                            ? [
                                                                  ...form.data
                                                                      .lines,
                                                                  {
                                                                      line_id:
                                                                          line.id,
                                                                      quantity_delivered:
                                                                          '1',
                                                                      unit_cost:
                                                                          line.unit_cost,
                                                                      expiration_date:
                                                                          '',
                                                                  },
                                                              ]
                                                            : form.data.lines.filter(
                                                                  (input) =>
                                                                      input.line_id !==
                                                                      line.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            {line.item_name} /{' '}
                                            {line.unit_of_measure} /{' '}
                                            {line.item_kind} / Remaining:{' '}
                                            {line.available_quantity}
                                        </label>
                                        <p className="text-xs text-muted-foreground">
                                            {line.specifications}
                                        </p>
                                        {selected && (
                                            <div className="grid gap-3 sm:grid-cols-3">
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`delivered-${line.id}`}
                                                    >
                                                        Delivered quantity
                                                    </Label>
                                                    <Input
                                                        id={`delivered-${line.id}`}
                                                        type="number"
                                                        min="1"
                                                        max={
                                                            line.available_quantity
                                                        }
                                                        required
                                                        value={
                                                            selected.quantity_delivered
                                                        }
                                                        onChange={(e) =>
                                                            updateLine(
                                                                line.id,
                                                                'quantity_delivered',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`cost-${line.id}`}
                                                    >
                                                        Actual unit cost
                                                    </Label>
                                                    <Input
                                                        id={`cost-${line.id}`}
                                                        type="number"
                                                        min="0.01"
                                                        max="9999999999.99"
                                                        step="0.01"
                                                        required
                                                        value={
                                                            selected.unit_cost
                                                        }
                                                        onChange={(e) =>
                                                            updateLine(
                                                                line.id,
                                                                'unit_cost',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label
                                                        htmlFor={`expiry-${line.id}`}
                                                    >
                                                        Expiry date (optional)
                                                    </Label>
                                                    <DatePicker
                                                        id={`expiry-${line.id}`}
                                                        value={
                                                            selected.expiration_date
                                                        }
                                                        onChange={(value) =>
                                                            updateLine(
                                                                line.id,
                                                                'expiration_date',
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </article>
                                );
                            })}
                        </section>
                    )}
                    <section className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                        {(
                            [
                                ['supplier_name', 'Supplier name', true],
                                [
                                    'invoice_number',
                                    'Supplier invoice number',
                                    true,
                                ],
                                [
                                    'delivery_reference',
                                    'Delivery receipt / note number',
                                    true,
                                ],
                                [
                                    'received_at',
                                    'Physical receipt date and time',
                                    true,
                                ],
                                [
                                    'receiving_department',
                                    'Receiving department / end user',
                                    true,
                                ],
                                [
                                    'noa_reference',
                                    'Notice of Award reference (if applicable)',
                                    false,
                                ],
                                [
                                    'ntp_reference',
                                    'Notice to Proceed reference (if applicable)',
                                    false,
                                ],
                                [
                                    'notification_reference',
                                    'End-user and inspection-team notification reference',
                                    true,
                                ],
                            ] as const
                        ).map(([key, label, required]) => (
                            <div className="space-y-2" key={key}>
                                <Label htmlFor={key}>{label}</Label>
                                {key === 'received_at' ? (
                                    <DatePicker
                                        includeTime
                                        id={key}
                                        required={required}
                                        max={localReceiptTime()}
                                        value={form.data[key]}
                                        onChange={(value) =>
                                            form.setData(key, value)
                                        }
                                    />
                                ) : (
                                    <Input
                                        id={key}
                                        type="text"
                                        required={required}
                                        maxLength={
                                            key === 'invoice_number' ||
                                            key === 'delivery_reference' ||
                                            key === 'noa_reference' ||
                                            key === 'ntp_reference'
                                                ? 120
                                                : 255
                                        }
                                        value={form.data[key]}
                                        onChange={(e) =>
                                            form.setData(key, e.target.value)
                                        }
                                    />
                                )}
                            </div>
                        ))}
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="document-basis">
                                PO, TOR, and supporting-document verification
                            </Label>
                            <textarea
                                id="document-basis"
                                className={areaClass}
                                required
                                maxLength={3000}
                                placeholder="Record checked documents, certifications, and any reason NOA/NTP is not applicable."
                                value={form.data.order_document_basis}
                                onChange={(e) =>
                                    form.setData(
                                        'order_document_basis',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="packaging-findings">
                                Packaging condition and receipt findings
                            </Label>
                            <textarea
                                id="packaging-findings"
                                className={areaClass}
                                required
                                maxLength={3000}
                                value={form.data.packaging_findings}
                                onChange={(e) =>
                                    form.setData(
                                        'packaging_findings',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </section>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(e) =>
                                form.setData('attested', e.target.checked)
                            }
                        />
                        I confirm the shipment was physically received and its
                        documents and quantities were checked.
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                    <Button
                        disabled={
                            form.processing ||
                            !form.data.attested ||
                            form.data.lines.length === 0
                        }
                    >
                        Record for inspection
                    </Button>
                </form>
            </div>
        </>
    );
}
DeliveryCreate.layout = {
    breadcrumbs: [{ title: 'Delivery Inspection', href: index.url() }],
};
