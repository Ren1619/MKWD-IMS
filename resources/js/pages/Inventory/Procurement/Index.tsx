import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    FileCheck2,
    FilterX,
    Plus,
    Search,
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
import { index, store, transition } from '@/routes/inventory/procurement';
import { index as requestsIndex } from '@/routes/inventory/requests';
import type { Paginated } from '@/types/inventory';

type Item = {
    inventory_item_id: number;
    name: string;
    unit_of_measure: string;
    quantity: number;
    reorder_point: number;
    reorder_quantity: number | null;
};
type Series = { inv_series_cat_id: number; name: string };
type ProcurementLine = {
    id: number;
    item_name: string;
    unit_of_measure: string;
    quantity: number;
    estimated_unit_cost: string;
    quantity_received: number;
    item: null | { name: string };
};
type ActionHistory = {
    id: number;
    action: string;
    to_status: string;
    remarks: string | null;
    created_at: string;
    actor: { name: string };
};
type ProcurementRecord = {
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
    lines: ProcurementLine[];
    actions: ActionHistory[];
};
type ProcurementFormLine = {
    client_key: string;
    inventory_item_id: string;
    series_category_id: string;
    item_name: string;
    specifications: string;
    unit_of_measure: string;
    quantity: number;
    estimated_unit_cost: string;
};
type ProcurementForm = {
    type: string;
    source: string;
    purpose: string;
    funding_source: string;
    responsibility_center_code: string;
    ppmp_reference: string;
    app_reference: string;
    app_cse_classification: string;
    required_at: string;
    lines: ProcurementFormLine[];
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
    'draft',
    'for_budget_review',
    'for_approval',
    'approved',
    'forwarded_to_procurement',
    'ordered',
    'delivered',
    'accepted',
] as const;
const terminalStatuses = ['rejected', 'cancelled'] as const;
const fieldClassName =
    'min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm';

function emptyLine(): ProcurementFormLine {
    return {
        client_key: crypto.randomUUID(),
        inventory_item_id: '',
        series_category_id: '',
        item_name: '',
        specifications: '',
        unit_of_measure: '',
        quantity: 1,
        estimated_unit_cost: '',
    };
}

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ');
}

function nextAction(status: string): string {
    return (
        (
            {
                draft: 'The preparer must submit this draft for budget review.',
                for_budget_review:
                    'Verify funding, PPMP, and APP references before certification.',
                for_approval: 'Waiting for an authorized approver.',
                approved:
                    'Approved and ready to forward to the procurement unit.',
                forwarded_to_procurement:
                    'Record the procurement mode and purchase order number.',
                ordered:
                    'Record the delivery reference, date received, and actual cost.',
                delivered:
                    'Inspect the delivery and record the IAR before adding it to stock.',
                accepted:
                    'Completed. Accepted quantities have been added to inventory.',
                rejected:
                    'Closed as rejected. See the decision history for the reason.',
                cancelled:
                    'Closed as cancelled. See the decision history for the reason.',
            } as Record<string, string>
        )[status] ?? 'Review the record and its decision history.'
    );
}

function actionsFor(status: string): WorkflowAction[] {
    const reject: WorkflowAction = {
        action: 'reject',
        label: 'Reject PR',
        description: 'Reject this request and record the decision basis.',
        remarksRequired: true,
        destructive: true,
    };
    const cancel: WorkflowAction = {
        action: 'cancel',
        label: 'Cancel PR',
        description:
            'Cancel this request and record why it should not continue.',
        remarksRequired: true,
        destructive: true,
    };

    return (
        (
            {
                draft: [
                    {
                        action: 'submit',
                        label: 'Submit for budget review',
                        description:
                            'Send this draft to budget and planning review.',
                    },
                    cancel,
                ],
                for_budget_review: [
                    {
                        action: 'budget_review',
                        label: 'Certify budget and plan',
                        description:
                            'Confirm that funding, PPMP, and APP references support this request.',
                    },
                    reject,
                    cancel,
                ],
                for_approval: [
                    {
                        action: 'approve',
                        label: 'Approve PR',
                        description:
                            'Approve the purchase request for procurement processing.',
                    },
                    reject,
                    cancel,
                ],
                approved: [
                    {
                        action: 'forward',
                        label: 'Forward to procurement',
                        description:
                            'Record that the approved PR was forwarded to the procurement unit.',
                    },
                    reject,
                    cancel,
                ],
                forwarded_to_procurement: [
                    {
                        action: 'order',
                        label: 'Record order',
                        description:
                            'Record the purchase order and procurement mode.',
                    },
                    cancel,
                ],
                ordered: [
                    {
                        action: 'record_delivery',
                        label: 'Record delivery',
                        description:
                            'Record the delivery evidence and actual unit cost.',
                    },
                    cancel,
                ],
                delivered: [
                    {
                        action: 'record_delivery',
                        label: 'Update delivery',
                        description:
                            'Correct or complete the recorded delivery evidence.',
                    },
                    {
                        action: 'accept',
                        label: 'Inspect and accept',
                        description:
                            'Record the IAR and add accepted quantities to inventory.',
                    },
                ],
            } as Record<string, WorkflowAction[]>
        )[status] ?? []
    );
}

export default function ProcurementIndex({
    procurementRequests,
    items,
    seriesCategories,
    filters,
}: {
    procurementRequests: Paginated<ProcurementRecord>;
    items: Item[];
    seriesCategories: Series[];
    filters: Filters;
}) {
    const errorSummary = useRef<HTMLDivElement>(null);
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        queue: filters.queue ?? '',
    });
    const [selectedAction, setSelectedAction] = useState<{
        record: ProcurementRecord;
        action: WorkflowAction;
    } | null>(null);
    const [entirelyNewCatalogItems, setEntirelyNewCatalogItems] =
        useState(false);
    const [expandedNewItemKeys, setExpandedNewItemKeys] = useState<Set<string>>(
        new Set(),
    );
    const form = useForm<ProcurementForm>({
        type: 'replenishment',
        source: 'manual',
        purpose: '',
        funding_source: '',
        responsibility_center_code: '',
        ppmp_reference: '',
        app_reference: '',
        app_cse_classification: '',
        required_at: '',
        lines: [],
    });
    const actionForm = useForm({
        action: '',
        attested: true,
        remarks: '',
        procurement_mode: '',
        purchase_order_no: '',
        inspection_acceptance_no: '',
        delivery_reference: '',
        received_at: '',
        actual_unit_cost: '',
    });
    const errors = form.errors as Record<string, string>;

    function updateLine(
        lineIndex: number,
        changes: Partial<ProcurementFormLine>,
    ) {
        form.setData(
            'lines',
            form.data.lines.map((line, index) =>
                index === lineIndex ? { ...line, ...changes } : line,
            ),
        );
    }

    function replaceLines(lines: ProcurementFormLine[]) {
        const hasExistingItems = lines.some(
            (line) => line.inventory_item_id !== '',
        );
        const hasNewItems = lines.some((line) => line.inventory_item_id === '');

        form.setData({
            ...form.data,
            type:
                hasExistingItems && hasNewItems
                    ? 'mixed'
                    : hasNewItems
                      ? 'new_item'
                      : 'replenishment',
            lines,
        });
    }

    function toggleExistingItem(item: Item, checked: boolean) {
        replaceLines(
            checked
                ? [
                      ...form.data.lines,
                      {
                          ...emptyLine(),
                          inventory_item_id: String(item.inventory_item_id),
                          item_name: item.name,
                          unit_of_measure: item.unit_of_measure,
                          quantity: item.reorder_quantity ?? 1,
                      },
                  ]
                : form.data.lines.filter(
                      (line) =>
                          line.inventory_item_id !==
                          String(item.inventory_item_id),
                  ),
        );
    }

    function addNewCatalogItem() {
        const line = emptyLine();

        setExpandedNewItemKeys((current) =>
            new Set(current).add(line.client_key),
        );
        replaceLines([...form.data.lines, line]);
    }

    function toggleNewItemExpanded(clientKey: string, expanded: boolean) {
        setExpandedNewItemKeys((current) => {
            const next = new Set(current);

            if (expanded) {
                next.add(clientKey);
            } else {
                next.delete(clientKey);
            }

            return next;
        });
    }

    function removeNewCatalogItem(lineIndex: number, clientKey: string) {
        replaceLines(form.data.lines.filter((_, index) => index !== lineIndex));
        setExpandedNewItemKeys((current) => {
            const next = new Set(current);
            next.delete(clientKey);

            return next;
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEntirelyNewCatalogItems(false);
                setExpandedNewItemKeys(new Set());
            },
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

    function openAction(record: ProcurementRecord, action: WorkflowAction) {
        actionForm.clearErrors();
        actionForm.setData({
            action: action.action,
            attested: true,
            remarks: '',
            procurement_mode:
                record.status === 'forwarded_to_procurement' ? '' : '',
            purchase_order_no: record.purchase_order_no ?? '',
            inspection_acceptance_no: record.inspection_acceptance_no ?? '',
            delivery_reference: '',
            received_at: '',
            actual_unit_cost: '',
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
            <Head title="Procurement Controls" />
            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Human-authorized procurement
                        </p>
                        <h1 className="text-2xl font-semibold sm:text-3xl">
                            Procurement controls
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Plan, fund, order, receive, inspect, and accept
                            inventory with attested decisions.
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        variant="outline"
                        className="min-h-11 w-full sm:w-auto"
                        render={<Link href={requestsIndex()} />}
                    >
                        <ArrowLeft /> Supply requests
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="size-5" /> Prepare purchase request
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
                                    id="procurement-type"
                                    label="Request type"
                                    error={errors.type}
                                >
                                    <select
                                        id="procurement-type"
                                        className={fieldClassName}
                                        value={form.data.type}
                                        onChange={(event) =>
                                            form.setData(
                                                'type',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="replenishment">
                                            Replenishment
                                        </option>
                                        <option value="new_item">
                                            New item
                                        </option>
                                        <option value="mixed">Mixed</option>
                                    </select>
                                </Field>
                                <Field
                                    id="procurement-source"
                                    label="Source"
                                    error={errors.source}
                                >
                                    <select
                                        id="procurement-source"
                                        className={fieldClassName}
                                        value={form.data.source}
                                        onChange={(event) =>
                                            form.setData(
                                                'source',
                                                event.target.value,
                                            )
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
                                </Field>
                                <Field
                                    id="required-at"
                                    label="Required date"
                                    error={errors.required_at}
                                >
                                    <Input
                                        id="required-at"
                                        type="date"
                                        className="min-h-11"
                                        value={form.data.required_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'required_at',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.required_at,
                                        )}
                                    />
                                </Field>
                            </div>
                            <Field
                                id="procurement-purpose"
                                label="Procurement purpose"
                                error={errors.purpose}
                            >
                                <textarea
                                    id="procurement-purpose"
                                    rows={3}
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
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <Field
                                    id="funding-source"
                                    label="Funding source"
                                    error={errors.funding_source}
                                >
                                    <Input
                                        id="funding-source"
                                        className="min-h-11"
                                        value={form.data.funding_source}
                                        onChange={(event) =>
                                            form.setData(
                                                'funding_source',
                                                event.target.value,
                                            )
                                        }
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
                                    />
                                </Field>
                                <Field
                                    id="ppmp-reference"
                                    label="PPMP reference"
                                    error={errors.ppmp_reference}
                                >
                                    <Input
                                        id="ppmp-reference"
                                        className="min-h-11"
                                        value={form.data.ppmp_reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'ppmp_reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="app-reference"
                                    label="APP reference"
                                    error={errors.app_reference}
                                >
                                    <Input
                                        id="app-reference"
                                        className="min-h-11"
                                        value={form.data.app_reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'app_reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="app-cse"
                                    label="APP-CSE classification"
                                    error={errors.app_cse_classification}
                                >
                                    <Input
                                        id="app-cse"
                                        className="min-h-11"
                                        value={form.data.app_cse_classification}
                                        onChange={(event) =>
                                            form.setData(
                                                'app_cse_classification',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-3">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 className="font-medium">
                                            Procurement items
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            Select existing items and optionally
                                            add entirely new catalog items to
                                            the same PR.
                                        </p>
                                    </div>
                                    <div className="grid gap-2 sm:justify-items-end">
                                        <label className="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm">
                                            <input
                                                type="checkbox"
                                                className="size-5"
                                                checked={
                                                    entirelyNewCatalogItems
                                                }
                                                onChange={(event) => {
                                                    const checked =
                                                        event.target.checked;
                                                    setEntirelyNewCatalogItems(
                                                        checked,
                                                    );

                                                    if (checked) {
                                                        addNewCatalogItem();
                                                    } else {
                                                        replaceLines(
                                                            form.data.lines.filter(
                                                                (line) =>
                                                                    line.inventory_item_id !==
                                                                    '',
                                                            ),
                                                        );
                                                        setExpandedNewItemKeys(
                                                            new Set(),
                                                        );
                                                    }
                                                }}
                                            />
                                            Add new catalog items
                                        </label>
                                    </div>
                                </div>
                                <div className="overflow-hidden rounded-xl border">
                                    <div className="hidden grid-cols-[2rem_minmax(12rem,1fr)_8rem_10rem_8rem_11rem] items-center gap-3 border-b bg-muted/50 px-4 py-3 text-xs font-medium text-muted-foreground md:grid">
                                        <span />
                                        <span>Item</span>
                                        <span>On hand</span>
                                        <span>Suggested reorder</span>
                                        <span>Quantity</span>
                                        <span>Estimated unit cost</span>
                                    </div>
                                    <div className="divide-y">
                                        {items.map((item) => {
                                            const lineIndex =
                                                form.data.lines.findIndex(
                                                    (line) =>
                                                        line.inventory_item_id ===
                                                        String(
                                                            item.inventory_item_id,
                                                        ),
                                                );
                                            const line =
                                                form.data.lines[lineIndex];
                                            const selected = lineIndex !== -1;

                                            return (
                                                <div
                                                    key={item.inventory_item_id}
                                                    className={`grid gap-3 px-4 py-3 md:grid-cols-[2rem_minmax(12rem,1fr)_8rem_10rem_8rem_11rem] md:items-center ${selected ? 'bg-primary/5' : ''}`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        className="size-5"
                                                        checked={selected}
                                                        aria-label={`Select ${item.name}`}
                                                        onChange={(event) =>
                                                            toggleExistingItem(
                                                                item,
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                    <div className="min-w-0">
                                                        <p className="font-medium">
                                                            {item.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                item.unit_of_measure
                                                            }
                                                        </p>
                                                    </div>
                                                    <p className="text-sm">
                                                        <span className="text-xs text-muted-foreground md:hidden">
                                                            On hand:{' '}
                                                        </span>
                                                        {item.quantity}{' '}
                                                        {item.unit_of_measure}
                                                    </p>
                                                    <p className="text-sm">
                                                        <span className="text-xs text-muted-foreground md:hidden">
                                                            Suggested
                                                            reorder:{' '}
                                                        </span>
                                                        {item.reorder_quantity ??
                                                            'Not configured'}
                                                    </p>
                                                    {selected ? (
                                                        <Input
                                                            type="number"
                                                            min="1"
                                                            aria-label={`Quantity for ${item.name}`}
                                                            value={
                                                                line.quantity
                                                            }
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
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                    {selected ? (
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            aria-label={`Estimated unit cost for ${item.name}`}
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
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                        {items.length === 0 && (
                                            <p className="p-4 text-sm text-muted-foreground">
                                                No active catalog items are
                                                available.
                                            </p>
                                        )}
                                    </div>
                                </div>
                                {entirelyNewCatalogItems && (
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h3 className="font-medium">
                                                New catalog items
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                Expand an item to review or edit
                                                its details.
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11 w-full sm:w-auto"
                                            disabled={
                                                form.data.lines.length >= 50
                                            }
                                            onClick={addNewCatalogItem}
                                        >
                                            <Plus /> Add item
                                        </Button>
                                    </div>
                                )}
                                {entirelyNewCatalogItems &&
                                    form.data.lines.map((line, lineIndex) => {
                                        if (line.inventory_item_id !== '') {
                                            return null;
                                        }

                                        const isNewItem =
                                            line.inventory_item_id === '';
                                        const selectedItem = items.find(
                                            (item) =>
                                                String(
                                                    item.inventory_item_id,
                                                ) === line.inventory_item_id,
                                        );

                                        return (
                                            <details
                                                key={line.client_key}
                                                className="group overflow-hidden rounded-xl border"
                                                open={expandedNewItemKeys.has(
                                                    line.client_key,
                                                )}
                                                onToggle={(event) =>
                                                    toggleNewItemExpanded(
                                                        line.client_key,
                                                        event.currentTarget
                                                            .open,
                                                    )
                                                }
                                            >
                                                <summary className="flex min-h-12 cursor-pointer items-center justify-between gap-3 px-4 py-3 font-medium marker:content-none">
                                                    <span>
                                                        {line.item_name ||
                                                            `New item ${lineIndex + 1}`}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground group-open:hidden">
                                                        Expand
                                                    </span>
                                                    <span className="hidden text-xs text-muted-foreground group-open:inline">
                                                        Collapse
                                                    </span>
                                                </summary>
                                                <div className="grid gap-4 border-t p-4">
                                                    <div className="flex justify-end">
                                                        {form.data.lines
                                                            .length > 1 && (
                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                className="min-h-11 w-full sm:w-auto"
                                                                onClick={() =>
                                                                    removeNewCatalogItem(
                                                                        lineIndex,
                                                                        line.client_key,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />{' '}
                                                                Remove item
                                                            </Button>
                                                        )}
                                                    </div>
                                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                                                        {isNewItem ? (
                                                            <>
                                                                <Field
                                                                    id={`line-${lineIndex}-name`}
                                                                    label="New item name"
                                                                    error={
                                                                        errors[
                                                                            `lines.${lineIndex}.item_name`
                                                                        ]
                                                                    }
                                                                    className="lg:col-span-2"
                                                                >
                                                                    <Input
                                                                        id={`line-${lineIndex}-name`}
                                                                        className="min-h-11"
                                                                        value={
                                                                            line.item_name
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
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
                                                                <Field
                                                                    id={`line-${lineIndex}-category`}
                                                                    label="Category on acceptance"
                                                                    error={
                                                                        errors[
                                                                            `lines.${lineIndex}.series_category_id`
                                                                        ]
                                                                    }
                                                                >
                                                                    <select
                                                                        id={`line-${lineIndex}-category`}
                                                                        className={
                                                                            fieldClassName
                                                                        }
                                                                        value={
                                                                            line.series_category_id
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                lineIndex,
                                                                                {
                                                                                    series_category_id:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <option value="">
                                                                            Select
                                                                            category
                                                                        </option>
                                                                        {seriesCategories.map(
                                                                            (
                                                                                series,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        series.inv_series_cat_id
                                                                                    }
                                                                                    value={
                                                                                        series.inv_series_cat_id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        series.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </select>
                                                                </Field>
                                                            </>
                                                        ) : (
                                                            <Field
                                                                id={`line-${lineIndex}-item`}
                                                                label="Existing item"
                                                                error={
                                                                    errors[
                                                                        `lines.${lineIndex}.inventory_item_id`
                                                                    ]
                                                                }
                                                                className="lg:col-span-3"
                                                            >
                                                                <select
                                                                    id={`line-${lineIndex}-item`}
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
                                                                            },
                                                                        );
                                                                    }}
                                                                >
                                                                    <option value="">
                                                                        Select
                                                                        an
                                                                        existing
                                                                        item
                                                                    </option>
                                                                    {items.map(
                                                                        (
                                                                            item,
                                                                        ) => (
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
                                                                                on
                                                                                hand
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
                                                                value={
                                                                    line.quantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
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
                                                                onChange={(
                                                                    event,
                                                                ) =>
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
                                                    {isNewItem && (
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
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
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
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
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
                                                                />
                                                            </Field>
                                                        </div>
                                                    )}
                                                    {selectedItem && (
                                                        <p className="rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                                                            On hand:{' '}
                                                            {
                                                                selectedItem.quantity
                                                            }{' '}
                                                            {
                                                                selectedItem.unit_of_measure
                                                            }
                                                            . Suggested reorder:{' '}
                                                            {selectedItem.reorder_quantity ??
                                                                'not configured'}
                                                            .
                                                        </p>
                                                    )}
                                                </div>
                                            </details>
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
                                    ? 'Preparing…'
                                    : 'Prepare draft PR'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Procurement queue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-3 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]"
                        >
                            <Field id="procurement-search" label="Search">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-3.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        id="procurement-search"
                                        className="min-h-11 pl-9"
                                        placeholder="PR, PO, preparer, purpose, item…"
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
                            <Field id="procurement-status" label="Status">
                                <select
                                    id="procurement-status"
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
                                        ...workflowSteps,
                                        ...terminalStatuses,
                                    ].map((status) => (
                                        <option key={status} value={status}>
                                            {statusLabel(status)}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field id="procurement-queue" label="Queue">
                                <select
                                    id="procurement-queue"
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
                            {procurementRequests.total.toLocaleString()}{' '}
                            purchase request
                            {procurementRequests.total === 1 ? '' : 's'} found.
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-4">
                    {procurementRequests.data.map((record) => (
                        <Card key={record.id}>
                            <CardHeader>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <FileCheck2 className="size-5" />{' '}
                                        {record.pr_no}
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
                                <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt className="font-medium">
                                            Prepared by
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {record.creator.name}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">Purpose</dt>
                                        <dd className="text-muted-foreground">
                                            {record.purpose}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">Funding</dt>
                                        <dd className="text-muted-foreground">
                                            {record.funding_source ??
                                                'Not recorded'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="font-medium">
                                            Planning references
                                        </dt>
                                        <dd className="text-muted-foreground">
                                            {record.ppmp_reference ?? 'No PPMP'}{' '}
                                            · {record.app_reference ?? 'No APP'}
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
                                            </p>
                                            <dl className="mt-2 grid grid-cols-2 gap-2 text-xs">
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Quantity
                                                    </dt>
                                                    <dd>
                                                        {line.quantity}{' '}
                                                        {line.unit_of_measure}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Estimated total
                                                    </dt>
                                                    <dd>
                                                        ₱
                                                        {(
                                                            Number(
                                                                line.estimated_unit_cost,
                                                            ) * line.quantity
                                                        ).toLocaleString()}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        Received
                                                    </dt>
                                                    <dd>
                                                        {line.quantity_received}
                                                    </dd>
                                                </div>
                                            </dl>
                                        </div>
                                    ))}
                                </div>
                                <div className="hidden overflow-x-auto md:block">
                                    <Table className="w-full text-sm">
                                        <caption className="sr-only">
                                            Items under {record.pr_no}
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
                                                    Quantity
                                                </TableHead>
                                                <TableHead scope="col">
                                                    Estimated total
                                                </TableHead>
                                                <TableHead scope="col">
                                                    Received
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
                                                    </TableCell>
                                                    <TableCell>
                                                        {line.quantity}{' '}
                                                        {line.unit_of_measure}
                                                    </TableCell>
                                                    <TableCell>
                                                        ₱
                                                        {(
                                                            Number(
                                                                line.estimated_unit_cost,
                                                            ) * line.quantity
                                                        ).toLocaleString()}
                                                    </TableCell>
                                                    <TableCell>
                                                        {line.quantity_received}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                                {actionsFor(record.status).length > 0 && (
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
                                                                'accept'
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
                                        Attested decision history (
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
                    {procurementRequests.data.length === 0 && (
                        <Card>
                            <CardContent className="grid justify-items-center gap-3 py-10 text-center">
                                <FileCheck2 className="size-8 text-muted-foreground" />
                                <p className="text-muted-foreground">
                                    No purchase requests match the selected
                                    filters.
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
                <DataPagination links={procurementRequests.links} />
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
                        ? `${selectedAction.action.label}: ${selectedAction.record.pr_no}`
                        : 'Procurement action'
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
            >
                {selectedAction?.action.action === 'order' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="procurement-mode"
                            label="Procurement mode"
                            error={actionForm.errors.procurement_mode}
                        >
                            <Input
                                id="procurement-mode"
                                className="min-h-11"
                                value={actionForm.data.procurement_mode}
                                onChange={(event) =>
                                    actionForm.setData(
                                        'procurement_mode',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    actionForm.errors.procurement_mode,
                                )}
                            />
                        </Field>
                        <Field
                            id="purchase-order-no"
                            label="Purchase order number"
                            error={actionForm.errors.purchase_order_no}
                        >
                            <Input
                                id="purchase-order-no"
                                className="min-h-11"
                                value={actionForm.data.purchase_order_no}
                                onChange={(event) =>
                                    actionForm.setData(
                                        'purchase_order_no',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    actionForm.errors.purchase_order_no,
                                )}
                            />
                        </Field>
                    </div>
                )}
                {selectedAction?.action.action === 'record_delivery' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="delivery-reference"
                            label="Delivery reference"
                            error={actionForm.errors.delivery_reference}
                        >
                            <Input
                                id="delivery-reference"
                                className="min-h-11"
                                value={actionForm.data.delivery_reference}
                                onChange={(event) =>
                                    actionForm.setData(
                                        'delivery_reference',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    actionForm.errors.delivery_reference,
                                )}
                            />
                        </Field>
                        <Field
                            id="received-at"
                            label="Date received"
                            error={actionForm.errors.received_at}
                        >
                            <Input
                                id="received-at"
                                type="date"
                                className="min-h-11"
                                value={actionForm.data.received_at}
                                onChange={(event) =>
                                    actionForm.setData(
                                        'received_at',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    actionForm.errors.received_at,
                                )}
                            />
                        </Field>
                        <Field
                            id="actual-unit-cost"
                            label="Actual unit cost"
                            error={actionForm.errors.actual_unit_cost}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="actual-unit-cost"
                                type="number"
                                min="0"
                                step="0.01"
                                className="min-h-11"
                                value={actionForm.data.actual_unit_cost}
                                onChange={(event) =>
                                    actionForm.setData(
                                        'actual_unit_cost',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    actionForm.errors.actual_unit_cost,
                                )}
                            />
                        </Field>
                    </div>
                )}
                {selectedAction?.action.action === 'accept' && (
                    <Field
                        id="inspection-acceptance-no"
                        label="Inspection and Acceptance Report number"
                        error={actionForm.errors.inspection_acceptance_no}
                    >
                        <Input
                            id="inspection-acceptance-no"
                            className="min-h-11"
                            value={actionForm.data.inspection_acceptance_no}
                            onChange={(event) =>
                                actionForm.setData(
                                    'inspection_acceptance_no',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(
                                actionForm.errors.inspection_acceptance_no,
                            )}
                        />
                    </Field>
                )}
            </WorkflowActionDialog>
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

ProcurementIndex.layout = {
    breadcrumbs: [{ title: 'Procurement Controls', href: index() }],
};
