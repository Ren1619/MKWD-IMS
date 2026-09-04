import { Head, router, useForm } from '@inertiajs/react';
import {
    ClipboardSignature,
    FileWarning,
    FilterX,
    Printer,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkflowActionDialog } from '@/components/workflow-action-dialog';
import { WorkflowStatus } from '@/components/workflow-status';
import {
    index,
    issue,
    print,
    transition,
} from '@/routes/inventory/accountability';
import type { Paginated } from '@/types/inventory';

type AccountabilityAction = {
    id: number;
    action: string;
    to_status: string;
    remarks: string | null;
    created_at: string;
    actor: { name: string };
};
type AccountabilityDocument = {
    id: number;
    document_no: string;
    document_type: 'PAR' | 'ICS';
    status: string;
    asset_name: string;
    property_number: string | null;
    serial_number: string | null;
    recipient_reference_id: number;
    recipient_name: string;
    recipient_code: string | null;
    acquisition_cost: string;
    issued_at: string;
    actions: AccountabilityAction[];
};
type UndocumentedAsset = {
    inventory_asset_id: number;
    name: string;
    property_number: string | null;
    serial_number: string;
    acquisition_cost: string | null;
    current_custodian: { name: string; code: string | null } | null;
};
type Filters = {
    search?: string;
    status?: string;
    document_type?: string;
    queue?: string;
};
type WorkflowAction = {
    action: string;
    label: string;
    description: string;
    remarksRequired?: boolean;
    destructive?: boolean;
};

const workflowSteps = ['pending_recipient', 'active', 'returned'] as const;
const terminalStatuses = ['superseded', 'cancelled'] as const;
const fieldClassName =
    'min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm';

function statusLabel(status: string): string {
    return status.replaceAll('_', ' ');
}

function nextAction(status: string): string {
    return (
        (
            {
                pending_recipient:
                    'The named custodian must acknowledge receipt, or the property officer must record a witnessed acknowledgment.',
                active: 'Custody is active. Renew after verification or record the physical return of the property.',
                superseded:
                    'Closed by a replacement accountability document. The history remains available.',
                returned:
                    'Completed. The property was returned and custody was closed.',
                cancelled:
                    'Cancelled before activation. See the control history for the reason.',
            } as Record<string, string>
        )[status] ?? 'Review the control history for this document.'
    );
}

function managerActions(status: string): WorkflowAction[] {
    if (status === 'pending_recipient') {
        return [
            {
                action: 'witnessed_acknowledge',
                label: 'Record witnessed acknowledgment',
                description:
                    'Record how the recipient acknowledged the printed form in the presence of an authorized witness.',
                remarksRequired: true,
            },
            {
                action: 'renew',
                label: 'Renew or supersede',
                description:
                    'Close this version and issue a replacement for the current custodian.',
                remarksRequired: true,
            },
            {
                action: 'return',
                label: 'Return property',
                description:
                    'Record the physical return and close the custody assignment.',
                remarksRequired: true,
            },
            {
                action: 'cancel',
                label: 'Cancel and unassign',
                description:
                    'Cancel this unacknowledged document and reverse the custody assignment.',
                remarksRequired: true,
                destructive: true,
            },
        ];
    }

    if (status === 'active') {
        return [
            {
                action: 'renew',
                label: 'Renew or supersede',
                description:
                    'Close this version and issue a replacement after custody verification.',
                remarksRequired: true,
            },
            {
                action: 'return',
                label: 'Return property',
                description:
                    'Record the physical return and close the custody assignment.',
                remarksRequired: true,
            },
        ];
    }

    return [];
}

export default function AccountabilityIndex({
    documents,
    undocumentedAssets,
    canManage,
    currentReferenceId,
    capitalizationThreshold,
    filters,
}: {
    documents: Paginated<AccountabilityDocument>;
    undocumentedAssets: UndocumentedAsset[];
    canManage: boolean;
    currentReferenceId: number | null;
    capitalizationThreshold: number;
    filters: Filters;
}) {
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        document_type: filters.document_type ?? '',
        queue: filters.queue ?? '',
    });
    const [selectedAction, setSelectedAction] = useState<{
        document: AccountabilityDocument;
        action: WorkflowAction;
    } | null>(null);
    const [selectedAsset, setSelectedAsset] =
        useState<UndocumentedAsset | null>(null);
    const actionForm = useForm({ action: '', attested: true, remarks: '' });
    const issueForm = useForm({ attested: true, remarks: '' });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(index.url(), filterValues, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function clearFilters() {
        setFilterValues({
            search: '',
            status: '',
            document_type: '',
            queue: '',
        });
        router.get(index.url(), {}, { preserveState: true, replace: true });
    }

    function openAction(
        document: AccountabilityDocument,
        action: WorkflowAction,
    ) {
        actionForm.clearErrors();
        actionForm.setData({
            action: action.action,
            attested: true,
            remarks: '',
        });
        setSelectedAction({ document, action });
    }

    function confirmAction() {
        if (!selectedAction) {
            return;
        }

        actionForm.patch(transition.url(selectedAction.document.id), {
            preserveScroll: true,
            onSuccess: () => setSelectedAction(null),
        });
    }

    function confirmIssue() {
        if (!selectedAsset) {
            return;
        }

        issueForm.post(issue.url(selectedAsset.inventory_asset_id), {
            preserveScroll: true,
            onSuccess: () => setSelectedAsset(null),
        });
    }

    return (
        <>
            <Head title="Property Accountability" />
            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <p className="text-sm font-medium text-primary">
                        PAR and ICS controls
                    </p>
                    <h1 className="text-2xl font-semibold sm:text-3xl">
                        Property accountability
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        PPE at ₱{capitalizationThreshold.toLocaleString()} or
                        more uses PAR; lower-valued durable property uses ICS.
                    </p>
                </div>

                {canManage && undocumentedAssets.length > 0 && (
                    <Card className="border-amber-500/40">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileWarning className="size-5 text-amber-600" />{' '}
                                Assigned assets missing a current document
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {undocumentedAssets.map((asset) => (
                                <div
                                    key={asset.inventory_asset_id}
                                    className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_auto] sm:items-center"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {asset.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {asset.property_number ??
                                                asset.serial_number}{' '}
                                            ·{' '}
                                            {asset.current_custodian?.name ??
                                                'Unknown custodian'}{' '}
                                            · ₱
                                            {Number(
                                                asset.acquisition_cost ?? 0,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <Button
                                        className="min-h-11 w-full sm:w-auto"
                                        onClick={() => {
                                            issueForm.clearErrors();
                                            issueForm.reset();
                                            setSelectedAsset(asset);
                                        }}
                                    >
                                        Issue{' '}
                                        {Number(asset.acquisition_cost) >=
                                        capitalizationThreshold
                                            ? 'PAR'
                                            : 'ICS'}
                                    </Button>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Accountability register</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={applyFilters}
                            className="grid gap-3 lg:grid-cols-[minmax(0,2fr)_1fr_1fr_1fr_auto]"
                        >
                            <Field id="accountability-search" label="Search">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-3.5 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        id="accountability-search"
                                        className="min-h-11 pl-9"
                                        placeholder="Document, property, recipient…"
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
                            <Field
                                id="accountability-type"
                                label="Document type"
                            >
                                <select
                                    id="accountability-type"
                                    className={fieldClassName}
                                    value={filterValues.document_type}
                                    onChange={(event) =>
                                        setFilterValues({
                                            ...filterValues,
                                            document_type: event.target.value,
                                        })
                                    }
                                >
                                    <option value="">PAR and ICS</option>
                                    <option value="PAR">PAR</option>
                                    <option value="ICS">ICS</option>
                                </select>
                            </Field>
                            <Field id="accountability-status" label="Status">
                                <select
                                    id="accountability-status"
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
                            <Field id="accountability-queue" label="Queue">
                                <select
                                    id="accountability-queue"
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
                                        Current custody
                                    </option>
                                    <option value="completed">
                                        Closed documents
                                    </option>
                                </select>
                            </Field>
                            <div className="flex gap-2 lg:items-end">
                                <Button className="min-h-11 flex-1 lg:flex-none">
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
                            {documents.total.toLocaleString()} document
                            {documents.total === 1 ? '' : 's'} found.
                        </p>
                    </CardContent>
                </Card>

                <div className="grid gap-4">
                    {documents.data.map((document) => {
                        const canAcknowledge =
                            document.status === 'pending_recipient' &&
                            currentReferenceId ===
                                document.recipient_reference_id;

                        return (
                            <Card key={document.id}>
                                <CardHeader>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <ClipboardSignature className="size-5" />{' '}
                                            {document.document_no}
                                        </CardTitle>
                                        <div className="flex gap-2">
                                            <span className="rounded-full border px-3 py-1 text-xs font-medium">
                                                {document.document_type}
                                            </span>
                                            <span className="rounded-full border px-3 py-1 text-xs capitalize">
                                                {statusLabel(document.status)}
                                            </span>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <WorkflowStatus
                                        steps={workflowSteps}
                                        current={document.status}
                                        terminal={terminalStatuses}
                                    />
                                    <div className="rounded-lg bg-muted/60 p-3 text-sm">
                                        <p className="font-medium">
                                            What happens next
                                        </p>
                                        <p className="text-muted-foreground">
                                            {nextAction(document.status)}
                                        </p>
                                    </div>
                                    <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <dt className="font-medium">
                                                Property
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {document.asset_name}
                                                <br />
                                                {document.property_number ??
                                                    document.serial_number}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium">
                                                Recipient
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {document.recipient_name}
                                                <br />
                                                {document.recipient_code ??
                                                    'No employee number'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium">
                                                Acquisition cost
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                ₱
                                                {Number(
                                                    document.acquisition_cost,
                                                ).toLocaleString()}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="font-medium">
                                                Issued
                                            </dt>
                                            <dd className="text-muted-foreground">
                                                {new Date(
                                                    document.issued_at,
                                                ).toLocaleString()}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="grid gap-2 sm:flex sm:flex-wrap">
                                        <Button
                                            nativeButton={false}
                                            className="min-h-11 w-full sm:w-auto"
                                            variant="outline"
                                            render={
                                                <a
                                                    href={print.url(
                                                        document.id,
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                />
                                            }
                                        >
                                            <Printer /> Print form
                                        </Button>
                                        {canAcknowledge && (
                                            <Button
                                                className="min-h-11 w-full sm:w-auto"
                                                onClick={() =>
                                                    openAction(document, {
                                                        action: 'acknowledge',
                                                        label: 'Acknowledge custody',
                                                        description:
                                                            'Confirm that you received and accept responsibility for this property.',
                                                    })
                                                }
                                            >
                                                Acknowledge custody
                                            </Button>
                                        )}
                                        {canManage &&
                                            managerActions(document.status).map(
                                                (action) => (
                                                    <Button
                                                        key={action.action}
                                                        className="min-h-11 w-full sm:w-auto"
                                                        variant={
                                                            action.destructive
                                                                ? 'destructive'
                                                                : 'outline'
                                                        }
                                                        onClick={() =>
                                                            openAction(
                                                                document,
                                                                action,
                                                            )
                                                        }
                                                    >
                                                        {action.label}
                                                    </Button>
                                                ),
                                            )}
                                    </div>
                                    <details>
                                        <summary className="flex min-h-11 cursor-pointer items-center text-sm font-medium">
                                            Digital control history (
                                            {document.actions.length})
                                        </summary>
                                        <div className="mt-2 grid gap-2">
                                            {document.actions.map((action) => (
                                                <div
                                                    key={action.id}
                                                    className="rounded-lg border p-3 text-xs"
                                                >
                                                    <p>
                                                        <b>
                                                            {action.actor.name}
                                                        </b>{' '}
                                                        —{' '}
                                                        {statusLabel(
                                                            action.action,
                                                        )}{' '}
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
                        );
                    })}
                    {documents.data.length === 0 && (
                        <Card>
                            <CardContent className="grid justify-items-center gap-3 py-10 text-center">
                                <ClipboardSignature className="size-8 text-muted-foreground" />
                                <p className="text-muted-foreground">
                                    No accountability documents match the
                                    selected filters.
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
                <DataPagination links={documents.links} />
            </div>

            <WorkflowActionDialog
                key={
                    selectedAction
                        ? `${selectedAction.document.id}-${selectedAction.action.action}`
                        : 'closed-action'
                }
                open={selectedAction !== null}
                onOpenChange={(open) => !open && setSelectedAction(null)}
                title={
                    selectedAction
                        ? `${selectedAction.action.label}: ${selectedAction.document.document_no}`
                        : 'Accountability action'
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
            <WorkflowActionDialog
                key={
                    selectedAsset
                        ? `issue-${selectedAsset.inventory_asset_id}`
                        : 'closed-issue'
                }
                open={selectedAsset !== null}
                onOpenChange={(open) => !open && setSelectedAsset(null)}
                title={
                    selectedAsset
                        ? `Issue ${Number(selectedAsset.acquisition_cost) >= capitalizationThreshold ? 'PAR' : 'ICS'}: ${selectedAsset.name}`
                        : 'Issue accountability document'
                }
                description="Review the property and current custodian before issuing this versioned accountability document."
                confirmLabel="Issue document"
                processing={issueForm.processing}
                remarks={issueForm.data.remarks}
                onRemarksChange={(remarks) =>
                    issueForm.setData('remarks', remarks)
                }
                onConfirm={confirmIssue}
                error={issueForm.errors.attested}
            />
        </>
    );
}

function Field({
    id,
    label,
    children,
}: {
    id: string;
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError />
        </div>
    );
}

AccountabilityIndex.layout = {
    breadcrumbs: [{ title: 'Property Accountability', href: index() }],
};
