import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    index,
    print as printReturn,
    transition,
} from '@/routes/inventory/returns';
import { label, money } from './types';
import type { ReturnDocument } from './types';

const titles: Record<string, string> = {
    verify: 'Inspect and verify return',
    approve: 'Approve and post accepted stock',
    reject: 'Reject return',
    cancel: 'Cancel return',
    endorse_accounting: 'Record accounting endorsement',
};
const areaClass =
    'min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
function DecisionDialog({
    document,
    action,
    close,
}: {
    document: ReturnDocument;
    action: string;
    close: () => void;
}) {
    const form = useForm({
        action,
        remarks: '',
        attested: false,
        inspection_reference: '',
        accounting_reference: '',
        accounting_recipient: '',
        lines: (document.lines ?? []).map((line) => ({
            line_id: line.id,
            quantity_accepted: '',
            condition: 'good',
            storage_location: '',
            inspection_remarks: '',
        })),
    });
    const updateLine = (
        position: number,
        field:
            | 'quantity_accepted'
            | 'condition'
            | 'storage_location'
            | 'inspection_remarks',
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
                        {document.document_no}. Inspection records the physical
                        findings. Approval adds accepted quantities to available
                        stock.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) =>
                            action === 'verify'
                                ? {
                                      action: data.action,
                                      remarks: data.remarks,
                                      attested: data.attested,
                                      inspection_reference:
                                          data.inspection_reference,
                                      lines: data.lines,
                                  }
                                : action === 'endorse_accounting'
                                  ? {
                                        action: data.action,
                                        remarks: data.remarks,
                                        attested: data.attested,
                                        accounting_reference:
                                            data.accounting_reference,
                                        accounting_recipient:
                                            data.accounting_recipient,
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
                    <InputError message={form.errors.action} />
                    {action === 'verify' && (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="inspection-reference">
                                    Inspection reference
                                </Label>
                                <Input
                                    id="inspection-reference"
                                    required
                                    value={form.data.inspection_reference}
                                    onChange={(event) =>
                                        form.setData(
                                            'inspection_reference',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.inspection_reference}
                                />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Enter accepted quantities explicitly. Use zero
                                for rejected materials. Accept only serviceable,
                                unexpired materials and assign their storage
                                location.
                            </p>
                            {form.data.lines.map((line, i) => (
                                <section
                                    key={line.line_id}
                                    className="space-y-3 rounded-lg border p-4"
                                >
                                    <p className="font-medium">
                                        {document.lines?.[i].item_name} —
                                        declared:{' '}
                                        {document.lines?.[i].quantity_declared}{' '}
                                        {document.lines?.[i].unit_of_measure}
                                    </p>
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
                                                min="0"
                                                max={
                                                    document.lines?.[i]
                                                        .quantity_declared
                                                }
                                                required
                                                value={line.quantity_accepted}
                                                onChange={(e) =>
                                                    updateLine(
                                                        i,
                                                        'quantity_accepted',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `lines.${i}.quantity_accepted`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`condition-${line.line_id}`}
                                            >
                                                Condition
                                            </Label>
                                            <FormSelect
                                                id={`condition-${line.line_id}`}
                                                value={line.condition}
                                                onChange={(event) =>
                                                    updateLine(
                                                        i,
                                                        'condition',
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="good">
                                                    Serviceable
                                                </option>
                                                <option value="defective">
                                                    Defective
                                                </option>
                                                <option value="discrepant">
                                                    Discrepant
                                                </option>
                                            </FormSelect>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`location-${line.line_id}`}
                                        >
                                            Storage location
                                        </Label>
                                        <Input
                                            id={`location-${line.line_id}`}
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
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`findings-${line.line_id}`}
                                        >
                                            Inspection findings and rejected
                                            quantities
                                        </Label>
                                        <textarea
                                            className={areaClass}
                                            id={`findings-${line.line_id}`}
                                            required
                                            value={line.inspection_remarks}
                                            onChange={(e) =>
                                                updateLine(
                                                    i,
                                                    'inspection_remarks',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `lines.${i}.inspection_remarks`
                                                ]
                                            }
                                        />
                                    </div>
                                </section>
                            ))}
                            <InputError message={form.errors.lines} />
                        </>
                    )}
                    {action === 'approve' && (
                        <p className="rounded-lg bg-muted p-3 text-sm">
                            Approval posts accepted quantities at their recorded
                            unit costs and expiry dates. Any rejected remainder
                            stays outside usable stock.
                        </p>
                    )}
                    {action === 'endorse_accounting' && (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="accounting-reference">
                                    Accounting endorsement reference
                                </Label>
                                <Input
                                    id="accounting-reference"
                                    required
                                    value={form.data.accounting_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'accounting_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.accounting_reference}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="accounting-recipient">
                                    Accounting recipient
                                </Label>
                                <Input
                                    id="accounting-recipient"
                                    required
                                    value={form.data.accounting_recipient}
                                    onChange={(e) =>
                                        form.setData(
                                            'accounting_recipient',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.accounting_recipient}
                                />
                            </div>
                        </>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="decision-remarks">
                            Decision remarks
                        </Label>
                        <textarea
                            className={areaClass}
                            id="decision-remarks"
                            required
                            value={form.data.remarks}
                            onChange={(e) =>
                                form.setData('remarks', e.target.value)
                            }
                        />
                        <InputError message={form.errors.remarks} />
                    </div>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(e) =>
                                form.setData('attested', e.target.checked)
                            }
                        />
                        I confirm that the physical findings and supporting
                        documents justify this decision.
                    </label>
                    <InputError message={form.errors.attested} />
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={close}
                        >
                            Cancel
                        </Button>
                        <Button disabled={form.processing}>
                            {titles[action]}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
export default function ReturnShow({
    document,
    allowedActions,
}: {
    document: ReturnDocument;
    allowedActions: string[];
}) {
    const [action, setAction] = useState<string | null>(null);

    return (
        <>
            <Head title={document.document_no} />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link className="text-sm underline" href={index()}>
                            Return to Stock
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {document.document_no}
                        </h1>
                        <Badge variant="outline">
                            {label(document.status)}
                        </Badge>
                    </div>
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={
                            <a
                                href={printReturn.url(document.id)}
                                target="_blank"
                                rel="noreferrer"
                            />
                        }
                    >
                        Print RMS / MRS
                    </Button>
                </div>
                <section className="space-y-3 rounded-lg border p-4">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Returned by / department
                            </dt>
                            <dd>
                                {document.returned_by_name} /{' '}
                                {document.department_name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Physical return date
                            </dt>
                            <dd>{document.return_date.slice(0, 10)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Endorsement reference
                            </dt>
                            <dd>{document.endorsement_reference}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Issue evidence
                            </dt>
                            <dd>
                                {document.source_mode === 'linked'
                                    ? 'Linked issued batches'
                                    : document.legacy_issue_reference}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm">{document.reason}</p>
                    <p className="text-sm font-medium">
                        {document.approved_at
                            ? `Stock posted: ${new Date(document.approved_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' })}`
                            : 'Stock has not been posted.'}
                    </p>
                </section>
                <div className="space-y-3 md:hidden">
                    {document.lines?.map((line) => (
                        <section
                            key={line.id}
                            className="space-y-2 rounded-lg border p-4"
                        >
                            <p className="font-medium">{line.item_name}</p>
                            <p className="text-sm">
                                {line.stock_number} / {line.unit_of_measure} /{' '}
                                {line.source_issue_reference}
                            </p>
                            <p className="text-sm">
                                Declared: {line.quantity_declared} · Accepted:{' '}
                                {line.quantity_accepted ?? 'Pending'} · Unit
                                cost: {money(line.unit_cost)}
                            </p>
                            <p className="text-sm">
                                {line.condition
                                    ? label(line.condition)
                                    : 'Awaiting inspection'}{' '}
                                / {line.storage_location ?? 'Location pending'}
                            </p>
                            <p className="text-sm">{line.inspection_remarks}</p>
                            {line.expiration_date && (
                                <p className="text-sm">
                                    Expires: {line.expiration_date.slice(0, 10)}
                                </p>
                            )}
                            {line.valuation_basis && (
                                <p className="text-sm">
                                    Valuation basis: {line.valuation_basis}
                                </p>
                            )}
                        </section>
                    ))}
                </div>
                <div className="hidden md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Material / issue</TableHead>
                                <TableHead>Declared</TableHead>
                                <TableHead>Accepted</TableHead>
                                <TableHead>Unit cost</TableHead>
                                <TableHead>Inspection / location</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {document.lines?.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell>
                                        <p className="font-medium">
                                            {line.item_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {line.stock_number} /{' '}
                                            {line.source_issue_reference}
                                        </p>
                                        {line.expiration_date && (
                                            <p className="text-xs">
                                                Expires:{' '}
                                                {line.expiration_date.slice(
                                                    0,
                                                    10,
                                                )}
                                            </p>
                                        )}
                                        {line.valuation_basis && (
                                            <p className="text-xs">
                                                Valuation:{' '}
                                                {line.valuation_basis}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {line.quantity_declared}{' '}
                                        {line.unit_of_measure}
                                    </TableCell>
                                    <TableCell>
                                        {line.quantity_accepted ?? 'Pending'}
                                        {line.quantity_accepted !== null && (
                                            <p className="text-xs">
                                                Rejected:{' '}
                                                {line.quantity_declared -
                                                    line.quantity_accepted}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {money(line.unit_cost)}
                                    </TableCell>
                                    <TableCell>
                                        {line.condition
                                            ? label(line.condition)
                                            : 'Pending'}
                                        <p>{line.storage_location}</p>
                                        <p className="text-xs">
                                            {line.inspection_remarks}
                                        </p>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {allowedActions.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {allowedActions.map((value) => (
                            <Button
                                key={value}
                                variant={
                                    value === 'reject' || value === 'cancel'
                                        ? 'outline'
                                        : 'default'
                                }
                                onClick={() => setAction(value)}
                            >
                                {titles[value]}
                            </Button>
                        ))}
                    </div>
                )}
                <section className="space-y-3">
                    <h2 className="font-semibold">Decision history</h2>
                    {document.actions?.map((entry) => (
                        <article
                            key={entry.id}
                            className="rounded-lg border p-3 text-sm"
                        >
                            <p className="font-medium">
                                {label(entry.action)} — {entry.actor_name}
                            </p>
                            <p className="text-muted-foreground">
                                {new Date(entry.created_at).toLocaleString(
                                    'en-PH',
                                    { timeZone: 'Asia/Manila' },
                                )}
                            </p>
                            <p>{entry.remarks}</p>
                            {entry.action === 'endorse_accounting' && (
                                <p>
                                    {String(
                                        entry.metadata?.accounting_reference ??
                                            '',
                                    )}{' '}
                                    /{' '}
                                    {String(
                                        entry.metadata?.accounting_recipient ??
                                            '',
                                    )}
                                </p>
                            )}
                        </article>
                    ))}
                </section>
                {action && (
                    <DecisionDialog
                        key={`${document.id}-${action}`}
                        document={document}
                        action={action}
                        close={() => setAction(null)}
                    />
                )}
            </div>
        </>
    );
}
ReturnShow.layout = {
    breadcrumbs: [{ title: 'Return to Stock', href: index.url() }],
};
