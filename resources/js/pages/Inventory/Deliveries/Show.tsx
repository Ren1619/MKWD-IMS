import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import InputError from '@/components/input-error';
import {
    Table,
    TableHeader,
    TableHead,
    TableRow,
    TableBody,
    TableCell,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index,
    create,
    transition,
    print as printDelivery,
} from '@/routes/inventory/deliveries';
import { print as printPostponement } from '@/routes/inventory/deliveries/postponements';
import type { HrisReference } from '@/types/inventory';
import PostponementDialog from './PostponementDialog';
import SupplierReturnAuthorization from './SupplierReturnAuthorization';
import type { Delivery } from './types';
import { areaClass, title, money, manilaTime, localReceiptTime } from './types';
const titles: Record<string, string> = {
    inspect: 'Record joint inspection',
    accept: 'Accept and post conforming quantities',
    cancel: 'Cancel shipment record',
    record_supplier_return: 'Record physical return to supplier',
    endorse: 'Record pre-audit / accounting endorsement',
    postpone: 'Postpone inspection',
    authorize_supplier_return: 'Record signed supplier-return notice',
};
function DeliveryDecision({
    document,
    action,
    close,
    employees,
    today,
}: {
    document: Delivery;
    action: string;
    close: () => void;
    employees: HrisReference[];
    today: string;
}) {
    const form = useForm({
        action,
        remarks: '',
        attested: false,
        inspection_reference: '',
        evaluation_date: today,
        order_reference_date: '',
        invoice_date: '',
        delivery_receipt_date: '',
        inspection_site: '',
        evaluation_findings: '',
        evaluation_comments: '',
        evaluator_reference_id: '',
        inspection_chair_reference_id: '',
        inspection_vice_reference_id: '',
        inspection_end_user_reference_id: '',
        evaluator_signature_reference: '',
        evaluation_chair_signature_reference: '',
        resumption_reference: '',
        coa_witness_name: '',
        supplier_notification_reference: '',
        signed_acceptance_reference: '',
        return_reference: '',
        returned_at: localReceiptTime().slice(0, 10),
        endorsement_reference: '',
        endorsement_recipient: '',
        lines: document.lines.map((line) => ({
            line_id: line.id,
            quantity_accepted: '',
            condition: 'conforming',
            disposition: 'warehouse',
            storage_location: '',
            disposition_reference: '',
            findings: '',
            nonconformities: [] as string[],
        })),
    });
    const updateLine = (
        position: number,
        field:
            | 'quantity_accepted'
            | 'condition'
            | 'disposition'
            | 'storage_location'
            | 'disposition_reference'
            | 'findings',
        value: string,
    ) =>
        form.setData(
            'lines',
            form.data.lines.map((line, i) =>
                i === position ? { ...line, [field]: value } : line,
            ),
        );

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) {
                    close();
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{titles[action]}</DialogTitle>
                    <DialogDescription>
                        {document.document_no}. Record the findings and
                        references from the physical inspection and signed
                        documents.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) =>
                            action === 'inspect'
                                ? {
                                      action: data.action,
                                      remarks: data.remarks,
                                      attested: data.attested,
                                      inspection_reference:
                                          data.inspection_reference,
                                      evaluation_date: data.evaluation_date,
                                      order_reference_date:
                                          data.order_reference_date,
                                      invoice_date: data.invoice_date,
                                      delivery_receipt_date:
                                          data.delivery_receipt_date,
                                      inspection_site: data.inspection_site,
                                      evaluation_findings:
                                          data.evaluation_findings,
                                      evaluation_comments:
                                          data.evaluation_comments,
                                      evaluator_reference_id:
                                          data.evaluator_reference_id,
                                      inspection_chair_reference_id:
                                          data.inspection_chair_reference_id,
                                      inspection_vice_reference_id:
                                          data.inspection_vice_reference_id,
                                      inspection_end_user_reference_id:
                                          data.inspection_end_user_reference_id,
                                      evaluator_signature_reference:
                                          data.evaluator_signature_reference,
                                      evaluation_chair_signature_reference:
                                          data.evaluation_chair_signature_reference,
                                      resumption_reference:
                                          data.resumption_reference,
                                      coa_witness_name: data.coa_witness_name,
                                      supplier_notification_reference:
                                          data.supplier_notification_reference,
                                      lines: data.lines,
                                  }
                                : action === 'accept'
                                  ? {
                                        action: data.action,
                                        remarks: data.remarks,
                                        attested: data.attested,
                                        signed_acceptance_reference:
                                            data.signed_acceptance_reference,
                                    }
                                  : action === 'record_supplier_return'
                                    ? {
                                          action: data.action,
                                          remarks: data.remarks,
                                          attested: data.attested,
                                          return_reference:
                                              data.return_reference,
                                          returned_at: data.returned_at,
                                      }
                                    : action === 'endorse'
                                      ? {
                                            action: data.action,
                                            remarks: data.remarks,
                                            attested: data.attested,
                                            endorsement_reference:
                                                data.endorsement_reference,
                                            endorsement_recipient:
                                                data.endorsement_recipient,
                                        }
                                      : {
                                            action: data.action,
                                            remarks: data.remarks,
                                            attested: data.attested,
                                        },
                        );
                        form.patch(transition.url(document.id), {
                            onSuccess: close,
                        });
                    }}
                >
                    {action === 'inspect' && (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'evaluation_date',
                                            'Physical evaluation date',
                                        ],
                                        [
                                            'order_reference_date',
                                            'PO reference date',
                                        ],
                                        ['invoice_date', 'Invoice date'],
                                        [
                                            'delivery_receipt_date',
                                            'Delivery receipt date',
                                        ],
                                        ['inspection_site', 'Site inspection'],
                                        [
                                            'evaluation_findings',
                                            'Overall evaluation / findings',
                                        ],
                                        [
                                            'evaluation_comments',
                                            'Sampling method / formula / other comments',
                                        ],
                                        [
                                            'evaluator_signature_reference',
                                            'Signed evaluator reference',
                                        ],
                                        [
                                            'evaluation_chair_signature_reference',
                                            'Signed Inspectorate Chairperson reference',
                                        ],
                                    ] as const
                                ).map(([name, caption]) => (
                                    <div className="grid gap-2" key={name}>
                                        <Label htmlFor={name}>{caption}</Label>
                                        {name.endsWith('_date') ? (
                                            <DatePicker
                                                id={name}
                                                max={today}
                                                value={form.data[name]}
                                                onChange={(value) =>
                                                    form.setData(name, value)
                                                }
                                                required={
                                                    name !==
                                                    'evaluation_comments'
                                                }
                                            />
                                        ) : (
                                            <Input
                                                id={name}
                                                type="text"
                                                value={form.data[name]}
                                                onChange={(event) =>
                                                    form.setData(
                                                        name,
                                                        event.target.value,
                                                    )
                                                }
                                                required={
                                                    name !==
                                                    'evaluation_comments'
                                                }
                                                maxLength={
                                                    name.endsWith('_reference')
                                                        ? 120
                                                        : name ===
                                                            'inspection_site'
                                                          ? 255
                                                          : 3000
                                                }
                                            />
                                        )}
                                        <InputError
                                            message={form.errors[name]}
                                        />
                                    </div>
                                ))}
                                {(
                                    [
                                        [
                                            'evaluator_reference_id',
                                            'Inspected / evaluated by',
                                        ],
                                        [
                                            'inspection_chair_reference_id',
                                            'Inspectorate / QAT chairperson',
                                        ],
                                        [
                                            'inspection_vice_reference_id',
                                            'Inspectorate / QAT vice-chairperson',
                                        ],
                                        [
                                            'inspection_end_user_reference_id',
                                            'Validating end user',
                                        ],
                                    ] as const
                                ).map(([name, caption]) => (
                                    <div className="grid gap-2" key={name}>
                                        <Label htmlFor={name}>{caption}</Label>
                                        <HrisReferenceCombobox
                                            id={name}
                                            name={name}
                                            references={employees}
                                            value={form.data[name]}
                                            onValueChange={(value) =>
                                                form.setData(name, value)
                                            }
                                            required
                                        />
                                        <InputError
                                            message={form.errors[name]}
                                        />
                                    </div>
                                ))}
                            </div>
                            {document.actions.some(
                                (entry) => entry.action === 'postpone',
                            ) && (
                                <div className="grid gap-2">
                                    <Label htmlFor="resumption_reference">
                                        Supplier availability / restored
                                        inspection team confirmation reference
                                    </Label>
                                    <Input
                                        id="resumption_reference"
                                        value={form.data.resumption_reference}
                                        onChange={(e) =>
                                            form.setData(
                                                'resumption_reference',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        required
                                    />
                                    <InputError
                                        message={
                                            form.errors.resumption_reference
                                        }
                                    />
                                </div>
                            )}
                            <p className="text-sm text-muted-foreground">
                                Enter accepted quantities explicitly. Record
                                zero for wholly rejected lines. Defective or
                                expired items stay segregated. Calibration
                                requires a passed test reference; direct use
                                requires a signed end-user endorsement.
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {(
                                    [
                                        [
                                            'inspection_reference',
                                            'Joint inspection report / signed evidence',
                                            true,
                                        ],
                                        [
                                            'coa_witness_name',
                                            'COA representative / witness (if present)',
                                            false,
                                        ],
                                        [
                                            'supplier_notification_reference',
                                            'Supplier / Procurement discrepancy notification',
                                            false,
                                        ],
                                    ] as const
                                ).map(([key, label, required]) => (
                                    <div className="space-y-2" key={key}>
                                        <Label htmlFor={key}>{label}</Label>
                                        <Input
                                            id={key}
                                            required={required}
                                            maxLength={
                                                key === 'inspection_reference'
                                                    ? 120
                                                    : 255
                                            }
                                            value={form.data[key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    key,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                            {form.data.lines.map((line, i) => (
                                <article
                                    key={line.line_id}
                                    className="space-y-3 rounded-lg border p-4"
                                >
                                    <h3 className="font-medium">
                                        {document.lines[i].item_name} /
                                        Delivered:{' '}
                                        {document.lines[i].quantity_delivered}{' '}
                                        {document.lines[i].unit_of_measure}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        {document.lines[i].specifications}
                                    </p>
                                    <fieldset className="space-y-2">
                                        <legend className="text-sm font-medium">
                                            Non-conformance checklist (select
                                            reasons for rejected quantity)
                                        </legend>
                                        <div className="flex flex-wrap gap-4">
                                            {[
                                                [
                                                    'packaging',
                                                    'Packing / packaging',
                                                ],
                                                ['label', 'Label (minimum)'],
                                                ['count', 'Count'],
                                                [
                                                    'brand_model',
                                                    'Brand / model',
                                                ],
                                            ].map(([category, caption]) => (
                                                <Label
                                                    key={category}
                                                    htmlFor={
                                                        category +
                                                        '-' +
                                                        line.line_id
                                                    }
                                                >
                                                    <input
                                                        type="checkbox"
                                                        id={
                                                            category +
                                                            '-' +
                                                            line.line_id
                                                        }
                                                        className="mr-2"
                                                        checked={line.nonconformities.includes(
                                                            category,
                                                        )}
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'lines',
                                                                form.data.lines.map(
                                                                    (
                                                                        entry,
                                                                        position,
                                                                    ) =>
                                                                        position ===
                                                                        i
                                                                            ? {
                                                                                  ...entry,
                                                                                  nonconformities:
                                                                                      event
                                                                                          .target
                                                                                          .checked
                                                                                          ? [
                                                                                                ...entry.nonconformities,
                                                                                                category,
                                                                                            ]
                                                                                          : entry.nonconformities.filter(
                                                                                                (
                                                                                                    value,
                                                                                                ) =>
                                                                                                    value !==
                                                                                                    category,
                                                                                            ),
                                                                              }
                                                                            : entry,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                    {caption}
                                                </Label>
                                            ))}
                                        </div>
                                    </fieldset>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`accepted-${line.line_id}`}
                                            >
                                                Accepted quantity
                                            </Label>
                                            <Input
                                                id={`accepted-${line.line_id}`}
                                                type="number"
                                                required
                                                min="0"
                                                max={
                                                    document.lines[i]
                                                        .quantity_delivered
                                                }
                                                value={line.quantity_accepted}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'quantity_accepted',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`condition-${line.line_id}`}
                                            >
                                                Accepted material condition
                                            </Label>
                                            <FormSelect
                                                id={`condition-${line.line_id}`}
                                                value={line.condition}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'condition',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="conforming">
                                                    Conforms to specifications
                                                </option>
                                                <option value="damaged">
                                                    Damaged
                                                </option>
                                                <option value="nonconforming">
                                                    Non-conforming
                                                </option>
                                            </FormSelect>
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`disposition-${line.line_id}`}
                                            >
                                                Destination
                                            </Label>
                                            <FormSelect
                                                id={`disposition-${line.line_id}`}
                                                value={line.disposition}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'disposition',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="warehouse">
                                                    Warehouse / property
                                                    register
                                                </option>
                                                <option value="direct_use">
                                                    Direct end-user use
                                                </option>
                                                <option value="calibration">
                                                    Water meter calibration
                                                    completed
                                                </option>
                                            </FormSelect>
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`location-${line.line_id}`}
                                            >
                                                Storage location / end-user
                                                destination
                                            </Label>
                                            <Input
                                                id={`location-${line.line_id}`}
                                                maxLength={255}
                                                value={line.storage_location}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'storage_location',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2 sm:col-span-2">
                                            <Label
                                                htmlFor={`disposition-ref-${line.line_id}`}
                                            >
                                                Passed calibration / signed
                                                end-user endorsement reference
                                            </Label>
                                            <Input
                                                id={`disposition-ref-${line.line_id}`}
                                                maxLength={255}
                                                value={
                                                    line.disposition_reference
                                                }
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'disposition_reference',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`findings-${line.line_id}`}
                                        >
                                            Findings, discrepancies,
                                            certifications, test reports, and
                                            photo references
                                        </Label>
                                        <textarea
                                            id={`findings-${line.line_id}`}
                                            className={areaClass}
                                            required
                                            maxLength={3000}
                                            value={line.findings}
                                            onChange={(e) =>
                                                updateLine(
                                                    i,
                                                    'findings',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </article>
                            ))}
                        </>
                    )}
                    {action === 'accept' && (
                        <>
                            <p className="rounded-lg bg-muted p-3 text-sm">
                                Only inspected, accepted quantities will be
                                posted. Direct-use consumables are recorded as
                                delivered to the end user. Property registration
                                uses actual unit cost and formal accountability
                                follows separately.
                            </p>
                            <div className="space-y-2">
                                <Label htmlFor="signed-acceptance">
                                    Completed IAR and receiving approval /
                                    signature reference
                                </Label>
                                <Input
                                    id="signed-acceptance"
                                    required
                                    maxLength={255}
                                    value={
                                        form.data.signed_acceptance_reference
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'signed_acceptance_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </>
                    )}
                    {action === 'record_supplier_return' && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="return-reference">
                                    Supplier return / replacement
                                    acknowledgement
                                </Label>
                                <Input
                                    id="return-reference"
                                    required
                                    maxLength={255}
                                    value={form.data.return_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'return_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="returned-date">
                                    Date rejected materials were physically
                                    returned
                                </Label>
                                <DatePicker
                                    id="returned-date"
                                    required
                                    max={localReceiptTime().slice(0, 10)}
                                    value={form.data.returned_at}
                                    onChange={(value) =>
                                        form.setData('returned_at', value)
                                    }
                                />
                            </div>
                            <p className="text-sm sm:col-span-2">
                                Confirm that all rejected quantities on this
                                notice were returned. This records the physical
                                handover and does not deduct usable stock.
                            </p>
                        </div>
                    )}
                    {action === 'endorse' && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="endorsement-reference">
                                    Pre-audit / accounting endorsement reference
                                </Label>
                                <Input
                                    id="endorsement-reference"
                                    required
                                    maxLength={255}
                                    value={form.data.endorsement_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'endorsement_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="endorsement-recipient">
                                    Internal Control / Accounting / Planning
                                    recipient
                                </Label>
                                <Input
                                    id="endorsement-recipient"
                                    required
                                    maxLength={255}
                                    value={form.data.endorsement_recipient}
                                    onChange={(e) =>
                                        form.setData(
                                            'endorsement_recipient',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="decision-remarks">
                            Decision remarks
                        </Label>
                        <textarea
                            id="decision-remarks"
                            className={areaClass}
                            required
                            maxLength={3000}
                            value={form.data.remarks}
                            onChange={(e) =>
                                form.setData('remarks', e.target.value)
                            }
                        />
                    </div>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(e) =>
                                form.setData('attested', e.target.checked)
                            }
                        />
                        I confirm the physical findings, quantities, and
                        supporting documents justify this decision.
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={close}
                        >
                            Close
                        </Button>
                        <Button
                            disabled={form.processing || !form.data.attested}
                        >
                            {titles[action]}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
export default function DeliveryShow({
    document,
    allowedActions,
    employees,
    today,
}: {
    document: Delivery;
    allowedActions: string[];
    employees: HrisReference[];
    today: string;
}) {
    const [action, setAction] = useState<string | null>(null);

    return (
        <>
            <Head title={document.document_no} />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap justify-between gap-3">
                    <div>
                        <Link href={index()} className="text-sm underline">
                            Delivery inspection
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {document.document_no}
                        </h1>
                        <Badge variant="outline">
                            {title(document.status)}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(
                            [
                                'iar',
                                'receiving-report',
                                ...(document.inspection ? ['evaluation'] : []),
                                ...(document.supplier_return_no
                                    ? ['supplier-return']
                                    : []),
                            ] as const
                        ).map((value) => (
                            <Button
                                key={value}
                                variant="outline"
                                nativeButton={false}
                                render={
                                    <a
                                        target="_blank"
                                        rel="noreferrer"
                                        href={printDelivery.url({
                                            delivery: document.id,
                                            document: value,
                                        })}
                                    />
                                }
                            >
                                Print {value === 'iar' ? 'IAR' : title(value)}
                            </Button>
                        ))}
                    </div>
                </div>
                <section className="space-y-3 rounded-lg border p-4">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        {[
                            [
                                'PR / PO',
                                document.procurement_request.pr_no +
                                    ' / ' +
                                    document.purchase_order_no,
                            ],
                            [
                                'Supplier / Invoice',
                                document.supplier_name +
                                    ' / ' +
                                    document.invoice_number,
                            ],
                            ['Delivery note', document.delivery_reference],
                            [
                                'Physical receipt (Manila)',
                                manilaTime(document.received_at),
                            ],
                            [
                                'Receiving department',
                                document.receiving_department,
                            ],
                            [
                                'NOA / NTP',
                                (document.noa_reference ??
                                    'See document basis') +
                                    ' / ' +
                                    (document.ntp_reference ??
                                        'See document basis'),
                            ],
                            [
                                'Joint inspection notification',
                                document.notification_reference,
                            ],
                            [
                                'PO / TOR verification',
                                document.order_document_basis,
                            ],
                            [
                                'Packaging / receipt findings',
                                document.packaging_findings,
                            ],
                            [
                                'Posting',
                                document.accepted_at
                                    ? manilaTime(document.accepted_at)
                                    : 'Nothing has been posted',
                            ],
                        ].map(([name, value]) => (
                            <div key={name}>
                                <dt className="text-muted-foreground">
                                    {name}
                                </dt>
                                <dd>{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {document.inspection && (
                        <div className="space-y-1 border-t pt-3 text-sm">
                            {Object.entries(document.inspection)
                                .filter(
                                    ([, value]) =>
                                        typeof value === 'string' && value,
                                )
                                .map(([key, value]) => (
                                    <p key={key}>
                                        {title(key)}: {String(value)}
                                    </p>
                                ))}
                        </div>
                    )}
                </section>
                <div className="space-y-3 md:hidden">
                    {document.lines.map((line) => (
                        <article
                            key={line.id}
                            className="space-y-2 rounded-lg border p-4"
                        >
                            <h2 className="font-medium">{line.item_name}</h2>
                            <p className="text-sm">{line.specifications}</p>
                            <p className="text-sm">
                                Delivered: {line.quantity_delivered}{' '}
                                {line.unit_of_measure} / Accepted:{' '}
                                {line.quantity_accepted ?? 'Pending'} / Unit
                                cost: {money(line.unit_cost)}
                            </p>
                            <p className="text-sm">
                                {line.condition
                                    ? title(line.condition)
                                    : 'Awaiting inspection'}{' '}
                                /{' '}
                                {line.disposition
                                    ? title(line.disposition)
                                    : 'Destination pending'}
                            </p>
                            <p className="text-sm">
                                {line.storage_location} /{' '}
                                {line.disposition_reference}
                            </p>
                            <p className="text-sm">{line.findings}</p>
                        </article>
                    ))}
                </div>
                <div className="hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Material / specifications</TableHead>
                                <TableHead>Delivered</TableHead>
                                <TableHead>Accepted / rejected</TableHead>
                                <TableHead>Unit cost</TableHead>
                                <TableHead>Inspection / destination</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {document.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell>
                                        {line.item_name}
                                        <p className="text-xs text-muted-foreground">
                                            {line.specifications}
                                        </p>
                                        {line.expiration_date && (
                                            <p className="text-xs">
                                                Expiry:{' '}
                                                {line.expiration_date.slice(
                                                    0,
                                                    10,
                                                )}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {line.quantity_delivered}{' '}
                                        {line.unit_of_measure}
                                    </TableCell>
                                    <TableCell>
                                        {line.quantity_accepted ?? 'Pending'}
                                        {line.quantity_accepted !== null && (
                                            <p className="text-xs">
                                                Rejected:{' '}
                                                {line.quantity_delivered -
                                                    line.quantity_accepted}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {money(line.unit_cost)}
                                    </TableCell>
                                    <TableCell>
                                        {line.condition
                                            ? title(line.condition)
                                            : 'Pending'}{' '}
                                        /{' '}
                                        {line.disposition
                                            ? title(line.disposition)
                                            : 'Pending'}
                                        <p>{line.storage_location}</p>
                                        <p className="text-xs">
                                            {line.disposition_reference}
                                        </p>
                                        <p className="text-xs">
                                            {line.findings}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <div className="flex flex-wrap gap-2">
                    {allowedActions.map((value) => (
                        <Button
                            key={value}
                            variant={value === 'cancel' ? 'outline' : 'default'}
                            onClick={() => setAction(value)}
                        >
                            {titles[value]}
                        </Button>
                    ))}
                    <Button
                        nativeButton={false}
                        variant="outline"
                        render={
                            <Link
                                href={create({
                                    query: {
                                        procurement_request_id:
                                            document.procurement_request.id,
                                    },
                                })}
                            />
                        }
                    >
                        Record another shipment / replacement
                    </Button>
                </div>
                <section className="space-y-3">
                    <h2 className="font-semibold">Delivery history</h2>
                    {document.actions.map((entry) => (
                        <article
                            key={entry.id}
                            className="space-y-1 rounded-lg border p-3 text-sm"
                        >
                            <p className="font-medium">
                                {title(entry.action)} — {entry.actor_name}
                            </p>
                            <p className="text-muted-foreground">
                                {manilaTime(entry.created_at)}
                            </p>
                            <p>{entry.remarks}</p>
                            {entry.action === 'postpone' && (
                                <a
                                    className="underline"
                                    target="_blank"
                                    rel="noreferrer"
                                    href={printPostponement.url({
                                        delivery: document.id,
                                        action: entry.id,
                                    })}
                                >
                                    Print preserved postponement notice
                                </a>
                            )}
                            {Object.entries(entry.metadata ?? {})
                                .filter(
                                    ([, value]) => typeof value === 'string',
                                )
                                .map(([key, value]) => (
                                    <p key={key}>
                                        {title(key)}: {String(value)}
                                    </p>
                                ))}
                        </article>
                    ))}
                </section>
                {action === 'postpone' && (
                    <PostponementDialog
                        document={document}
                        employees={employees}
                        today={today}
                        close={() => setAction(null)}
                    />
                )}
                {action === 'authorize_supplier_return' && (
                    <SupplierReturnAuthorization
                        document={document}
                        employees={employees}
                        today={today}
                        close={() => setAction(null)}
                    />
                )}
                {action &&
                    !['postpone', 'authorize_supplier_return'].includes(
                        action,
                    ) && (
                        <DeliveryDecision
                            key={document.id + '-' + action}
                            document={document}
                            action={action}
                            close={() => setAction(null)}
                            employees={employees}
                            today={today}
                        />
                    )}
            </div>
        </>
    );
}
DeliveryShow.layout = {
    breadcrumbs: [{ title: 'Delivery Inspection', href: index.url() }],
};
