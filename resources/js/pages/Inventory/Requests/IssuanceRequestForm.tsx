import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/form-error-summary';
import InputError from '@/components/input-error';
import { InventoryItemCombobox } from '@/components/inventory-item-combobox';
import { ResponsibilityCenterCombobox } from '@/components/responsibility-center-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/inventory/requests';

export type IssuanceContext = {
    irf_no: string | null;
    department: string | null;
    date: string;
    requested_by: string;
    checked_by: string | null;
    approved_by: string | null;
};

type Item = {
    inventory_item_id: number;
    name: string;
    unit_of_measure: string;
};
type ChargeCode = { code: string; name: string };

function emptyLine() {
    return {
        inventory_item_id: '',
        quantity: 1,
        unit_of_measure: '',
        item_name: '',
    };
}

export function IssuanceRequestForm({
    open,
    onOpenChange,
    context,
    items,
    chargeCodes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    context: IssuanceContext;
    items: Item[];
    chargeCodes: ChargeCode[];
}) {
    const errorSummary = useRef<HTMLDivElement>(null);
    const form = useForm({
        issuance_form: true,
        purpose: '',
        responsibility_center_code: '',
        lines: [emptyLine()],
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

    function updateLine(
        index: number,
        key: 'quantity' | 'unit_of_measure' | 'item_name',
        value: string,
    ) {
        const item =
            key === 'item_name'
                ? items.find((item) => item.name === value)
                : undefined;
        form.setData(
            'lines',
            form.data.lines.map((entry, entryIndex) =>
                entryIndex === index
                    ? {
                          ...entry,
                          [key]: key === 'quantity' ? Number(value) : value,
                          ...(key === 'item_name'
                              ? {
                                    inventory_item_id: item
                                        ? String(item.inventory_item_id)
                                        : '',
                                    unit_of_measure:
                                        item?.unit_of_measure ??
                                        entry.unit_of_measure,
                                }
                              : {}),
                      }
                    : entry,
            ),
        );
    }

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-3xl">
                <form onSubmit={submit} className="grid gap-5" noValidate>
                    <DialogHeader>
                        <DialogTitle>Issuance Request Form</DialogTitle>
                    </DialogHeader>
                    <div className="grid items-start gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="irf-department">Department</Label>
                            <Input
                                id="irf-department"
                                value={context.department ?? ''}
                                placeholder="Unavailable in system information"
                                readOnly
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="irf-date">Date</Label>
                            <Input
                                id="irf-date"
                                value={new Date(
                                    `${context.date}T00:00:00`,
                                ).toLocaleDateString('en-PH', {
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                                readOnly
                            />
                            <p className="text-xs text-muted-foreground">
                                Recorded when submitted.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="irf-number">IRF No.</Label>
                            <Input
                                id="irf-number"
                                value={context.irf_no ?? ''}
                                placeholder="Daily number limit reached"
                                readOnly
                            />
                            <p className="text-xs text-muted-foreground">
                                Preview; finalized when submitted.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="irf-purpose">Purpose</Label>
                        <textarea
                            id="irf-purpose"
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
                            List of Items Needed
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
                                            htmlFor={`irf-line-${index}-${key}`}
                                        >
                                            {label}
                                        </Label>
                                        {key === 'item_name' ? (
                                            <InventoryItemCombobox
                                                id={`irf-line-${index}-${key}`}
                                                items={items}
                                                value={line.item_name}
                                                onValueChange={(value) =>
                                                    updateLine(
                                                        index,
                                                        key,
                                                        value,
                                                    )
                                                }
                                                aria-invalid={Boolean(
                                                    form.errors[
                                                        `lines.${index}.${key}`
                                                    ],
                                                )}
                                            />
                                        ) : (
                                            <Input
                                                id={`irf-line-${index}-${key}`}
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
                                                readOnly={
                                                    key === 'unit_of_measure' &&
                                                    Boolean(
                                                        line.inventory_item_id,
                                                    )
                                                }
                                                onChange={(event) =>
                                                    updateLine(
                                                        index,
                                                        key,
                                                        event.target.value,
                                                    )
                                                }
                                                aria-invalid={Boolean(
                                                    form.errors[
                                                        `lines.${index}.${key}`
                                                    ],
                                                )}
                                            />
                                        )}
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
                            disabled={form.data.lines.length >= 25}
                            onClick={() =>
                                form.setData('lines', [
                                    ...form.data.lines,
                                    emptyLine(),
                                ])
                            }
                        >
                            Add item
                        </Button>
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="irf-charge-code">CHARGE CODE</Label>
                        <ResponsibilityCenterCombobox
                            id="irf-charge-code"
                            options={chargeCodes}
                            value={form.data.responsibility_center_code}
                            onValueChange={(value) =>
                                form.setData(
                                    'responsibility_center_code',
                                    value,
                                )
                            }
                            placeholder="Select or enter a charge code"
                            disabled={chargeCodes.length === 0}
                            aria-invalid={Boolean(
                                form.errors.responsibility_center_code,
                            )}
                        />
                        {chargeCodes.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                Awaiting accounts from Budget and Accounting
                                Management System.
                            </p>
                        )}
                        <InputError
                            message={form.errors.responsibility_center_code}
                        />
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
                                <Label htmlFor={`irf-${key}`}>{label}</Label>
                                <Input
                                    id={`irf-${key}`}
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
                        {form.processing
                            ? 'Submitting…'
                            : 'Submit issuance request'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
