import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/form-error-summary';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { print as printJobOrder } from '@/routes/inventory/job_orders';
import * as jobOrderAttachments from '@/routes/inventory/job_orders/attachments';
import { print as printDocument } from '@/routes/inventory/procurement';
import { store, download } from '@/routes/inventory/procurement/attachments';
import { print as printPurchaseRequest } from '@/routes/inventory/purchase_requests';
import * as purchaseRequestAttachments from '@/routes/inventory/purchase_requests/attachments';
import type { HrisReference } from '@/types/inventory';

export type Categories = Record<
    string,
    {
        label: string;
        documents: Record<string, { label: string; required: boolean }>;
    }
>;
export type IntakeDetails = {
    approved_request_reference: string;
    end_user: string;
    department: string;
    division: string;
    is_replacement: boolean;
};
export const emptyIntake: IntakeDetails = {
    approved_request_reference: '',
    end_user: '',
    department: '',
    division: '',
    is_replacement: false,
};
export const emptyPreparation = {
    requisition_type: '',
    charge_account: '',
    abc: '',
    caf_number: '',
    place_of_delivery: '',
    vehicle_plate: '',
    are_number: '',
    target_completion_date: '',
    estimated_cost: '',
    work_description: '',
    prepared_by_division: '',
    prepared_by_user_id: '',
    recommending_user_id: '',
    countersigning_user_id: '',
    gm_user_id: '',
};
export type Preparation = typeof emptyPreparation;
export type PreprocurementDetails = Partial<IntakeDetails & Preparation> & {
    prepared_at?: string;
    documents_verified_at?: string;
    signature_route?: { stage: string; name: string; user_id: number }[];
    signatures?: {
        stage: string;
        name: string;
        signed_at: string;
        user_id: number;
    }[];
    document_waivers?: Record<string, string>;
    approve?: { name: string; at: string };
    forward?: { name: string; at: string; recipient: string };
};
export type Attachment = {
    id: number;
    document_key: string;
    original_name: string;
    created_at: string;
};

const labels = {
    approved_request_reference: 'Approved request reference',
    end_user: 'End user',
    department: 'Requisitioning department',
    division: 'Requisitioning division',
};
export function IntakeFields({
    value,
    onChange,
}: {
    value: IntakeDetails;
    onChange: (value: IntakeDetails) => void;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {Object.entries(labels).map(([key, label]) => (
                <div key={key} className="grid gap-2">
                    <Label htmlFor={`intake-${key}`}>{label}</Label>
                    <Input
                        id={`intake-${key}`}
                        value={value[key as keyof typeof labels]}
                        onChange={(event) =>
                            onChange({ ...value, [key]: event.target.value })
                        }
                    />
                </div>
            ))}
            <label className="flex items-center gap-2 text-sm sm:col-span-2">
                <input
                    type="checkbox"
                    checked={value.is_replacement}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            is_replacement: event.target.checked,
                        })
                    }
                />
                Replacement requisition (replacement reports required)
            </label>
        </div>
    );
}

export function PreparationFields({
    kind,
    value,
    onChange,
    signatories,
}: {
    kind: string;
    value: Preparation;
    onChange: (value: Preparation) => void;
    signatories: HrisReference[];
}) {
    const fields: [keyof Preparation, string, string][] = [
        ['requisition_type', `${kind} type`, 'text'],
        ['charge_account', 'Charge account number', 'text'],
        ['abc', 'Allocated Budget Cost (ABC)', 'number'],
        ['caf_number', 'CAF number', 'text'],
        ...(kind === 'JO'
            ? ([
                  ['target_completion_date', 'Target completion date', 'date'],
                  ['estimated_cost', 'Estimated cost', 'number'],
              ] as [keyof Preparation, string, string][])
            : ([['place_of_delivery', 'Place of delivery', 'text']] as [
                  keyof Preparation,
                  string,
                  string,
              ][])),
        ['vehicle_plate', 'Vehicle plate number (if applicable)', 'text'],
        ['are_number', 'Property ARE number (if applicable)', 'text'],
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {fields.map(([key, label, type]) => (
                <div className="grid gap-2" key={key}>
                    <Label htmlFor={`prepare-${key}`}>{label}</Label>
                    <Input
                        id={`prepare-${key}`}
                        type={type}
                        min={type === 'number' ? '0.01' : undefined}
                        step={type === 'number' ? '0.01' : undefined}
                        value={value[key]}
                        onChange={(event) =>
                            onChange({ ...value, [key]: event.target.value })
                        }
                    />
                </div>
            ))}
            {kind === 'JO' && (
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="work-description">
                        Description of work and inclusions
                    </Label>
                    <textarea
                        id="work-description"
                        rows={5}
                        className="rounded-lg border bg-background p-3 text-sm"
                        value={value.work_description}
                        onChange={(event) =>
                            onChange({
                                ...value,
                                work_description: event.target.value,
                            })
                        }
                    />
                </div>
            )}
            <div className="grid gap-2">
                <Label htmlFor="prepared-division">
                    Preparing Division Manager
                </Label>
                <FormSelect
                    id="prepared-division"
                    value={value.prepared_by_division}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            prepared_by_division: event.target.value,
                        })
                    }
                >
                    <option value="">Select division</option>
                    <option value="PMMD">PMMD</option>
                    <option value="PWSD">PWSD</option>
                </FormSelect>
            </div>
            <p className="self-center text-xs text-muted-foreground">
                PMMD and PWSD remain separate. Select the division and
                authorized officers for this record.
            </p>
            {(
                [
                    [
                        'prepared_by_user_id',
                        'Prepared by — selected Division Manager',
                    ],
                    [
                        'recommending_user_id',
                        'Recommending approval — AGM for A&F',
                    ],
                    [
                        'countersigning_user_id',
                        'Countersigned by — AHRD Department Manager',
                    ],
                    ['gm_user_id', 'Approved by — General Manager'],
                ] as const
            ).map(([key, label]) => (
                <div className="grid gap-2" key={key}>
                    <Label htmlFor={`signer-${key}`}>{label}</Label>
                    <HrisReferenceCombobox
                        id={`signer-${key}`}
                        name={key}
                        references={signatories}
                        value={value[key]}
                        onValueChange={(id) =>
                            onChange({ ...value, [key]: id })
                        }
                        placeholder="Select authorized system account"
                    />
                </div>
            ))}
        </div>
    );
}

export function DocumentChecklist({
    category,
    attachments,
    waivers,
    onChange,
}: {
    category: Categories[string];
    attachments: Attachment[];
    waivers: Record<string, string>;
    onChange: (value: Record<string, string>) => void;
}) {
    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                Verify every attachment. Optional documents need a reason when
                not applicable; replacement reports cannot be waived for
                replacements.
            </p>
            {Object.entries(category.documents).map(([key, document]) => (
                <div key={key} className="grid gap-2 rounded-lg border p-3">
                    <Label htmlFor={`waiver-${key}`}>
                        {document.label} {document.required && '(required)'}
                    </Label>
                    <p className="text-xs text-muted-foreground">
                        {attachments
                            .filter((file) => file.document_key === key)
                            .map((file) => file.original_name)
                            .join(', ') || 'No document uploaded'}
                    </p>
                    {!document.required &&
                        !attachments.some(
                            (file) => file.document_key === key,
                        ) && (
                            <Input
                                id={`waiver-${key}`}
                                placeholder="Reason this document is not applicable"
                                value={waivers[key] ?? ''}
                                onChange={(event) =>
                                    onChange({
                                        ...waivers,
                                        [key]: event.target.value,
                                    })
                                }
                            />
                        )}
                </div>
            ))}
        </div>
    );
}

export function SupportingDocuments({
    record,
    categories,
    moduleKind,
}: {
    record: {
        id: number;
        pr_no: string;
        document_kind: string;
        procurement_category: string | null;
        status: string;
        preprocurement: PreprocurementDetails | null;
        attachments: Attachment[];
        deleted_at?: string | null;
    };
    categories: Categories;
    moduleKind?: 'PR' | 'JO' | null;
}) {
    const attachmentRoutes =
        moduleKind === 'JO'
            ? jobOrderAttachments
            : moduleKind === 'PR'
              ? purchaseRequestAttachments
              : { store, download };
    const printRoute =
        moduleKind === 'JO'
            ? printJobOrder
            : moduleKind === 'PR'
              ? printPurchaseRequest
              : printDocument;
    const form = useForm<{ document_key: string; file: File | null }>({
        document_key: '',
        file: null,
    });
    const fileInput = useRef<HTMLInputElement>(null);
    const category = record.procurement_category
        ? categories[record.procurement_category]
        : null;
    const details = record.preprocurement;

    if (!category || !details) {
        return null;
    }

    const canUpload =
        !record.deleted_at &&
        ['intake', 'returned_for_compliance'].includes(record.status);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(attachmentRoutes.store.url(record.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    }

    return (
        <section className="grid gap-4 rounded-xl border p-4">
            <h2 className="font-semibold">
                {record.document_kind} · {category.label}
            </h2>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                {Object.entries(labels).map(([key, label]) => (
                    <div key={key}>
                        <dt className="font-medium">{label}</dt>
                        <dd>
                            {String(
                                details[key as keyof typeof labels] ??
                                    'Not recorded',
                            )}
                        </dd>
                    </div>
                ))}
                <div>
                    <dt className="font-medium">Replacement</dt>
                    <dd>{details.is_replacement ? 'Yes' : 'No'}</dd>
                </div>
            </dl>
            <h3 className="font-medium">Supporting documents</h3>
            <ul className="grid gap-2 text-sm">
                {Object.entries(category.documents).map(([key, document]) => (
                    <li key={key}>
                        <span className="font-medium">
                            {document.label}
                            {document.required ? ' (required)' : ''}
                        </span>
                        <div className="flex flex-wrap gap-2">
                            {record.attachments
                                .filter((file) => file.document_key === key)
                                .map((file) => (
                                    <a
                                        key={file.id}
                                        className="text-primary underline"
                                        href={attachmentRoutes.download.url({
                                            procurementRequest: record.id,
                                            attachment: file.id,
                                        })}
                                    >
                                        {file.original_name}
                                    </a>
                                ))}
                            {!record.attachments.some(
                                (file) => file.document_key === key,
                            ) && (
                                <span className="text-muted-foreground">
                                    {details.document_waivers?.[key]
                                        ? `Not applicable: ${details.document_waivers[key]}`
                                        : 'Awaiting document'}
                                </span>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
            {canUpload && (
                <form
                    onSubmit={submit}
                    className="grid gap-3 rounded-lg bg-muted/40 p-3"
                >
                    <Label htmlFor="supporting-document-type">
                        Document type
                    </Label>
                    <FormSelect
                        id="supporting-document-type"
                        value={form.data.document_key}
                        onChange={(event) =>
                            form.setData('document_key', event.target.value)
                        }
                    >
                        <option value="">Select checklist document</option>
                        {Object.entries(category.documents).map(
                            ([key, document]) => (
                                <option key={key} value={key}>
                                    {document.label}
                                </option>
                            ),
                        )}
                    </FormSelect>
                    <Label htmlFor="supporting-document-file">
                        File (PDF, Word, JPG or PNG; up to 10 MB)
                    </Label>
                    <Input
                        ref={fileInput}
                        id="supporting-document-file"
                        type="file"
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                        onChange={(event) =>
                            form.setData(
                                'file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <FormErrorSummary errors={form.errors} />
                    <Button
                        disabled={
                            form.processing ||
                            !form.data.file ||
                            !form.data.document_key
                        }
                        type="submit"
                    >
                        Upload supporting document
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Uploads are retained privately. Add a corrected version
                        to preserve the earlier copy.
                    </p>
                </form>
            )}
            {details.prepared_at && (
                <>
                    <h3 className="font-medium">Prepared document</h3>
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        {(
                            [
                                'requisition_type',
                                'charge_account',
                                'abc',
                                'caf_number',
                                'place_of_delivery',
                                'vehicle_plate',
                                'are_number',
                                'target_completion_date',
                                'estimated_cost',
                                'work_description',
                                'prepared_by_division',
                            ] as const
                        )
                            .filter((key) => details[key])
                            .map((key) => (
                                <div key={key}>
                                    <dt className="font-medium capitalize">
                                        {key.replaceAll('_', ' ')}
                                    </dt>
                                    <dd className="whitespace-pre-wrap">
                                        {details[key]}
                                    </dd>
                                </div>
                            ))}
                    </dl>
                    <a
                        href={printRoute.url(record.id)}
                        target="_blank"
                        rel="noreferrer"
                        className="text-sm text-primary underline"
                    >
                        Print {record.document_kind} / Save file copy as PDF
                    </a>
                </>
            )}
            {details.signatures?.map((signature) => (
                <p key={signature.stage} className="text-sm">
                    {signature.stage
                        .replace('_user_id', '')
                        .replaceAll('_', ' ')}
                    : {signature.name} ·{' '}
                    {new Date(signature.signed_at).toLocaleString()}
                </p>
            ))}
            {details.signature_route && (
                <div className="grid gap-2 text-sm">
                    <h3 className="font-medium">Assigned signature route</h3>
                    {details.signature_route.map((signature) => (
                        <p key={signature.stage}>
                            {signature.stage
                                .replace('_user_id', '')
                                .replaceAll('_', ' ')}
                            : {signature.name}
                        </p>
                    ))}
                </div>
            )}
            {details.approve && (
                <p className="text-sm">
                    GM approval: {details.approve.name} ·{' '}
                    {new Date(details.approve.at).toLocaleString()}
                </p>
            )}
            {details.forward && (
                <p className="text-sm">
                    Endorsed to {details.forward.recipient} by{' '}
                    {details.forward.name} ·{' '}
                    {new Date(details.forward.at).toLocaleString()}
                </p>
            )}
        </section>
    );
}
