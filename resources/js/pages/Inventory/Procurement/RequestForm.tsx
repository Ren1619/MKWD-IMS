import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/form-error-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/inventory/procurement';

export type RequestFormContext = {
    date: string;
    department: string | null;
    requested_by: string;
    checked_by: string | null;
    approved_by: string | null;
};

export type ChargeAccount = { code: string; name: string };

export function RequestForm({
    open,
    onOpenChange,
    context,
    accounts,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    context: RequestFormContext;
    accounts: ChargeAccount[];
}) {
    const errorSummary = useRef<HTMLDivElement>(null);
    const form = useForm({
        request_form: true,
        document_kind: 'PR',
        required_at: '',
        purpose: '',
        abc: '',
        charge_account: '',
        lines: [{ quantity: 1, unit_of_measure: '', item_name: '' }],
    });

    function close(open: boolean) {
        onOpenChange(open);

        if (!open) {
            form.reset();
            form.clearErrors();
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => close(false),
            onError: () =>
                requestAnimationFrame(() => errorSummary.current?.focus()),
        });
    }

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-3xl">
                <form onSubmit={submit} className="grid gap-5" noValidate>
                    <DialogHeader>
                        <DialogTitle>Request Form</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="request-date">Date</Label>
                            <Input
                                id="request-date"
                                value={new Date(
                                    `${context.date}T00:00:00`,
                                ).toLocaleDateString('en-PH', {
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                                readOnly
                            />
                        </div>
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                Request Type
                            </legend>
                            {(['PR', 'JO'] as const).map((kind) => (
                                <label
                                    key={kind}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="document_kind"
                                        value={kind}
                                        checked={
                                            form.data.document_kind === kind
                                        }
                                        onChange={() =>
                                            form.setData('document_kind', kind)
                                        }
                                    />
                                    {kind === 'PR'
                                        ? 'Purchase Requisition'
                                        : 'Job Order'}
                                </label>
                            ))}
                            <InputError message={form.errors.document_kind} />
                        </fieldset>
                        <div className="grid gap-2">
                            <Label htmlFor="request-department">
                                Requisitioning Department
                            </Label>
                            <Input
                                id="request-department"
                                value={context.department ?? ''}
                                placeholder="Unavailable in system information"
                                readOnly
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="request-delivery-date">
                                Date of Delivery/Accomplishment
                            </Label>
                            <DatePicker
                                id="request-delivery-date"
                                value={form.data.required_at}
                                onChange={(value) =>
                                    form.setData('required_at', value)
                                }
                                aria-invalid={Boolean(form.errors.required_at)}
                            />
                            <InputError message={form.errors.required_at} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="request-purpose">Purpose</Label>
                        <textarea
                            id="request-purpose"
                            rows={3}
                            className="min-h-24 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                            value={form.data.purpose}
                            onChange={(event) => {
                                form.setData('purpose', event.target.value);
                                event.target.style.height = 'auto';
                                event.target.style.height = `${event.target.scrollHeight}px`;
                            }}
                            aria-invalid={Boolean(form.errors.purpose)}
                        />
                        <InputError message={form.errors.purpose} />
                    </div>
                    <fieldset className="grid gap-3">
                        <legend className="mb-3 text-sm font-medium">
                            Items/Services Needed
                        </legend>
                        {form.data.lines.map((line, index) => (
                            <div
                                key={index}
                                className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[6rem_7rem_minmax(0,1fr)_auto]"
                            >
                                {(
                                    [
                                        ['quantity', 'Quantity'],
                                        ['unit_of_measure', 'Unit'],
                                        ['item_name', 'Description'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key} className="grid gap-2">
                                        <Label
                                            htmlFor={`request-line-${index}-${key}`}
                                        >
                                            {label}
                                        </Label>
                                        <Input
                                            id={`request-line-${index}-${key}`}
                                            type={
                                                key === 'quantity'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            min={
                                                key === 'quantity'
                                                    ? 1
                                                    : undefined
                                            }
                                            value={line[key]}
                                            onChange={(event) =>
                                                form.setData(
                                                    'lines',
                                                    form.data.lines.map(
                                                        (entry, entryIndex) =>
                                                            entryIndex === index
                                                                ? {
                                                                      ...entry,
                                                                      [key]:
                                                                          key ===
                                                                          'quantity'
                                                                              ? Number(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                              : event
                                                                                    .target
                                                                                    .value,
                                                                  }
                                                                : entry,
                                                    ),
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors[
                                                    `lines.${index}.${key}`
                                                ],
                                            )}
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `lines.${index}.${key}`
                                                ]
                                            }
                                        />
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="sm:mt-6"
                                    aria-label={`Remove item ${index + 1}`}
                                    disabled={form.data.lines.length === 1}
                                    onClick={() =>
                                        form.setData(
                                            'lines',
                                            form.data.lines.filter(
                                                (_, entryIndex) =>
                                                    entryIndex !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                        <InputError message={form.errors.lines} />
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            disabled={form.data.lines.length >= 50}
                            onClick={() =>
                                form.setData('lines', [
                                    ...form.data.lines,
                                    {
                                        quantity: 1,
                                        unit_of_measure: '',
                                        item_name: '',
                                    },
                                ])
                            }
                        >
                            <Plus /> Add item/service
                        </Button>
                    </fieldset>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="request-abc">
                                Allocated Budget Cost (ABC)
                            </Label>
                            <Input
                                id="request-abc"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={form.data.abc}
                                onChange={(event) =>
                                    form.setData('abc', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.abc)}
                            />
                            <InputError message={form.errors.abc} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="request-charge-account">
                                Charge To
                            </Label>
                            <Input
                                id="request-charge-account"
                                name="charge_account"
                                role="combobox"
                                list="request-charge-accounts"
                                aria-autocomplete="list"
                                aria-controls="request-charge-accounts"
                                value={form.data.charge_account}
                                onChange={(event) =>
                                    form.setData(
                                        'charge_account',
                                        event.target.value,
                                    )
                                }
                                placeholder="Select account"
                                disabled={accounts.length === 0}
                                aria-invalid={Boolean(
                                    form.errors.charge_account,
                                )}
                            />
                            <datalist id="request-charge-accounts">
                                {accounts.map((account) => (
                                    <option
                                        key={account.code}
                                        value={account.code}
                                    >
                                        {account.name}
                                    </option>
                                ))}
                            </datalist>
                            {accounts.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    API from BAMS
                                </p>
                            )}{/* needs API date from BAMS */}
                            <InputError message={form.errors.charge_account} />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {(
                            [
                                ['requested_by', 'Requested by'],
                                ['checked_by', 'Checked by'],
                                ['approved_by', 'Approved by'],
                            ] as const
                        ).map(([key, label]) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={`request-${key}`}>
                                    {label}
                                </Label>
                                <Input
                                    id={`request-${key}`}
                                    value={context[key] ?? ''}
                                    placeholder="Unavailable in system information"
                                    readOnly
                                />
                            </div>
                        ))}
                    </div>
                    <div ref={errorSummary} tabIndex={-1}>
                        <FormErrorSummary errors={form.errors} />
                    </div>
                    <Button
                        type="submit"
                        className="w-fit"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Saving…' : 'Create request'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
