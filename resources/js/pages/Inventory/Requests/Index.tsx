import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ClipboardList,
    FilterX,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { FormErrorSummary } from '@/components/form-error-summary';
import InputError from '@/components/input-error';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkflowActionDialog } from '@/components/workflow-action-dialog';
import { WorkflowStatus } from '@/components/workflow-status';
import { index as procurementIndex } from '@/routes/inventory/procurement';
import { index, store, transition } from '@/routes/inventory/requests';
import type { Paginated } from '@/types/inventory';

type Item = {
    inventory_item_id: number;
    name: string;
    stock_number: string;
    unit_of_measure: string;
    quantity: number;
    reorder_point: number;
    weighted_average_unit_cost: string | null;
};
type RequestLine = {
    id: number;
    item_name: string;
    unit_of_measure: string;
    quantity_requested: number;
    quantity_approved: number;
    quantity_reserved: number;
    quantity_released: number;
    is_new_item: boolean;
};
type ActionHistory = {
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
    lines: RequestLine[];
    actions: ActionHistory[];
};
type RequestFormLine = {
    inventory_item_id: string;
    is_new_item: boolean;
    item_name: string;
    specifications: string;
    unit_of_measure: string;
    quantity: number;
    estimated_unit_cost: string;
    justification: string;
};
type RequestForm = {
    office_name: string;
    responsibility_center_code: string;
    purpose: string;
    date_needed: string;
    lines: RequestFormLine[];
};
type Filters = { search?: string; status?: string; queue?: string };
type WorkflowAction = {
    action: string;
    label: string;
    description: string;
    destructive?: boolean;
    remarksRequired?: boolean;
};

const workflowSteps = [
    'submitted',
    'approved',
    'ready_for_release',
    'released',
] as const;
const terminalStatuses = ['rejected', 'cancelled'] as const;
const fieldClassName =
    'min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm';

function emptyLine(): RequestFormLine {
    return {
        inventory_item_id: '',
        is_new_item: false,
        item_name: '',
        specifications: '',
        unit_of_measure: '',
        quantity: 1,
        estimated_unit_cost: '',
        justification: '',
    };
}

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ');
}

function nextAction(status: string): string {
    return (
        (
            {
                submitted:
                    'Waiting for an inventory manager to review and approve.',
                approved: 'Approved. Stock availability must now be reviewed.',
                awaiting_replenishment:
                    'Some items are unavailable. Review after replenishment or release reserved quantities.',
                ready_for_release:
                    'All approved quantities are reserved and ready for release.',
                partially_released:
                    'Some quantities were released. Review stock and release the remainder.',
                released: 'Completed. All approved supplies were released.',
                rejected:
                    'Closed as rejected. See the decision history for the reason.',
                cancelled:
                    'Closed as cancelled. See the decision history for the reason.',
            } as Record<string, string>
        )[status] ?? 'Review the decision history for the current state.'
    );
}

function actionsFor(status: string): WorkflowAction[] {
    const close = (action: 'reject' | 'cancel'): WorkflowAction => ({
        action,
        label: `${action === 'reject' ? 'Reject' : 'Cancel'} request`,
        description: `Close this request as ${action === 'reject' ? 'rejected' : 'cancelled'} and record the reason.`,
        remarksRequired: true,
        destructive: true,
    });
    const actions: Record<string, WorkflowAction[]> = {
        submitted: [
            {
                action: 'approve',
                label: 'Approve request',
                description:
                    'Confirm that this request is necessary and authorized.',
            },
            close('reject'),
            close('cancel'),
        ],
        approved: [
            {
                action: 'review',
                label: 'Review stock',
                description: 'Reserve available stock and identify shortages.',
            },
            close('reject'),
            close('cancel'),
        ],
        awaiting_replenishment: [
            {
                action: 'review',
                label: 'Review stock again',
                description: 'Recalculate reservations after stock changes.',
            },
            {
                action: 'release',
                label: 'Release reserved stock',
                description:
                    'Release reserved quantities and retain any shortages.',
            },
            close('cancel'),
        ],
        ready_for_release: [
            {
                action: 'release',
                label: 'Release supplies',
                description:
                    'Deduct and release the reserved quantities to the requester.',
            },
            close('cancel'),
        ],
        partially_released: [
            {
                action: 'review',
                label: 'Review remaining stock',
                description:
                    'Recalculate reservations for outstanding quantities.',
            },
            {
                action: 'release',
                label: 'Release remainder',
                description: 'Release newly reserved outstanding quantities.',
            },
        ],
    };

    return actions[status] ?? [];
}

export default function RequestsIndex({
    requests,
    items,
    canManage,
    filters,
}: {
    requests: Paginated<RequestRecord>;
    items: Item[];
    canManage: boolean;
    filters: Filters;
}) {
    const errorSummary = useRef<HTMLDivElement>(null);
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        queue: filters.queue ?? '',
    });
    const [selectedAction, setSelectedAction] = useState<{
        record: RequestRecord;
        action: WorkflowAction;
    } | null>(null);
    const form = useForm<RequestForm>({
        office_name: '',
        responsibility_center_code: '',
        purpose: '',
        date_needed: '',
        lines: [emptyLine()],
    });
    const actionForm = useForm({ action: '', attested: true, remarks: '' });
    const errors = form.errors as Record<string, string>;

    function updateLine(lineIndex: number, changes: Partial<RequestFormLine>) {
        form.setData(
            'lines',
            form.data.lines.map((line, index) =>
                index === lineIndex ? { ...line, ...changes } : line,
            ),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () =>
                requestAnimationFrame(() => errorSummary.current?.focus()),
        });
    }

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(index.url(), filterValues, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function clearFilters() {
        setFilterValues({ search: '', status: '', queue: '' });
        router.get(index.url(), {}, { preserveState: true, replace: true });
    }

    function openAction(record: RequestRecord, action: WorkflowAction) {
        actionForm.clearErrors();
        actionForm.setData({
            action: action.action,
            attested: true,
            remarks: '',
        });
        setSelectedAction({ record, action });
    }

    function confirmAction() {
        if (!selectedAction) {
            return;
        }

        actionForm.patch(transition.url(selectedAction.record.id), {
            preserveScroll: true,
            onSuccess: () => setSelectedAction(null),
        });
    }

    return (
        <>
            <Head title="Supply Requests" />
            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Digitized RIS controls
                        </p>
                        <h1 className="text-2xl font-semibold sm:text-3xl">
                            Supply requests
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Submit, authorize, reserve, and release supplies
                            with a complete audit history.
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            nativeButton={false}
                            className="min-h-11 w-full sm:w-auto"
                            render={<Link href={procurementIndex()} />}
                        >
                            <ShoppingCart /> Procurement controls
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="size-5" /> Submit a request
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-5"
                            noValidate
                        >
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    id="office-name"
                                    label="Office or division"
                                    error={errors.office_name}
                                >
                                    <Input
                                        id="office-name"
                                        className="min-h-11"
                                        value={form.data.office_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'office_name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.office_name,
                                        )}
                                    />
                                </Field>
                                <Field
                                    id="responsibility-center"
                                    label="Responsibility center"
                                    error={errors.responsibility_center_code}
                                >
                                    <Input
                                        id="responsibility-center"
                                        className="min-h-11"
                                        value={
                                            form.data.responsibility_center_code
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'responsibility_center_code',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.responsibility_center_code,
                                        )}
                                    />
                                </Field>
                                <Field
                                    id="date-needed"
                                    label="Date needed"
                                    error={errors.date_needed}
                                >
                                    <Input
                                        id="date-needed"
                                        type="date"
                                        className="min-h-11"
                                        value={form.data.date_needed}
                                        onChange={(event) =>
                                            form.setData(
                                                'date_needed',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.date_needed,
                                        )}
                                    />
                                </Field>
                            </div>
                            <Field
                                id="request-purpose"
                                label="Official purpose"
                                error={errors.purpose}
                            >
                                <textarea
                                    id="request-purpose"
                                    rows={3}
                                    required
                                    className={fieldClassName}
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.purpose)}
                                />
                            </Field>

                            <div className="grid gap-3">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="font-medium">
                                            Requested items
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            Add up to 25 existing or entirely
                                            new supply items.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11 w-full sm:w-auto"
                                        disabled={form.data.lines.length >= 25}
                                        onClick={() =>
                                            form.setData('lines', [
                                                ...form.data.lines,
                                                emptyLine(),
                                            ])
                                        }
                                    >
                                        <Plus /> Add item
                                    </Button>
                                </div>
                                {form.data.lines.map((line, lineIndex) => {
                                    const selectedItem = items.find(
                                        (item) =>
                                            String(item.inventory_item_id) ===
                                            line.inventory_item_id,
                                    );

                                    return (
                                        <fieldset
                                            key={lineIndex}
                                            className="grid gap-4 rounded-xl border p-4"
                                        >
                                            <legend className="px-2 text-sm font-medium">
                                                Item {lineIndex + 1}
                                            </legend>
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <label className="flex min-h-11 items-center gap-3 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        className="size-5"
                                                        checked={
                                                            line.is_new_item
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(
                                                                lineIndex,
                                                                {
                                                                    is_new_item:
                                                                        event
                                                                            .target
                                                                            .checked,
                                                                    inventory_item_id:
                                                                        '',
                                                                    item_name:
                                                                        '',
                                                                    unit_of_measure:
                                                                        '',
                                                                },
                                                            )
                                                        }
                                                    />
                                                    Entirely new supply item
                                                </label>
                                                {form.data.lines.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        className="min-h-11 w-full sm:w-auto"
                                                        onClick={() =>
                                                            form.setData(
                                                                'lines',
                                                                form.data.lines.filter(
                                                                    (
                                                                        _,
                                                                        index,
                                                                    ) =>
                                                                        index !==
                                                                        lineIndex,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 /> Remove item
                                                    </Button>
                                                )}
                                            </div>
                                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                                {line.is_new_item ? (
                                                    <Field
                                                        id={`line-${lineIndex}-name`}
                                                        label="Proposed item name"
                                                        error={
                                                            errors[
                                                                `lines.${lineIndex}.item_name`
                                                            ]
                                                        }
                                                        className="md:col-span-2"
                                                    >
                                                        <Input
                                                            id={`line-${lineIndex}-name`}
                                                            className="min-h-11"
                                                            value={
                                                                line.item_name
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    lineIndex,
                                                                    {
                                                                        item_name:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            aria-invalid={Boolean(
                                                                errors[
                                                                    `lines.${lineIndex}.item_name`
                                                                ],
                                                            )}
                                                        />
                                                    </Field>
                                                ) : (
                                                    <Field
                                                        id={`line-${lineIndex}-item`}
                                                        label="Stocked item"
                                                        error={
                                                            errors[
                                                                `lines.${lineIndex}.inventory_item_id`
                                                            ]
                                                        }
                                                        className="md:col-span-2"
                                                    >
                                                        <select
                                                            id={`line-${lineIndex}-item`}
                                                            required
                                                            className={
                                                                fieldClassName
                                                            }
                                                            value={
                                                                line.inventory_item_id
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const item =
                                                                    items.find(
                                                                        (
                                                                            candidate,
                                                                        ) =>
                                                                            String(
                                                                                candidate.inventory_item_id,
                                                                            ) ===
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    );
                                                                updateLine(
                                                                    lineIndex,
                                                                    {
                                                                        inventory_item_id:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        item_name:
                                                                            item?.name ??
                                                                            '',
                                                                        unit_of_measure:
                                                                            item?.unit_of_measure ??
                                                                            '',
                                                                        estimated_unit_cost:
                                                                            item?.weighted_average_unit_cost ??
                                                                            '',
                                                                    },
                                                                );
                                                            }}
                                                            aria-invalid={Boolean(
                                                                errors[
                                                                    `lines.${lineIndex}.inventory_item_id`
                                                                ],
                                                            )}
                                                        >
                                                            <option value="">
                                                                Select an item
                                                            </option>
                                                            {items.map(
                                                                (item) => (
                                                                    <option
                                                                        key={
                                                                            item.inventory_item_id
                                                                        }
                                                                        value={
                                                                            item.inventory_item_id
                                                                        }
                                                                    >
                                                                        {
                                                                            item.name
                                                                        }{' '}
                                                                        —{' '}
                                                                        {
                                                                            item.quantity
                                                                        }{' '}
                                                                        {
                                                                            item.unit_of_measure
                                                                        }{' '}
                                                                        available
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </Field>
                                                )}
                                                <Field
                                                    id={`line-${lineIndex}-quantity`}
                                                    label="Quantity"
                                                    error={
                                                        errors[
                                                            `lines.${lineIndex}.quantity`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`line-${lineIndex}-quantity`}
                                                        type="number"
                                                        min="1"
                                                        className="min-h-11"
                                                        value={line.quantity}
                                                        onChange={(event) =>
                                                            updateLine(
                                                                lineIndex,
                                                                {
                                                                    quantity:
                                                                        Number(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                },
                                                            )
                                                        }
                                                        aria-invalid={Boolean(
                                                            errors[
                                                                `lines.${lineIndex}.quantity`
                                                            ],
                                                        )}
                                                    />
                                                </Field>
                                                <Field
                                                    id={`line-${lineIndex}-cost`}
                                                    label="Estimated unit cost"
                                                    error={
                                                        errors[
                                                            `lines.${lineIndex}.estimated_unit_cost`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`line-${lineIndex}-cost`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className="min-h-11"
                                                        value={
                                                            line.estimated_unit_cost
                                                        }
                                                        onChange={(event) =>
                                                            updateLine(
                                                                lineIndex,
                                                                {
                                                                    estimated_unit_cost:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                        aria-invalid={Boolean(
                                                            errors[
                                                                `lines.${lineIndex}.estimated_unit_cost`
                                                            ],
                                                        )}
                                                    />
                                                </Field>
                                            </div>
                                            {line.is_new_item && (
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <Field
                                                        id={`line-${lineIndex}-unit`}
                                                        label="Unit of measure"
                                                        error={
                                                            errors[
                                                                `lines.${lineIndex}.unit_of_measure`
                                                            ]
                                                        }
                                                    >
                                                        <Input
                                                            id={`line-${lineIndex}-unit`}
                                                            className="min-h-11"
                                                            value={
                                                                line.unit_of_measure
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    lineIndex,
                                                                    {
                                                                        unit_of_measure:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            aria-invalid={Boolean(
                                                                errors[
                                                                    `lines.${lineIndex}.unit_of_measure`
                                                                ],
                                                            )}
                                                        />
                                                    </Field>
                                                    <Field
                                                        id={`line-${lineIndex}-specifications`}
                                                        label="Specifications"
                                                        error={
                                                            errors[
                                                                `lines.${lineIndex}.specifications`
                                                            ]
                                                        }
                                                    >
                                                        <textarea
                                                            id={`line-${lineIndex}-specifications`}
                                                            rows={3}
                                                            className={
                                                                fieldClassName
                                                            }
                                                            value={
                                                                line.specifications
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    lineIndex,
                                                                    {
                                                                        specifications:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            aria-invalid={Boolean(
                                                                errors[
                                                                    `lines.${lineIndex}.specifications`
                                                                ],
                                                            )}
                                                        />
                                                    </Field>
                                                    <Field
                                                        id={`line-${lineIndex}-justification`}
                                                        label="Justification and lack of an existing equivalent"
                                                        error={
                                                            errors[
                                                                `lines.${lineIndex}.justification`
                                                            ]
                                                        }
                                                        className="md:col-span-2"
                                                    >
                                                        <textarea
                                                            id={`line-${lineIndex}-justification`}
                                                            rows={3}
                                                            className={
                                                                fieldClassName
                                                            }
                                                            value={
                                                                line.justification
                                                            }
                                                            onChange={(event) =>
                                                                updateLine(
                                                                    lineIndex,
                                                                    {
                                                                        justification:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                    },
                                                                )
                                                            }
                                                            aria-invalid={Boolean(
                                                                errors[
                                                                    `lines.${lineIndex}.justification`
                                                                ],
                                                            )}
                                                        />
                                                    </Field>
                                                </div>
                                            )}
                                            {selectedItem && (
                                                <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                                                    Current stock:{' '}
                                                    {selectedItem.quantity}{' '}
                                                    {
                                                        selectedItem.unit_of_measure
                                                    }
                                                    ; reorder point:{' '}
                                                    {selectedItem.reorder_point}
                                                    .
                                                </p>
                                            )}
                                        </fieldset>
                                    );
                                })}
                            </div>
                            <div ref={errorSummary} tabIndex={-1}>
                                <FormErrorSummary errors={errors} />
                            </div>
                            <Button
                                type="submit"
                                className="min-h-11 w-full sm:w-fit"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Submitting…'
                                    : 'Submit and attest'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Request queue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-3 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]"
                        >
                            <Field id="request-search" label="Search">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-3.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        id="request-search"
                                        className="min-h-11 pl-9"
                                        placeholder="RIS no., requester, office, item…"
                                        value={filterValues.search}
                                        onChange={(event) =>
                                            setFilterValues({
                                                ...filterValues,
                                                search: event.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </Field>
                            <Field id="request-status" label="Status">
                                <select
                                    id="request-status"
                                    className={fieldClassName}
                                    value={filterValues.status}
                                    onChange={(event) =>
                                        setFilterValues({
                                            ...filterValues,
                                            status: event.target.value,
                                        })
                                    }
                                >
                                    <option value="">All statuses</option>
                                    {[
                                        'submitted',
                                        'approved',
                                        'awaiting_replenishment',
                                        'ready_for_release',
                                        'partially_released',
                                        'released',
                                        'rejected',
                                        'cancelled',
                                    ].map((status) => (
                                        <option key={status} value={status}>
                                            {statusLabel(status)}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field id="request-queue" label="Queue">
                                <select
                                    id="request-queue"
                                    className={fieldClassName}
                                    value={filterValues.queue}
                                    onChange={(event) =>
                                        setFilterValues({
                                            ...filterValues,
                                            queue: event.target.value,
                                        })
                                    }
                                >
                                    <option value="">All records</option>
                                    <option value="needs_action">
                                        Needs action
                                    </option>
                                    <option value="completed">Completed</option>
                                </select>
                            </Field>
                            <div className="flex gap-2 md:items-end">
                                <Button className="min-h-11 flex-1 md:flex-none">
                                    Apply
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    className="size-11"
                                    aria-label="Clear filters"
                                    onClick={clearFilters}
                                >
                                    <FilterX />
                                </Button>
                            </div>
                        </form>
                        <p
                            role="status"
                            className="mt-3 text-sm text-muted-foreground"
                        >
                            {requests.total.toLocaleString()} request
                            {requests.total === 1 ? '' : 's'} found.
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-4">
                    {requests.data.map((record) => (
                        <Card key={record.id}>
                            <CardHeader>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList className="size-5" />{' '}
                                        {record.ris_no}
                                    </CardTitle>
                                    <span className="w-fit rounded-full border px-3 py-1 text-xs font-medium capitalize">
                                        {statusLabel(record.status)}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <WorkflowStatus
                                    steps={workflowSteps}
                                    current={record.status}
                                    terminal={terminalStatuses}
                                />
                                <div className="rounded-lg bg-muted/60 p-3 text-sm">
                                    <p className="font-medium">
                                        What happens next
                                    </p>
                                    <p className="text-muted-foreground">
                                        {nextAction(record.status)}
                                    </p>
                                </div>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="font-medium">
                                            Requester
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {record.requester_name}
                                            {record.office_name
                                                ? ` — ${record.office_name}`
                                                : ''}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">Purpose</dt>
                                        <dd className="text-muted-foreground">
                                            {record.purpose}
                                        </dd>
                                    </div>
                                </dl>
                                <div className="grid gap-2 md:hidden">
                                    {record.lines.map((line) => (
                                        <div
                                            key={line.id}
                                            className="rounded-lg border p-3 text-sm"
                                        >
                                            <p className="font-medium">
                                                {line.item_name}
                                                {line.is_new_item
                                                    ? ' (new item)'
                                                    : ''}
                                            </p>
                                            <dl className="mt-2 grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Requested
                                                    </dt>
                                                    <dd>
                                                        {
                                                            line.quantity_requested
                                                        }{' '}
                                                        {line.unit_of_measure}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Approved
                                                    </dt>
                                                    <dd>
                                                        {line.quantity_approved}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Reserved
                                                    </dt>
                                                    <dd>
                                                        {line.quantity_reserved}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Released
                                                    </dt>
                                                    <dd>
                                                        {line.quantity_released}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>
                                    ))}
                                </div>
                                <div className="hidden overflow-x-auto md:block">
                                    <Table className="w-full text-sm">
                                        <caption className="sr-only">
                                            Items requested under{' '}
                                            {record.ris_no}
                                        </caption>
                                        <TableHeader>
                                            <TableRow className="border-b text-left">
                                                <TableHead
                                                    scope="col"
                                                    className="py-2"
                                                >
                                                    Item
                                                </TableHead>
                                                <TableHead scope="col">
                                                    Requested
                                                </TableHead>
                                                <TableHead scope="col">
                                                    Approved
                                                </TableHead>
                                                <TableHead scope="col">
                                                    Reserved
                                                </TableHead>
                                                <TableHead scope="col">
                                                    Released
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {record.lines.map((line) => (
                                                <TableRow
                                                    className="border-b"
                                                    key={line.id}
                                                >
                                                    <TableCell className="py-2">
                                                        {line.item_name}
                                                        {line.is_new_item
                                                            ? ' (new)'
                                                            : ''}
                                                    </TableCell>
                                                    <TableCell>
                                                        {
                                                            line.quantity_requested
                                                        }{' '}
                                                        {line.unit_of_measure}
                                                    </TableCell>
                                                    <TableCell>
                                                        {line.quantity_approved}
                                                    </TableCell>
                                                    <TableCell>
                                                        {line.quantity_reserved}
                                                    </TableCell>
                                                    <TableCell>
                                                        {line.quantity_released}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                                {canManage &&
                                    actionsFor(record.status).length > 0 && (
                                        <div className="grid gap-2 sm:flex sm:flex-wrap">
                                            {actionsFor(record.status).map(
                                                (action) => (
                                                    <Button
                                                        key={action.action}
                                                        className="min-h-11 w-full sm:w-auto"
                                                        variant={
                                                            action.destructive
                                                                ? 'destructive'
                                                                : action.action ===
                                                                    'release'
                                                                  ? 'default'
                                                                  : 'outline'
                                                        }
                                                        onClick={() =>
                                                            openAction(
                                                                record,
                                                                action,
                                                            )
                                                        }
                                                    >
                                                        {action.label}
                                                    </Button>
                                                ),
                                            )}
                                        </div>
                                    )}
                                <details>
                                    <summary className="flex min-h-11 cursor-pointer items-center text-sm font-medium">
                                        Decision history (
                                        {record.actions.length})
                                    </summary>
                                    <div className="mt-2 grid gap-2">
                                        {record.actions.map((action) => (
                                            <div
                                                key={action.id}
                                                className="rounded-lg border p-3 text-xs"
                                            >
                                                <p>
                                                    <b>{action.actor.name}</b> —{' '}
                                                    {statusLabel(action.action)}{' '}
                                                    to{' '}
                                                    {statusLabel(
                                                        action.to_status,
                                                    )}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {new Date(
                                                        action.created_at,
                                                    ).toLocaleString()}
                                                </p>
                                                {action.remarks && (
                                                    <p className="mt-1">
                                                        {action.remarks}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </details>
                            </CardContent>
                        </Card>
                    ))}
                    {requests.data.length === 0 && (
                        <Card>
                            <CardContent className="grid justify-items-center gap-3 py-10 text-center">
                                <ClipboardList className="size-8 text-muted-foreground" />
                                <p className="text-muted-foreground">
                                    No requests match the selected filters.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={clearFilters}
                                >
                                    Clear filters
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
                <DataPagination links={requests.links} />
            </div>
            <WorkflowActionDialog
                key={
                    selectedAction
                        ? `${selectedAction.record.id}-${selectedAction.action.action}`
                        : 'closed'
                }
                open={selectedAction !== null}
                onOpenChange={(open) => !open && setSelectedAction(null)}
                title={
                    selectedAction
                        ? `${selectedAction.action.label}: ${selectedAction.record.ris_no}`
                        : 'Request action'
                }
                description={selectedAction?.action.description ?? ''}
                confirmLabel={selectedAction?.action.label ?? 'Confirm'}
                destructive={selectedAction?.action.destructive}
                remarksRequired={selectedAction?.action.remarksRequired}
                processing={actionForm.processing}
                remarks={actionForm.data.remarks}
                onRemarksChange={(remarks) =>
                    actionForm.setData('remarks', remarks)
                }
                onConfirm={confirmAction}
                error={actionForm.errors.action ?? actionForm.errors.remarks}
            />
        </>
    );
}

function Field({
    id,
    label,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`grid gap-2 ${className ?? ''}`}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

RequestsIndex.layout = {
    breadcrumbs: [{ title: 'Supply Requests', href: index() }],
};
