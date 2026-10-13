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
import { DatePicker } from '@/components/ui/date-picker';
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
import { useAppPage } from '@/hooks/use-app-page';
import {
    create as createDelivery,
    index as deliveriesIndex,
} from '@/routes/inventory/deliveries';
import * as jobOrdersRoutes from '@/routes/inventory/job_orders';
import {
    index,
    store,
    transition,
    update,
} from '@/routes/inventory/procurement';
import * as purchaseRequestsRoutes from '@/routes/inventory/purchase_requests';
import { index as requestsIndex } from '@/routes/inventory/requests';
import type { Paginated } from '@/types/inventory';
import type { HrisReference } from '@/types/inventory';
import {
    DocumentChecklist,
    IntakeFields,
    PreparationFields,
    SupportingDocuments,
    emptyIntake,
    emptyPreparation,
} from './Preprocurement';
import type {
    Attachment,
    Categories,
    IntakeDetails,
    PreprocurementDetails,
} from './Preprocurement';
import { RequestForm } from './RequestForm';
import type { ChargeAccount, RequestFormContext } from './RequestForm';

type Item = {
    inventory_item_id: number;
    name: string;
    unit_of_measure: string;
    quantity: number;
    reorder_point: number;
    reorder_quantity: number | null;
};
type Series = { inv_series_cat_id: number; name: string };
type AssetCategory = {
    inv_asset_cat_id: number;
    name: string;
    subcategories: {
        inventory_asset_subcategory_id: number;
        name: string;
    }[];
};
type ProcurementLine = {
    inventory_item_id: number | null;
    specifications: string | null;
    id: number;
    item_kind: 'consumable' | 'property';
    series_category_id: number | null;
    asset_category_id: number | null;
    asset_subcategory_id: number | null;
    item_name: string;
    unit_of_measure: string;
    quantity: number;
    estimated_unit_cost: string;
    actual_unit_cost: string | null;
    quantity_received: number;
    item: null | { name: string };
    asset_category: null | { name: string };
    asset_subcategory: null | { name: string };
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
    deliveries_count: number;
    deleted_at: string | null;
    can_delete: boolean;
    can_archive: boolean;
    created_by_user_id: number;
    document_kind: string;
    procurement_category: string | null;
    preprocurement: PreprocurementDetails | null;
    attachments: Attachment[];
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
    responsibility_center_code: string | null;
    required_at: string | null;
    app_cse_classification: string | null;
    supply_request: { id: number; ris_no: string } | null;
    creator: { name: string };
    lines: ProcurementLine[];
    actions: ActionHistory[];
};
type ProcurementFormLine = {
    client_key: string;
    inventory_item_id: string;
    item_kind: 'consumable' | 'property';
    series_category_id: string;
    asset_category_id: string;
    asset_subcategory_id: string;
    item_name: string;
    specifications: string;
    unit_of_measure: string;
    quantity: number;
    estimated_unit_cost: string;
};
type ProcurementForm = {
    document_kind: 'PR' | 'JO';
    procurement_category: string;
    preprocurement: IntakeDetails;
    type: 'replenishment' | 'new_item' | 'mixed';
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
    'intake',
    'for_document_review',
    'returned_for_compliance',
    'ready_for_preparation',
    'for_signatures',
    'draft',
    'for_budget_review',
    'for_approval',
    'approved',
    'forwarded_to_procurement',
    'ordered',
    'delivered',
    'accepted',
    'endorsed',
] as const;
const terminalStatuses = ['rejected', 'cancelled'] as const;
function stepsFor(record: ProcurementRecord): readonly string[] {
    if (!record.preprocurement) {
        return [
            'draft',
            'for_budget_review',
            'for_approval',
            'approved',
            'forwarded_to_procurement',
            'ordered',
            'delivered',
            'accepted',
        ];
    }

    return [
        'intake',
        'for_document_review',
        'ready_for_preparation',
        'for_signatures',
        'for_approval',
        'approved',
        ...(record.document_kind === 'JO'
            ? ['endorsed']
            : ['forwarded_to_procurement', 'ordered', 'delivered', 'accepted']),
    ];
}
const fieldClassName =
    'min-h-11 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm';

function emptyLine(): ProcurementFormLine {
    return {
        client_key: crypto.randomUUID(),
        inventory_item_id: '',
        item_kind: 'consumable',
        series_category_id: '',
        asset_category_id: '',
        asset_subcategory_id: '',
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
                intake: 'Upload supporting documents, then submit the approved requisition for review.',
                for_document_review:
                    'Another inventory manager checks the category-specific attachments.',
                returned_for_compliance:
                    'Correct the intake information and upload missing or corrected documents, then resubmit.',
                ready_for_preparation:
                    'Prepare the PR or JO and assign the required signatories.',
                for_signatures:
                    'The assigned officers sign in sequence: prepared by, recommending approval, then countersignature.',
                endorsed:
                    'Job order endorsed to Procurement. Subsequent service processing is outside this workflow.',
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
                    'Inspect the delivery and record the IAR before posting it to stock or the property register.',
                accepted:
                    'Completed. Consumables are in stock and durable items are in the property register.',
                rejected:
                    'Closed as rejected. See the decision history for the reason.',
                cancelled:
                    'Closed as cancelled. See the decision history for the reason.',
            } as Record<string, string>
        )[status] ?? 'Review the record and its decision history.'
    );
}

function availableActionsFor(record: ProcurementRecord): WorkflowAction[] {
    const status = record.status;
    const label = record.document_kind ?? 'PR';

    if (record.preprocurement && status === 'approved') {
        return [
            {
                action: 'forward',
                label: 'Endorse to Procurement',
                description:
                    'Record the recipient and endorsement of the approved PR/JO.',
            },
            {
                action: 'cancel',
                label: `Cancel ${label}`,
                description: 'Record why this document is being cancelled.',
                remarksRequired: true,
                destructive: true,
            },
        ];
    }

    const intakeActions: Record<string, WorkflowAction[]> = {
        intake: [
            {
                action: 'submit_intake',
                label: 'Submit for document review',
                description:
                    'Submit the approved requisition and its supporting records.',
            },
        ],
        returned_for_compliance: [
            {
                action: 'submit_intake',
                label: 'Resubmit for review',
                description:
                    'Correct the intake details and confirm that missing documents have been uploaded.',
            },
        ],
        for_document_review: [
            {
                action: 'verify_documents',
                label: 'Verify complete documents',
                description:
                    'Check every supporting document before allowing PR/JO preparation.',
            },
            {
                action: 'return_for_compliance',
                label: 'Return for compliance',
                description:
                    'Return the incomplete requisition to the end user with reasons.',
                remarksRequired: true,
            },
        ],
        ready_for_preparation: [
            {
                action: 'prepare_document',
                label: `Prepare ${label}`,
                description:
                    'Encode WI–008 details and assign the signature route. The document number will be generated on preparation.',
            },
        ],
        for_signatures: [
            {
                action: 'sign',
                label: 'Sign current step',
                description:
                    'Only the assigned officer for the next signature may confirm this step.',
            },
        ],
    };

    if (intakeActions[status]) {
        return [
            ...intakeActions[status],
            {
                action: 'cancel',
                label: 'Cancel requisition',
                description: 'Close this requisition and record the reason.',
                remarksRequired: true,
                destructive: true,
            },
        ];
    }

    const reject: WorkflowAction = {
        action: 'reject',
        label: record.preprocurement
            ? `Disapprove and cancel ${label}`
            : 'Reject PR',
        description: 'Reject this request and record the decision basis.',
        remarksRequired: true,
        destructive: true,
    };
    const cancel: WorkflowAction = {
        action: 'cancel',
        label: `Cancel ${label}`,
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
                        label: `Approve ${label}`,
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
                            'Record the IAR and post accepted lines to stock or the property register.',
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
    assetCategories,
    filters,
    procurementCategories,
    signatories,
    moduleKind = null,
    requestFormContext,
    chargeAccounts,
}: {
    procurementRequests: Paginated<ProcurementRecord>;
    items: Item[];
    seriesCategories: Series[];
    assetCategories: AssetCategory[];
    filters: Filters;
    procurementCategories: Categories;
    signatories: HrisReference[];
    moduleKind?: 'PR' | 'JO' | null;
    requestFormContext: RequestFormContext;
    chargeAccounts: ChargeAccount[];
}) {
    const moduleRoutes =
        moduleKind === 'JO'
            ? jobOrdersRoutes
            : moduleKind === 'PR'
              ? purchaseRequestsRoutes
              : { index, store, update, transition };
    const moduleTitle =
        moduleKind === 'PR'
            ? 'Purchase Requests'
            : moduleKind === 'JO'
              ? 'Job Orders'
              : 'Requests';
    const addLabel = 'Request Form';
    const [requestFormOpen, setRequestFormOpen] = useState(false);
    const lifecycleForm = useForm({ remarks: '' });
    const [managementAction, setManagementAction] = useState<{
        record: ProcurementRecord;
        action: 'delete' | 'archive' | 'restore';
    } | null>(null);
    function manageRecord() {
        if (!managementAction) {
            return;
        }

        const routes =
            managementAction.record.document_kind === 'JO'
                ? jobOrdersRoutes
                : purchaseRequestsRoutes;
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setManagementAction(null);
                setSelectedProcurement(null);
                lifecycleForm.reset();
            },
        };

        if (managementAction.action === 'delete') {
            lifecycleForm.delete(
                routes.destroy.url(managementAction.record.id),
                options,
            );
        } else {
            lifecycleForm.patch(
                (managementAction.action === 'archive'
                    ? routes.archive
                    : routes.restore
                ).url(managementAction.record.id),
                options,
            );
        }
    }
    const currentUserId = useAppPage().props.auth.user.id;
    function actionsFor(record: ProcurementRecord): WorkflowAction[] {
        if (record.deleted_at) {
            return [];
        }

        return availableActionsFor(record).filter((action) => {
            if (
                (action.action === 'record_delivery' &&
                    record.status === 'ordered') ||
                (record.deliveries_count > 0 &&
                    ['record_delivery', 'accept', 'cancel'].includes(
                        action.action,
                    ))
            ) {
                return false;
            }

            if (
                record.created_by_user_id === currentUserId &&
                [
                    'verify_documents',
                    'budget_review',
                    'approve',
                    'accept',
                ].includes(action.action)
            ) {
                return false;
            }

            if (!record.preprocurement) {
                return true;
            }

            if (['approve', 'reject'].includes(action.action)) {
                return (
                    Number(record.preprocurement.gm_user_id) === currentUserId
                );
            }

            if (action.action === 'sign') {
                const stage = [
                    'prepared_by_user_id',
                    'recommending_user_id',
                    'countersigning_user_id',
                ][
                    record.preprocurement.signatures?.length ?? 0
                ] as keyof typeof emptyPreparation;

                return Number(record.preprocurement[stage]) === currentUserId;
            }

            return true;
        });
    }
    const errorSummary = useRef<HTMLDivElement>(null);
    const [filterValues, setFilterValues] = useState({
        search: filters.search ?? '',
        status: filters.status ?? '',
        queue: filters.queue ?? '',
    });
    const [prepareDialogOpen, setPrepareDialogOpen] = useState(false);
    const [editingRequisitionId, setEditingRequisitionId] = useState<
        number | null
    >(null);
    const [selectedProcurementSelection, setSelectedProcurement] =
        useState<ProcurementRecord | null>(null);
    const selectedProcurement =
        procurementRequests.data.find(
            (record) => record.id === selectedProcurementSelection?.id,
        ) ?? selectedProcurementSelection;
    const [selectedAction, setSelectedAction] = useState<{
        record: ProcurementRecord;
        action: WorkflowAction;
    } | null>(null);
    const [expandedNewItemKeys, setExpandedNewItemKeys] = useState<Set<string>>(
        new Set(),
    );
    const form = useForm<ProcurementForm>({
        document_kind: moduleKind ?? 'PR',
        procurement_category:
            moduleKind === 'JO' ? 'other_services' : 'supplies',
        preprocurement: { ...emptyIntake },
        type: moduleKind === 'JO' ? 'new_item' : 'replenishment',
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
        intake_details: { ...emptyIntake },
        document_waivers: {} as Record<string, string>,
        preparation: { ...emptyPreparation },
        endorsement_recipient: '',
        action: '',
        attested: true,
        remarks: '',
        procurement_mode: '',
        purchase_order_no: '',
        inspection_acceptance_no: '',
        delivery_reference: '',
        received_at: '',
        funding_source: '',
        responsibility_center_code: '',
        ppmp_reference: '',
        app_reference: '',
        line_categories: [] as {
            line_id: number;
            series_category_id: string;
        }[],
        line_estimates: [] as {
            line_id: number;
            estimated_unit_cost: string;
        }[],
        line_receipts: [] as {
            line_id: number;
            quantity_received: number;
            actual_unit_cost: string;
        }[],
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
        form.setData('lines', lines);
    }

    function changeRequestType(type: ProcurementForm['type']) {
        const existingLines = form.data.lines.filter(
            (line) => line.inventory_item_id !== '',
        );
        let newLines = form.data.lines.filter(
            (line) => line.inventory_item_id === '',
        );

        if (type !== 'replenishment' && newLines.length === 0) {
            const line = emptyLine();

            newLines = [line];
            setExpandedNewItemKeys((current) =>
                new Set(current).add(line.client_key),
            );
        }

        if (type === 'replenishment') {
            setExpandedNewItemKeys(new Set());
        }

        form.setData({
            ...form.data,
            type,
            lines:
                type === 'replenishment'
                    ? existingLines
                    : type === 'new_item'
                      ? newLines
                      : [...existingLines, ...newLines],
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
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setExpandedNewItemKeys(new Set());
                setPrepareDialogOpen(false);
                setEditingRequisitionId(null);
            },
            onError: () =>
                requestAnimationFrame(() => errorSummary.current?.focus()),
        };

        if (editingRequisitionId) {
            form.patch(moduleRoutes.update.url(editingRequisitionId), options);
        } else {
            form.post(moduleRoutes.store.url(), options);
        }
    }

    function editRequisition(record: ProcurementRecord) {
        form.clearErrors();
        const lines = record.lines.map((line) => ({
            ...emptyLine(),
            inventory_item_id: line.inventory_item_id?.toString() ?? '',
            item_kind: line.item_kind,
            series_category_id: line.series_category_id?.toString() ?? '',
            asset_category_id: line.asset_category_id?.toString() ?? '',
            asset_subcategory_id: line.asset_subcategory_id?.toString() ?? '',
            item_name: line.item_name,
            specifications: line.specifications ?? '',
            unit_of_measure: line.unit_of_measure,
            quantity: line.quantity,
            estimated_unit_cost: line.estimated_unit_cost,
        }));
        form.setData({
            document_kind: record.document_kind as 'PR' | 'JO',
            procurement_category: record.procurement_category ?? 'supplies',
            preprocurement: {
                ...emptyIntake,
                ...record.preprocurement,
            } as IntakeDetails,
            type: record.type as ProcurementForm['type'],
            source: record.source,
            purpose: record.purpose,
            funding_source: record.funding_source ?? '',
            responsibility_center_code: record.responsibility_center_code ?? '',
            ppmp_reference: record.ppmp_reference ?? '',
            app_reference: record.app_reference ?? '',
            app_cse_classification: record.app_cse_classification ?? '',
            required_at: record.required_at?.slice(0, 10) ?? '',
            lines,
        });
        setExpandedNewItemKeys(
            new Set(
                lines
                    .filter((line) => !line.inventory_item_id)
                    .map((line) => line.client_key),
            ),
        );
        setEditingRequisitionId(record.id);
        setSelectedProcurement(null);
        setPrepareDialogOpen(true);
    }

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(moduleRoutes.index.url(), filterValues, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function clearFilters() {
        setFilterValues({ search: '', status: '', queue: '' });
        router.get(
            moduleRoutes.index.url(),
            {},
            { preserveState: true, replace: true },
        );
    }

    function openAction(record: ProcurementRecord, action: WorkflowAction) {
        actionForm.clearErrors();
        actionForm.setData({
            intake_details: {
                ...emptyIntake,
                ...Object.fromEntries(
                    Object.keys(emptyIntake).map((key) => [
                        key,
                        record.preprocurement?.[key as keyof IntakeDetails] ??
                            emptyIntake[key as keyof IntakeDetails],
                    ]),
                ),
            } as IntakeDetails,
            document_waivers: record.preprocurement?.document_waivers ?? {},
            preparation: { ...emptyPreparation },
            endorsement_recipient: '',
            action: action.action,
            attested: true,
            remarks: '',
            procurement_mode:
                record.status === 'forwarded_to_procurement' ? '' : '',
            purchase_order_no: record.purchase_order_no ?? '',
            inspection_acceptance_no: record.inspection_acceptance_no ?? '',
            delivery_reference: '',
            received_at: '',
            funding_source: record.funding_source ?? '',
            responsibility_center_code: record.responsibility_center_code ?? '',
            ppmp_reference: record.ppmp_reference ?? '',
            app_reference: record.app_reference ?? '',
            line_categories: record.lines
                .filter(
                    (line) =>
                        line.item_kind === 'consumable' && line.item === null,
                )
                .map((line) => ({
                    line_id: line.id,
                    series_category_id:
                        line.series_category_id?.toString() ?? '',
                })),
            line_receipts: record.lines.map((line) => ({
                line_id: line.id,
                quantity_received: line.quantity,
                actual_unit_cost:
                    line.actual_unit_cost ?? line.estimated_unit_cost,
            })),
            line_estimates: record.lines.map((line) => ({
                line_id: line.id,
                estimated_unit_cost: line.estimated_unit_cost,
            })),
        });
        setSelectedAction({ record, action });
    }

    function confirmAction() {
        if (!selectedAction) {
            return;
        }

        actionForm.transform((data) => {
            const common = {
                action: data.action,
                attested: data.attested,
                remarks: data.remarks,
            };

            switch (data.action) {
                case 'submit_intake':
                    return {
                        ...common,
                        intake_details: data.intake_details,
                    };
                case 'verify_documents':
                    return {
                        ...common,
                        document_waivers: data.document_waivers,
                    };
                case 'prepare_document':
                    return {
                        ...common,
                        preparation: data.preparation,
                        line_categories: data.line_categories,
                        line_estimates: data.line_estimates,
                        funding_source: data.funding_source,
                        responsibility_center_code:
                            data.responsibility_center_code,
                        app_reference: data.app_reference,
                        ppmp_reference: data.ppmp_reference,
                    };
                case 'budget_review':
                    return {
                        ...common,
                        line_categories: data.line_categories,
                        funding_source: data.funding_source,
                        responsibility_center_code:
                            data.responsibility_center_code,
                        app_reference: data.app_reference,
                        ppmp_reference: data.ppmp_reference,
                    };
                case 'forward':
                    return {
                        ...common,
                        endorsement_recipient: data.endorsement_recipient,
                    };
                case 'order':
                    return {
                        ...common,
                        purchase_order_no: data.purchase_order_no,
                        procurement_mode: data.procurement_mode,
                    };
                case 'record_delivery':
                    return {
                        ...common,
                        line_receipts: data.line_receipts,
                        delivery_reference: data.delivery_reference,
                        received_at: data.received_at,
                    };
                case 'accept':
                    return {
                        ...common,
                        inspection_acceptance_no: data.inspection_acceptance_no,
                    };
                default:
                    return common;
            }
        });
        actionForm.patch(
            moduleRoutes.transition.url(selectedAction.record.id),
            {
                preserveScroll: true,
                onSuccess: () => setSelectedAction(null),
            },
        );
    }

    return (
        <>
            <Head title={moduleTitle} />
            <RequestForm
                open={requestFormOpen}
                onOpenChange={setRequestFormOpen}
                context={requestFormContext}
                accounts={chargeAccounts}
            />
            <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            End-to-end purchasing and recording
                        </p>
                        <h1 className="text-2xl font-semibold sm:text-3xl">
                            {moduleTitle}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {moduleKind === 'JO'
                                ? 'Manage job order records, supporting documents, approvals, and service procurement endorsements.'
                                : 'Manage purchase request records for common and non-common supplies, approvals, and procurement processing.'}
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

                <Dialog
                    open={prepareDialogOpen}
                    onOpenChange={(open) => {
                        setPrepareDialogOpen(open);

                        if (!open) {
                            setEditingRequisitionId(null);
                            form.reset();
                            form.clearErrors();
                        }
                    }}
                >
                    <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-5xl">
                        <form
                            onSubmit={submit}
                            className="grid gap-5"
                            noValidate
                        >
                            <DialogHeader className="pr-12">
                                <DialogTitle>
                                    {editingRequisitionId
                                        ? 'Correct requisition'
                                        : addLabel}
                                </DialogTitle>
                                <DialogDescription>
                                    Record the approved end-user requisition.
                                    Supporting documents must be verified before
                                    preparing its PR or JO.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {!moduleKind && (
                                    <Field
                                        id="document-kind"
                                        label="Document to prepare"
                                    >
                                        <FormSelect
                                            id="document-kind"
                                            value={form.data.document_kind}
                                            onChange={(event) =>
                                                form.setData({
                                                    ...form.data,
                                                    document_kind: event.target
                                                        .value as 'PR' | 'JO',
                                                    procurement_category:
                                                        event.target.value ===
                                                        'JO'
                                                            ? 'other_services'
                                                            : 'supplies',
                                                    type:
                                                        event.target.value ===
                                                        'JO'
                                                            ? 'new_item'
                                                            : 'replenishment',
                                                    lines: [],
                                                })
                                            }
                                        >
                                            <option value="PR">
                                                Purchase requisition (PR) —
                                                goods
                                            </option>
                                            <option value="JO">
                                                Job order (JO) — services
                                            </option>
                                        </FormSelect>
                                    </Field>
                                )}
                                <Field
                                    id="procurement-category"
                                    label="Supporting-document category"
                                >
                                    <FormSelect
                                        id="procurement-category"
                                        value={form.data.procurement_category}
                                        onChange={(event) =>
                                            form.setData(
                                                'procurement_category',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {Object.entries(
                                            procurementCategories,
                                        ).map(([key, category]) => (
                                            <option key={key} value={key}>
                                                {category.label}
                                            </option>
                                        ))}
                                    </FormSelect>
                                </Field>
                            </div>
                            <IntakeFields
                                value={form.data.preprocurement}
                                onChange={(value) =>
                                    form.setData('preprocurement', value)
                                }
                            />
                            <div className="grid gap-4 md:grid-cols-3">
                                {moduleKind !== 'JO' && (
                                    <Field
                                        id="procurement-type"
                                        label="Request type"
                                        error={errors.type}
                                    >
                                        <FormSelect
                                            id="procurement-type"
                                            className={fieldClassName}
                                            disabled={
                                                form.data.document_kind === 'JO'
                                            }
                                            value={form.data.type}
                                            onChange={(event) =>
                                                changeRequestType(
                                                    event.target
                                                        .value as ProcurementForm['type'],
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
                                        </FormSelect>
                                    </Field>
                                )}
                                {moduleKind !== 'JO' && (
                                    <Field
                                        id="procurement-source"
                                        label="Source"
                                        error={errors.source}
                                    >
                                        <FormSelect
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
                                            <option value="manual">
                                                Manual
                                            </option>
                                            <option value="low_stock">
                                                Low-stock recommendation
                                            </option>
                                            <option value="request_shortage">
                                                Request shortage
                                            </option>
                                        </FormSelect>
                                    </Field>
                                )}
                                <Field
                                    id="required-at"
                                    label="Required date"
                                    error={errors.required_at}
                                >
                                    <DatePicker
                                        id="required-at"
                                        value={form.data.required_at}
                                        onChange={(value) =>
                                            form.setData('required_at', value)
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
                                {form.data.document_kind === 'PR' &&
                                    form.data.type !== 'new_item' && (
                                        <div>
                                            <h2 className="font-medium">
                                                Consumable inventory
                                                replenishment
                                            </h2>
                                            <p className="text-xs text-muted-foreground">
                                                Select existing catalog items to
                                                replenish.
                                            </p>
                                        </div>
                                    )}
                                {form.data.document_kind === 'PR' &&
                                    form.data.type !== 'new_item' && (
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
                                                        form.data.lines[
                                                            lineIndex
                                                        ];
                                                    const selected =
                                                        lineIndex !== -1;

                                                    return (
                                                        <div
                                                            key={
                                                                item.inventory_item_id
                                                            }
                                                            className={`grid gap-3 px-4 py-3 md:grid-cols-[2rem_minmax(12rem,1fr)_8rem_10rem_8rem_11rem] md:items-center ${selected ? 'bg-primary/5' : ''}`}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                className="size-5"
                                                                checked={
                                                                    selected
                                                                }
                                                                aria-label={`Select ${item.name}`}
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    toggleExistingItem(
                                                                        item,
                                                                        event
                                                                            .target
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
                                                                    On
                                                                    hand:{' '}
                                                                </span>
                                                                {item.quantity}{' '}
                                                                {
                                                                    item.unit_of_measure
                                                                }
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
                                                        No active catalog items
                                                        are available.
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                {form.data.document_kind === 'PR' &&
                                    form.data.type !== 'replenishment' && (
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <h3 className="font-medium">
                                                    New goods and property
                                                </h3>
                                                <p className="text-xs text-muted-foreground">
                                                    Classify each new line for
                                                    stock or individual property
                                                    recording.
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
                                {form.data.document_kind === 'PR' &&
                                    form.data.type !== 'replenishment' &&
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
                                        const selectedAssetCategory =
                                            assetCategories.find(
                                                (category) =>
                                                    String(
                                                        category.inv_asset_cat_id,
                                                    ) ===
                                                    line.asset_category_id,
                                            );
                                        const isProjectedPpe =
                                            Number(line.estimated_unit_cost) >=
                                            50000;

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
                                                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
                                                                    id={`line-${lineIndex}-kind`}
                                                                    label="Recording destination"
                                                                    error={
                                                                        errors[
                                                                            `lines.${lineIndex}.item_kind`
                                                                        ]
                                                                    }
                                                                    className="lg:col-span-2"
                                                                >
                                                                    <FormSelect
                                                                        id={`line-${lineIndex}-kind`}
                                                                        className={
                                                                            fieldClassName
                                                                        }
                                                                        value={
                                                                            line.item_kind
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                lineIndex,
                                                                                {
                                                                                    item_kind:
                                                                                        event
                                                                                            .target
                                                                                            .value as ProcurementFormLine['item_kind'],
                                                                                    asset_category_id:
                                                                                        '',
                                                                                    asset_subcategory_id:
                                                                                        '',
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <option value="consumable">
                                                                            Consumable
                                                                            inventory
                                                                        </option>
                                                                        <option value="property">
                                                                            Durable
                                                                            property
                                                                            or
                                                                            equipment
                                                                        </option>
                                                                    </FormSelect>
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
                                                                <FormSelect
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
                                                                </FormSelect>
                                                            </Field>
                                                        )}
                                                        {line.item_kind ===
                                                            'property' && (
                                                            <>
                                                                <Field
                                                                    id={`line-${lineIndex}-asset-category`}
                                                                    label="Property category"
                                                                    error={
                                                                        errors[
                                                                            `lines.${lineIndex}.asset_category_id`
                                                                        ]
                                                                    }
                                                                    className="lg:col-span-2"
                                                                >
                                                                    <FormSelect
                                                                        id={`line-${lineIndex}-asset-category`}
                                                                        className={
                                                                            fieldClassName
                                                                        }
                                                                        value={
                                                                            line.asset_category_id
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                lineIndex,
                                                                                {
                                                                                    asset_category_id:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                    asset_subcategory_id:
                                                                                        '',
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <option value="">
                                                                            Select
                                                                            category
                                                                        </option>
                                                                        {assetCategories.map(
                                                                            (
                                                                                category,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        category.inv_asset_cat_id
                                                                                    }
                                                                                    value={
                                                                                        category.inv_asset_cat_id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        category.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </FormSelect>
                                                                </Field>
                                                                <Field
                                                                    id={`line-${lineIndex}-asset-subcategory`}
                                                                    label="Property subcategory"
                                                                    error={
                                                                        errors[
                                                                            `lines.${lineIndex}.asset_subcategory_id`
                                                                        ]
                                                                    }
                                                                    className="lg:col-span-2"
                                                                >
                                                                    <FormSelect
                                                                        id={`line-${lineIndex}-asset-subcategory`}
                                                                        className={
                                                                            fieldClassName
                                                                        }
                                                                        value={
                                                                            line.asset_subcategory_id
                                                                        }
                                                                        disabled={
                                                                            !selectedAssetCategory
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                lineIndex,
                                                                                {
                                                                                    asset_subcategory_id:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                            )
                                                                        }
                                                                    >
                                                                        <option value="">
                                                                            Select
                                                                            subcategory
                                                                        </option>
                                                                        {selectedAssetCategory?.subcategories.map(
                                                                            (
                                                                                subcategory,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        subcategory.inventory_asset_subcategory_id
                                                                                    }
                                                                                    value={
                                                                                        subcategory.inventory_asset_subcategory_id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        subcategory.name
                                                                                    }
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                    </FormSelect>
                                                                </Field>
                                                            </>
                                                        )}
                                                        <Field
                                                            id={`line-${lineIndex}-quantity`}
                                                            label="Quantity"
                                                            error={
                                                                errors[
                                                                    `lines.${lineIndex}.quantity`
                                                                ]
                                                            }
                                                            className="lg:col-span-2"
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
                                                            className="lg:col-span-2"
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
                                                        <div className="grid gap-4 md:grid-cols-3">
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
                                                                className="md:col-span-2"
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
                                                    {line.item_kind ===
                                                        'property' && (
                                                        <div className="flex gap-3 rounded-lg border border-primary/20 bg-primary/5 p-4 text-sm">
                                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                                                {isProjectedPpe
                                                                    ? 'PPE'
                                                                    : 'ICS'}
                                                            </span>
                                                            <div className="grid gap-1">
                                                                <p className="font-medium">
                                                                    Projected
                                                                    COA
                                                                    classification
                                                                </p>
                                                                <p className="text-muted-foreground">
                                                                    {isProjectedPpe
                                                                        ? 'Property, plant and equipment — individually recorded and issued through PAR.'
                                                                        : 'Semi-expendable property — individually recorded and issued through ICS.'}{' '}
                                                                    Final
                                                                    classification
                                                                    uses the
                                                                    actual unit
                                                                    cost at
                                                                    delivery.
                                                                </p>
                                                            </div>
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
                                    : editingRequisitionId
                                      ? 'Save corrections'
                                      : addLabel}
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>

                <Card>
                    <CardContent>
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <form
                                onSubmit={applyFilters}
                                className="grid w-full max-w-4xl gap-3 md:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]"
                            >
                                <Field id="procurement-search" label="Search">
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-3.5 left-3 size-4 text-muted-foreground" />
                                        <Input
                                            id="procurement-search"
                                            className="min-h-11 pl-9"
                                            maxLength={100}
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
                                    <FormSelect
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
                                    </FormSelect>
                                </Field>
                                <Field id="procurement-queue" label="Queue">
                                    <FormSelect
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
                                        <option value="archived">
                                            Archived / deleted
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
                            <Button
                                type="button"
                                className="min-h-11 w-full shrink-0 lg:w-auto"
                                onClick={() => setRequestFormOpen(true)}
                            >
                                <Plus /> {addLabel}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card className="hidden lg:flex">
                    <CardHeader>
                        <CardTitle>{moduleTitle}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <caption className="sr-only">
                                Procurement request queue
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col">Reference</TableHead>
                                    <TableHead scope="col">
                                        Received / created by
                                    </TableHead>
                                    <TableHead scope="col">Type</TableHead>
                                    <TableHead scope="col">Funding</TableHead>
                                    <TableHead
                                        scope="col"
                                        className="text-center"
                                    >
                                        Items
                                    </TableHead>
                                    <TableHead
                                        scope="col"
                                        className="text-right"
                                    >
                                        Estimated total
                                    </TableHead>
                                    <TableHead scope="col">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {procurementRequests.data.map((record) => (
                                    <Tooltip key={record.id}>
                                        <TooltipTrigger
                                            render={
                                                <TableRow
                                                    role="button"
                                                    tabIndex={0}
                                                    aria-haspopup="dialog"
                                                    aria-label={`View details for ${record.pr_no}`}
                                                    className="cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                                    onClick={() =>
                                                        setSelectedProcurement(
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
                                                            setSelectedProcurement(
                                                                record,
                                                            );
                                                        }
                                                    }}
                                                />
                                            }
                                        >
                                            <TableCell className="font-medium">
                                                {record.pr_no}
                                            </TableCell>
                                            <TableCell>
                                                {record.creator.name}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {record.document_kind === 'JO'
                                                    ? 'Job order'
                                                    : statusLabel(record.type)}
                                            </TableCell>
                                            <TableCell>
                                                {record.funding_source ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {record.lines.length}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                ₱
                                                {(record.document_kind === 'JO'
                                                    ? Number(
                                                          record.preprocurement
                                                              ?.estimated_cost ??
                                                              0,
                                                      )
                                                    : record.lines.reduce(
                                                          (total, line) =>
                                                              total +
                                                              Number(
                                                                  line.estimated_unit_cost,
                                                              ) *
                                                                  line.quantity,
                                                          0,
                                                      )
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                <span className="inline-flex rounded-full border px-3 py-1 text-xs font-medium capitalize">
                                                    {statusLabel(
                                                        record.deleted_at
                                                            ? 'archived'
                                                            : record.status,
                                                    )}
                                                </span>
                                            </TableCell>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Click for more info
                                        </TooltipContent>
                                    </Tooltip>
                                ))}
                                {procurementRequests.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="h-32 text-center text-muted-foreground"
                                        >
                                            No requisitions match the selected
                                            filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:hidden">
                    {procurementRequests.data.map((record) => (
                        <Card key={record.id}>
                            <CardHeader>
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <FileCheck2 className="size-5" />{' '}
                                        {record.pr_no}
                                    </CardTitle>
                                    <span className="w-fit rounded-full border px-3 py-1 text-xs font-medium capitalize">
                                        {statusLabel(
                                            record.deleted_at
                                                ? 'archived'
                                                : record.status,
                                        )}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <WorkflowStatus
                                    steps={stepsFor(record)}
                                    current={record.status}
                                    terminal={terminalStatuses}
                                />
                                <div className="rounded-lg bg-muted/60 p-3 text-sm">
                                    <p className="font-medium">
                                        What happens next
                                    </p>
                                    <p className="text-muted-foreground">
                                        {record.deleted_at
                                            ? 'Archived. Restore this record to return it to its module.'
                                            : nextAction(record.status)}
                                    </p>
                                </div>
                                <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <dt className="font-medium">
                                            Received / created by
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
                                {record.document_kind === 'PR' &&
                                    (record.status === 'ordered' ||
                                        record.deliveries_count > 0) && (
                                        <Button
                                            nativeButton={false}
                                            variant="outline"
                                            render={
                                                <Link
                                                    href={
                                                        record.deliveries_count >
                                                        0
                                                            ? deliveriesIndex({
                                                                  query: {
                                                                      procurement_request_id:
                                                                          record.id,
                                                                  },
                                                              })
                                                            : createDelivery({
                                                                  query: {
                                                                      procurement_request_id:
                                                                          record.id,
                                                                  },
                                                              })
                                                    }
                                                />
                                            }
                                        >
                                            Delivery inspection records
                                        </Button>
                                    )}
                                {actionsFor(record).length > 0 && (
                                    <div className="grid gap-2 sm:flex sm:flex-wrap">
                                        {actionsFor(record).map((action) => (
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
                                                    openAction(record, action)
                                                }
                                            >
                                                {action.label}
                                            </Button>
                                        ))}
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
                                    No requisitions match the selected filters.
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

            <Dialog
                open={selectedProcurement !== null}
                onOpenChange={(open) => !open && setSelectedProcurement(null)}
            >
                <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-5xl">
                    {selectedProcurement && (
                        <div className="grid gap-5">
                            <DialogHeader className="pr-12">
                                <div className="flex flex-wrap items-center gap-2">
                                    <DialogTitle>
                                        {selectedProcurement.pr_no}
                                    </DialogTitle>
                                    <span className="rounded-full border px-3 py-1 text-xs font-medium capitalize">
                                        {statusLabel(
                                            selectedProcurement.status,
                                        )}
                                    </span>
                                </div>
                                <DialogDescription>
                                    Received / created by{' '}
                                    {selectedProcurement.creator.name} for{' '}
                                    {selectedProcurement.purpose}.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="flex flex-wrap gap-2">
                                {(['delete', 'archive', 'restore'] as const)
                                    .filter((action) =>
                                        action === 'delete'
                                            ? selectedProcurement.can_delete
                                            : action === 'archive'
                                              ? selectedProcurement.can_archive
                                              : Boolean(
                                                    selectedProcurement.deleted_at,
                                                ),
                                    )
                                    .map((action) => (
                                        <Button
                                            key={action}
                                            type="button"
                                            variant={
                                                action === 'delete'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                            onClick={() => {
                                                lifecycleForm.clearErrors();
                                                lifecycleForm.reset();
                                                setManagementAction({
                                                    record: selectedProcurement,
                                                    action,
                                                });
                                            }}
                                        >
                                            <span className="capitalize">
                                                {action}
                                            </span>
                                        </Button>
                                    ))}
                            </div>
                            <SupportingDocuments
                                key={selectedProcurement.id}
                                record={selectedProcurement}
                                moduleKind={moduleKind}
                                categories={procurementCategories}
                            />
                            {!selectedProcurement.deleted_at &&
                                ['intake', 'returned_for_compliance'].includes(
                                    selectedProcurement.status,
                                ) &&
                                !selectedProcurement.supply_request && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            editRequisition(selectedProcurement)
                                        }
                                    >
                                        Correct requisition details and items
                                    </Button>
                                )}

                            <WorkflowStatus
                                steps={stepsFor(selectedProcurement)}
                                current={selectedProcurement.status}
                                terminal={terminalStatuses}
                            />

                            <div className="rounded-lg bg-muted/60 p-3 text-sm">
                                <p className="font-medium">What happens next</p>
                                <p className="text-muted-foreground">
                                    {selectedProcurement.deleted_at
                                        ? 'Archived. Restore this record to return it to its module.'
                                        : nextAction(
                                              selectedProcurement.status,
                                          )}
                                </p>
                            </div>

                            <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <dt className="font-medium">Type</dt>
                                    <dd className="text-muted-foreground capitalize">
                                        {statusLabel(selectedProcurement.type)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-medium">
                                        Source request
                                    </dt>
                                    <dd className="text-muted-foreground">
                                        {selectedProcurement.supply_request
                                            ?.ris_no ?? 'Manual procurement'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-medium">Funding</dt>
                                    <dd className="text-muted-foreground">
                                        {selectedProcurement.funding_source ??
                                            'Not recorded'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-medium">
                                        Planning references
                                    </dt>
                                    <dd className="text-muted-foreground">
                                        {selectedProcurement.ppmp_reference ??
                                            'No PPMP'}{' '}
                                        ·{' '}
                                        {selectedProcurement.app_reference ??
                                            'No APP'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="font-medium">
                                        Purchase order
                                    </dt>
                                    <dd className="text-muted-foreground">
                                        {selectedProcurement.purchase_order_no ??
                                            'Not recorded'}
                                    </dd>
                                </div>
                            </dl>

                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <caption className="sr-only">
                                        Items under {selectedProcurement.pr_no}
                                    </caption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col">
                                                Item
                                            </TableHead>
                                            <TableHead scope="col">
                                                Quantity
                                            </TableHead>
                                            <TableHead scope="col">
                                                Unit cost
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
                                        {selectedProcurement.lines.map(
                                            (line) => (
                                                <TableRow key={line.id}>
                                                    <TableCell className="font-medium">
                                                        <p>{line.item_name}</p>
                                                        <p className="text-xs font-normal text-muted-foreground">
                                                            {line.item_kind ===
                                                            'property'
                                                                ? `${line.asset_category?.name ?? 'Property'} · ${line.asset_subcategory?.name ?? 'Unclassified'}`
                                                                : 'Consumable inventory'}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell>
                                                        {line.quantity}{' '}
                                                        {line.unit_of_measure}
                                                    </TableCell>
                                                    <TableCell>
                                                        ₱
                                                        {Number(
                                                            line.actual_unit_cost ??
                                                                line.estimated_unit_cost,
                                                        ).toLocaleString()}
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
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>

                            {selectedProcurement.document_kind === 'PR' &&
                                (selectedProcurement.status === 'ordered' ||
                                    selectedProcurement.deliveries_count >
                                        0) && (
                                    <Button
                                        nativeButton={false}
                                        variant="outline"
                                        render={
                                            <Link
                                                href={
                                                    selectedProcurement.deliveries_count >
                                                    0
                                                        ? deliveriesIndex({
                                                              query: {
                                                                  procurement_request_id:
                                                                      selectedProcurement.id,
                                                              },
                                                          })
                                                        : createDelivery({
                                                              query: {
                                                                  procurement_request_id:
                                                                      selectedProcurement.id,
                                                              },
                                                          })
                                                }
                                            />
                                        }
                                    >
                                        Delivery inspection records
                                    </Button>
                                )}
                            {actionsFor(selectedProcurement).length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {actionsFor(selectedProcurement).map(
                                        (action) => (
                                            <Button
                                                key={action.action}
                                                className="min-h-11"
                                                variant={
                                                    action.destructive
                                                        ? 'destructive'
                                                        : action.action ===
                                                            'accept'
                                                          ? 'default'
                                                          : 'outline'
                                                }
                                                onClick={() => {
                                                    setSelectedProcurement(
                                                        null,
                                                    );
                                                    openAction(
                                                        selectedProcurement,
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
                                    Attested decision history (
                                    {selectedProcurement.actions.length})
                                </summary>
                                <div className="mt-2 grid gap-2">
                                    {selectedProcurement.actions.map(
                                        (action) => (
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
                                        ),
                                    )}
                                </div>
                            </details>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <WorkflowActionDialog
                open={managementAction !== null}
                onOpenChange={(open) => !open && setManagementAction(null)}
                title={
                    managementAction
                        ? `${managementAction.action}: ${managementAction.record.pr_no}`
                        : 'Manage record'
                }
                description="Deleted and archived records are kept in the archive with their history and can be restored."
                confirmLabel={managementAction?.action ?? 'Confirm'}
                destructive={managementAction?.action === 'delete'}
                remarksRequired
                processing={lifecycleForm.processing}
                remarks={lifecycleForm.data.remarks}
                onRemarksChange={(remarks) =>
                    lifecycleForm.setData('remarks', remarks)
                }
                onConfirm={manageRecord}
                error={
                    lifecycleForm.errors.remarks ??
                    (lifecycleForm.errors as Record<string, string>).action
                }
            />
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
                {selectedAction && (
                    <FormErrorSummary errors={actionForm.errors} />
                )}
                {selectedAction?.action.action === 'submit_intake' && (
                    <IntakeFields
                        value={actionForm.data.intake_details}
                        onChange={(value) =>
                            actionForm.setData('intake_details', value)
                        }
                    />
                )}
                {selectedAction?.action.action === 'verify_documents' &&
                    selectedAction.record.procurement_category && (
                        <DocumentChecklist
                            category={
                                procurementCategories[
                                    selectedAction.record.procurement_category
                                ]
                            }
                            attachments={selectedAction.record.attachments}
                            waivers={actionForm.data.document_waivers}
                            onChange={(value) =>
                                actionForm.setData('document_waivers', value)
                            }
                        />
                    )}
                {selectedAction?.action.action === 'prepare_document' && (
                    <PreparationFields
                        kind={selectedAction.record.document_kind}
                        value={actionForm.data.preparation}
                        onChange={(value) =>
                            actionForm.setData('preparation', value)
                        }
                        signatories={signatories}
                    />
                )}
                {selectedAction?.action.action === 'prepare_document' &&
                    selectedAction.record.lines.map((line, index) => (
                        <Field
                            key={line.id}
                            id={`estimate-${line.id}`}
                            label={`Estimated unit cost — ${line.item_name}`}
                        >
                            <Input
                                id={`estimate-${line.id}`}
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={
                                    actionForm.data.line_estimates[index]
                                        ?.estimated_unit_cost ?? ''
                                }
                                onChange={(event) =>
                                    actionForm.setData(
                                        'line_estimates',
                                        actionForm.data.line_estimates.map(
                                            (estimate) =>
                                                estimate.line_id === line.id
                                                    ? {
                                                          ...estimate,
                                                          estimated_unit_cost:
                                                              event.target
                                                                  .value,
                                                      }
                                                    : estimate,
                                        ),
                                    )
                                }
                            />
                        </Field>
                    ))}
                {selectedAction?.action.action === 'forward' && (
                    <Field
                        id="endorsement-recipient"
                        label="Receiving procurement officer / unit"
                    >
                        <Input
                            id="endorsement-recipient"
                            value={actionForm.data.endorsement_recipient}
                            onChange={(event) =>
                                actionForm.setData(
                                    'endorsement_recipient',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                )}
                {selectedAction &&
                    ['budget_review', 'prepare_document'].includes(
                        selectedAction?.action.action ?? '',
                    ) && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="budget-funding-source"
                                label="Funding source"
                                error={actionForm.errors.funding_source}
                            >
                                <Input
                                    id="budget-funding-source"
                                    className="min-h-11"
                                    value={actionForm.data.funding_source}
                                    onChange={(event) =>
                                        actionForm.setData(
                                            'funding_source',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="budget-responsibility-center"
                                label="Responsibility center"
                                error={
                                    actionForm.errors.responsibility_center_code
                                }
                            >
                                <Input
                                    id="budget-responsibility-center"
                                    className="min-h-11"
                                    value={
                                        actionForm.data
                                            .responsibility_center_code
                                    }
                                    onChange={(event) =>
                                        actionForm.setData(
                                            'responsibility_center_code',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="budget-ppmp-reference"
                                label="PPMP reference"
                                error={actionForm.errors.ppmp_reference}
                            >
                                <Input
                                    id="budget-ppmp-reference"
                                    className="min-h-11"
                                    value={actionForm.data.ppmp_reference}
                                    onChange={(event) =>
                                        actionForm.setData(
                                            'ppmp_reference',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="budget-app-reference"
                                label="APP reference"
                                error={actionForm.errors.app_reference}
                            >
                                <Input
                                    id="budget-app-reference"
                                    className="min-h-11"
                                    value={actionForm.data.app_reference}
                                    onChange={(event) =>
                                        actionForm.setData(
                                            'app_reference',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {selectedAction.record.lines
                                .filter(
                                    (line) =>
                                        line.item_kind === 'consumable' &&
                                        line.item === null,
                                )
                                .map((line) => {
                                    const lineIndex =
                                        actionForm.data.line_categories.findIndex(
                                            (category) =>
                                                category.line_id === line.id,
                                        );

                                    return (
                                        <Field
                                            key={line.id}
                                            id={`budget-line-category-${line.id}`}
                                            label={`Inventory category — ${line.item_name}`}
                                            error={
                                                actionForm.errors[
                                                    `line_categories.${lineIndex}.series_category_id`
                                                ]
                                            }
                                            className="sm:col-span-2"
                                        >
                                            <FormSelect
                                                id={`budget-line-category-${line.id}`}
                                                className={fieldClassName}
                                                value={
                                                    actionForm.data
                                                        .line_categories[
                                                        lineIndex
                                                    ]?.series_category_id ?? ''
                                                }
                                                onChange={(event) =>
                                                    actionForm.setData(
                                                        'line_categories',
                                                        actionForm.data.line_categories.map(
                                                            (category) =>
                                                                category.line_id ===
                                                                line.id
                                                                    ? {
                                                                          ...category,
                                                                          series_category_id:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : category,
                                                        ),
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Select category
                                                </option>
                                                {seriesCategories.map(
                                                    (series) => (
                                                        <option
                                                            key={
                                                                series.inv_series_cat_id
                                                            }
                                                            value={
                                                                series.inv_series_cat_id
                                                            }
                                                        >
                                                            {series.name}
                                                        </option>
                                                    ),
                                                )}
                                            </FormSelect>
                                        </Field>
                                    );
                                })}
                        </div>
                    )}
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
                            <DatePicker
                                id="received-at"
                                value={actionForm.data.received_at}
                                onChange={
                                    (value) =>
                                        actionForm.setData('received_at', value)
                                    /*  */
                                }
                                aria-invalid={Boolean(
                                    actionForm.errors.received_at,
                                )}
                            />
                        </Field>
                        <div className="grid gap-3 sm:col-span-2">
                            <div>
                                <p className="font-medium">
                                    Received lines and actual costs
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Actual unit cost determines whether durable
                                    property is semi-expendable or PPE.
                                </p>
                            </div>
                            {selectedAction.record.lines.map((line) => {
                                const receiptIndex =
                                    actionForm.data.line_receipts.findIndex(
                                        (receipt) =>
                                            receipt.line_id === line.id,
                                    );
                                const receipt =
                                    actionForm.data.line_receipts[receiptIndex];

                                return (
                                    <div
                                        key={line.id}
                                        className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_8rem_11rem] sm:items-end"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {line.item_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {line.item_kind === 'property'
                                                    ? 'Property register'
                                                    : 'Consumable stock'}
                                            </p>
                                        </div>
                                        <Field
                                            id={`receipt-${line.id}-quantity`}
                                            label="Received"
                                            error={
                                                actionForm.errors[
                                                    `line_receipts.${receiptIndex}.quantity_received`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`receipt-${line.id}-quantity`}
                                                type="number"
                                                min="1"
                                                max={line.quantity}
                                                value={
                                                    receipt?.quantity_received ??
                                                    line.quantity
                                                }
                                                onChange={(event) =>
                                                    actionForm.setData(
                                                        'line_receipts',
                                                        actionForm.data.line_receipts.map(
                                                            (candidate) =>
                                                                candidate.line_id ===
                                                                line.id
                                                                    ? {
                                                                          ...candidate,
                                                                          quantity_received:
                                                                              Number(
                                                                                  event
                                                                                      .target
                                                                                      .value,
                                                                              ),
                                                                      }
                                                                    : candidate,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            id={`receipt-${line.id}-cost`}
                                            label="Actual unit cost"
                                            error={
                                                actionForm.errors[
                                                    `line_receipts.${receiptIndex}.actual_unit_cost`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`receipt-${line.id}-cost`}
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                value={
                                                    receipt?.actual_unit_cost ??
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    actionForm.setData(
                                                        'line_receipts',
                                                        actionForm.data.line_receipts.map(
                                                            (candidate) =>
                                                                candidate.line_id ===
                                                                line.id
                                                                    ? {
                                                                          ...candidate,
                                                                          actual_unit_cost:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : candidate,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                );
                            })}
                        </div>
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
    breadcrumbs: [{ title: 'Requests', href: index() }],
};
