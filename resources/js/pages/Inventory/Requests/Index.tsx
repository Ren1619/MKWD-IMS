import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardList, FilterX, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { DataPagination } from '@/components/data-pagination';

import InputError from '@/components/input-error';

import type { ResponsibilityCenterOption } from '@/components/responsibility-center-combobox';
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

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { WorkflowActionDialog } from '@/components/workflow-action-dialog';
import { WorkflowStatus } from '@/components/workflow-status';
import { index, transition } from '@/routes/inventory/requests';
import type { Paginated } from '@/types/inventory';
import { IssuanceLiquidationDialog } from './IssuanceLiquidation';
import type {
    IssuanceAllocation,
    LiquidationDocument,
} from './IssuanceLiquidation';
import { IssuanceRequestForm } from './IssuanceRequestForm';
import type { IssuanceContext } from './IssuanceRequestForm';
import { ReturnSlipDialog } from './ReturnSlipDialog';

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
    isf_no: string | null;
    return_slip_no: string | null;
    return_slip_date: string | null;
    requester_name: string;
    office_name: string | null;
    purpose: string;
    status: string;
    submitted_at: string;
    lines: RequestLine[];
    actions: ActionHistory[];
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
                action: 'review',
                label: 'Review stock availability',
                description:
                    'Recheck expiry and recalculate usable stock reservations.',
            },
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
    issuanceAllocations,
    liquidationDocuments,
    items,
    canManage,

    responsibilityCenters,
    issuanceContext,
    filters,
}: {
    requests: Paginated<RequestRecord>;
    issuanceAllocations: IssuanceAllocation[];
    liquidationDocuments: LiquidationDocument[];
    items: Item[];
    canManage: boolean;

    responsibilityCenters: ResponsibilityCenterOption[];
    filters: Filters;
    issuanceContext: IssuanceContext;
}) {
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        queue: filters.queue ?? '',
    });
    const [requestDialogOpen, setRequestDialogOpen] = useState(false);
    const [liquidationRequestId, setLiquidationRequestId] = useState<
        number | null
    >(null);
    const [returnSlipRequest, setReturnSlipRequest] =
        useState<RequestRecord | null>(null);
    const [selectedRequest, setSelectedRequest] =
        useState<RequestRecord | null>(null);
    const [selectedAction, setSelectedAction] = useState<{
        record: RequestRecord;
        action: WorkflowAction;
    } | null>(null);
    const actionForm = useForm({ action: '', attested: true, remarks: '' });

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
                </div>

                <IssuanceRequestForm
                    open={requestDialogOpen}
                    onOpenChange={setRequestDialogOpen}
                    context={issuanceContext}
                    items={items}
                    chargeCodes={responsibilityCenters}
                />
                <IssuanceLiquidationDialog
                    key={liquidationRequestId ?? 'closed'}
                    open={liquidationRequestId !== null}
                    onOpenChange={(open) =>
                        !open && setLiquidationRequestId(null)
                    }
                    supplyRequestId={liquidationRequestId ?? undefined}
                    allocations={issuanceAllocations}
                    documents={liquidationDocuments}
                    canManage={canManage}
                />
                {returnSlipRequest && (
                    <ReturnSlipDialog
                        key={returnSlipRequest.id}
                        request={returnSlipRequest}
                        onClose={() => setReturnSlipRequest(null)}
                    />
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Request queue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <form
                                onSubmit={applyFilters}
                                className="grid w-full max-w-4xl gap-3 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]"
                            >
                                <Field id="request-search" label="Search">
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-3.5 left-3 size-4 text-muted-foreground" />
                                        <Input
                                            id="request-search"
                                            className="min-h-11 pl-9"
                                            maxLength={100}
                                            placeholder="ISF no., requester, department, item…"
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
                                    <FormSelect
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
                                    </FormSelect>
                                </Field>
                                <Field id="request-queue" label="Queue">
                                    <FormSelect
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
                                        <option value="completed">
                                            Completed
                                        </option>
                                    </FormSelect>
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
                            <div className="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                                <Button
                                    type="button"
                                    className="min-h-11 w-full shrink-0 lg:w-auto"
                                    onClick={() => setRequestDialogOpen(true)}
                                >
                                    Issuance Request Form
                                </Button>
                            </div>
                        </div>
                        <p
                            role="status"
                            className="mt-3 text-sm text-muted-foreground"
                        >
                            {requests.total.toLocaleString()} request
                            {requests.total === 1 ? '' : 's'} found.
                        </p>
                    </CardContent>
                </Card>

                <Card className="hidden lg:flex">
                    <CardContent>
                        <Table>
                            <caption className="sr-only">
                                Supply request queue
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col">ISF No.</TableHead>
                                    <TableHead scope="col">Requester</TableHead>
                                    <TableHead scope="col">Office</TableHead>
                                    <TableHead scope="col">Submitted</TableHead>
                                    <TableHead
                                        scope="col"
                                        className="text-center"
                                    >
                                        Items
                                    </TableHead>
                                    <TableHead scope="col">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {requests.data.map((record) => (
                                    <Tooltip key={record.id}>
                                        <TooltipTrigger
                                            render={
                                                <TableRow
                                                    role="button"
                                                    tabIndex={0}
                                                    aria-haspopup="dialog"
                                                    aria-label={`View details for ${record.isf_no ?? record.ris_no}`}
                                                    className="cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                                    onClick={() =>
                                                        setSelectedRequest(
                                                            record,
                                                        )
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                                'Enter' ||
                                                            event.key === ' '
                                                        ) {
                                                            event.preventDefault();
                                                            setSelectedRequest(
                                                                record,
                                                            );
                                                        }
                                                    }}
                                                />
                                            }
                                        >
                                            <TableCell className="font-medium">
                                                {record.isf_no ?? record.ris_no}
                                            </TableCell>
                                            <TableCell>
                                                {record.requester_name}
                                            </TableCell>
                                            <TableCell>
                                                {record.office_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {new Date(
                                                    record.submitted_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {record.lines.length}
                                            </TableCell>
                                            <TableCell>
                                                <span className="inline-flex rounded-full border px-3 py-1 text-xs font-medium capitalize">
                                                    {statusLabel(record.status)}
                                                </span>
                                            </TableCell>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Click for more info
                                        </TooltipContent>
                                    </Tooltip>
                                ))}
                                {requests.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="h-32 text-center text-muted-foreground"
                                        >
                                            No requests match the selected
                                            filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:hidden">
                    {requests.data.map((record) => (
                        <Card key={record.id}>
                            <CardHeader>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList className="size-5" />{' '}
                                        {record.isf_no ?? record.ris_no}
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
                                            {record.isf_no ?? record.ris_no}
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
            <Dialog
                open={selectedRequest !== null}
                onOpenChange={(open) => !open && setSelectedRequest(null)}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-5xl">
                    {selectedRequest && (
                        <div className="grid gap-5">
                            <DialogHeader className="pr-8">
                                <div className="flex flex-wrap items-center gap-2">
                                    <DialogTitle>
                                        {selectedRequest.isf_no ??
                                            selectedRequest.ris_no}
                                    </DialogTitle>
                                    <span className="rounded-full border px-3 py-1 text-xs font-medium capitalize">
                                        {statusLabel(selectedRequest.status)}
                                    </span>
                                </div>
                                <DialogDescription>
                                    Submitted by{' '}
                                    {selectedRequest.requester_name}
                                    {selectedRequest.office_name
                                        ? ` — ${selectedRequest.office_name}`
                                        : ''}
                                    .
                                </DialogDescription>
                            </DialogHeader>

                            <WorkflowStatus
                                steps={workflowSteps}
                                current={selectedRequest.status}
                                terminal={terminalStatuses}
                            />

                            <div className="rounded-lg bg-muted/60 p-3 text-sm">
                                <p className="font-medium">What happens next</p>
                                <p className="text-muted-foreground">
                                    {nextAction(selectedRequest.status)}
                                </p>
                            </div>

                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="font-medium">Purpose</dt>
                                    <dd className="text-muted-foreground">
                                        {selectedRequest.purpose}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-medium">Submitted</dt>
                                    <dd className="text-muted-foreground">
                                        {new Date(
                                            selectedRequest.submitted_at,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                            </dl>

                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <caption className="sr-only">
                                        Items requested under{' '}
                                        {selectedRequest.isf_no ??
                                            selectedRequest.ris_no}
                                    </caption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col">
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
                                        {selectedRequest.lines.map((line) => (
                                            <TableRow key={line.id}>
                                                <TableCell className="font-medium">
                                                    {line.item_name}
                                                    {line.is_new_item
                                                        ? ' (new)'
                                                        : ''}
                                                </TableCell>
                                                <TableCell>
                                                    {line.quantity_requested}{' '}
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

                            {canManage && (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            setLiquidationRequestId(
                                                selectedRequest.id,
                                            );
                                            setSelectedRequest(null);
                                        }}
                                    >
                                        RIS liquidation
                                    </Button>
                                    <Button
                                        variant="outline"
                                        disabled={
                                            !selectedRequest.lines.some(
                                                (line) =>
                                                    line.quantity_released > 0,
                                            )
                                        }
                                        onClick={() => {
                                            setReturnSlipRequest(
                                                selectedRequest,
                                            );
                                            setSelectedRequest(null);
                                        }}
                                    >
                                        Return slip
                                    </Button>
                                </div>
                            )}
                            {canManage &&
                                actionsFor(selectedRequest.status).length >
                                    0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {actionsFor(selectedRequest.status).map(
                                            (action) => (
                                                <Button
                                                    key={action.action}
                                                    className="min-h-11"
                                                    variant={
                                                        action.destructive
                                                            ? 'destructive'
                                                            : action.action ===
                                                                'release'
                                                              ? 'default'
                                                              : 'outline'
                                                    }
                                                    onClick={() => {
                                                        setSelectedRequest(
                                                            null,
                                                        );
                                                        openAction(
                                                            selectedRequest,
                                                            action,
                                                        );
                                                    }}
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
                                    {selectedRequest.actions.length})
                                </summary>
                                <div className="mt-2 grid gap-2">
                                    {selectedRequest.actions.map((action) => (
                                        <div
                                            key={action.id}
                                            className="rounded-lg border p-3 text-xs"
                                        >
                                            <p>
                                                <b>{action.actor.name}</b> —{' '}
                                                {statusLabel(action.action)} to{' '}
                                                {statusLabel(action.to_status)}
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
                        </div>
                    )}
                </DialogContent>
            </Dialog>
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
                        ? `${selectedAction.action.label}: ${selectedAction.record.isf_no ?? selectedAction.record.ris_no}`
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
