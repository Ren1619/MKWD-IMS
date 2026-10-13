import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
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
    index,
    print as printCount,
    record,
    transition,
} from '@/routes/inventory/counts';
import type { Paginated } from '@/types/inventory';
import { statusLabel } from './types';
import type { CountLine, CountSession } from './types';

type CountAction = {
    actor_name: string;
    id: number;
    action: string;
    remarks: string | null;
    created_at: string;
    actor: { name: string };
};
const labels: Record<string, string> = {
    start: 'Start and freeze stock',
    submit: 'Submit for verification',
    verify: 'Verify reconciliation',
    approve: 'Approve and post adjustments',
    return_for_recount: 'Return for recount',
    cancel: 'Cancel count',
};

function ObservationDialog({
    session,
    line,
    close,
}: {
    session: CountSession;
    line: CountLine;
    close: () => void;
}) {
    const latest = line.latest_entry;
    const form = useForm({
        latest_entry_id: latest?.id ?? null,
        quantity: '',
        condition: latest?.condition ?? 'good',
        location: latest?.location ?? '',
        aisle: latest?.aisle ?? '',
        bin: latest?.bin ?? '',
        counter_name: latest?.counter_name ?? '',
        checker_name: latest?.checker_name ?? '',
        validator_name: latest?.validator_name ?? '',
        remarks: '',
        adjustment_unit_cost: line.adjustment_unit_cost ?? line.unit_cost,
        valuation_basis: line.valuation_basis ?? '',
        attested: false,
    });
    const expected = line.book_quantity - line.before_count_issues;
    const variance =
        form.data.quantity === ''
            ? null
            : Number(form.data.quantity) + line.after_count_issues - expected;

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {latest ? 'Record a recount' : 'Record physical count'}{' '}
                        — {line.item_name}
                    </DialogTitle>
                    <DialogDescription>
                        Tag {line.tag_no}. Expected at counting: {expected}{' '}
                        {line.unit_of_measure}. Enter what is physically present
                        now, excluding segregated deliveries. Enter an explicit
                        zero when nothing was found. The{' '}
                        {line.after_count_issues} authorized after-count issues
                        will be added back for reconciliation. Variances need an
                        agreeing recount.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(
                            record.url({ session: session.id, line: line.id }),
                            { preserveScroll: true, onSuccess: close },
                        );
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="observed-quantity">
                                Physical quantity ({line.unit_of_measure})
                            </Label>
                            <Input
                                id="observed-quantity"
                                type="number"
                                min={0}
                                max={1000000}
                                step={1}
                                required
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData('quantity', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="observed-condition">
                                Condition
                            </Label>
                            <FormSelect
                                id="observed-condition"
                                value={form.data.condition}
                                onChange={(event) =>
                                    form.setData(
                                        'condition',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="good">In good condition</option>
                                <option value="defective">
                                    Defective / for evaluation
                                </option>
                            </FormSelect>
                        </div>
                        {(
                            [
                                'location',
                                'aisle',
                                'bin',
                                'counter_name',
                                'checker_name',
                                'validator_name',
                            ] as const
                        ).map((field) => (
                            <div key={field} className="grid gap-2">
                                <Label htmlFor={field}>
                                    {statusLabel(field)}
                                    {field === 'aisle' || field === 'bin'
                                        ? ' (optional)'
                                        : ''}
                                </Label>
                                <Input
                                    id={field}
                                    required={
                                        field !== 'aisle' && field !== 'bin'
                                    }
                                    maxLength={255}
                                    value={form.data[field]}
                                    onChange={(event) =>
                                        form.setData(field, event.target.value)
                                    }
                                />
                            </div>
                        ))}
                    </div>
                    <p className="text-sm">
                        Variance:{' '}
                        {variance === null
                            ? 'Not counted'
                            : variance > 0
                              ? `+${variance} overage`
                              : variance < 0
                                ? `${variance} shortage`
                                : 'No difference'}
                    </p>
                    {variance !== null && variance > 0 && (
                        <div className="grid gap-4 rounded-lg border p-4">
                            <div className="grid gap-2">
                                <Label htmlFor="overage-cost">
                                    Overage unit cost (PHP)
                                </Label>
                                <Input
                                    id="overage-cost"
                                    type="number"
                                    required
                                    min={0}
                                    step="0.01"
                                    value={form.data.adjustment_unit_cost}
                                    onChange={(event) =>
                                        form.setData(
                                            'adjustment_unit_cost',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="cost-basis">
                                    Valuation basis / accounting reference
                                </Label>
                                <textarea
                                    rows={3}
                                    className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    id="cost-basis"
                                    required
                                    maxLength={3000}
                                    value={form.data.valuation_basis}
                                    onChange={(event) =>
                                        form.setData(
                                            'valuation_basis',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="observation-remarks">
                            Findings / explanation
                            {latest ? ' (required for recount)' : ''}
                        </Label>
                        <textarea
                            rows={3}
                            className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            id="observation-remarks"
                            required={latest !== null}
                            maxLength={3000}
                            value={form.data.remarks}
                            onChange={(event) =>
                                form.setData('remarks', event.target.value)
                            }
                        />
                    </div>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(event) =>
                                form.setData('attested', event.target.checked)
                            }
                        />
                        I confirm this observation and the counter, checker, and
                        validator details match the signed inventory tag.
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                    <Button disabled={form.processing || !form.data.attested}>
                        Save observation
                    </Button>
                </form>
                {line.entries.length > 0 && (
                    <details className="rounded-lg border p-3">
                        <summary className="cursor-pointer text-sm font-medium">
                            Previous observations ({line.entries.length})
                        </summary>
                        <ul className="mt-3 grid gap-2 text-sm">
                            {line.entries.map((entry) => (
                                <li key={entry.id}>
                                    Observed:{' '}
                                    {entry.observed_quantity ?? entry.quantity};{' '}
                                    reconciled count: {entry.quantity};{' '}
                                    {entry.counter_name}, {entry.checker_name},{' '}
                                    {entry.validator_name}. {entry.remarks}
                                </li>
                            ))}
                        </ul>
                    </details>
                )}
            </DialogContent>
        </Dialog>
    );
}

function ActionDialog({
    session,
    action,
    line,
    close,
}: {
    session: CountSession;
    action: string;
    line: CountLine | null;
    close: () => void;
}) {
    const movement =
        action === 'emergency_issue' || action === 'segregated_receipt';
    const form = useForm({
        action,
        idempotency_key: crypto.randomUUID(),
        line_id: line?.id ?? null,
        quantity: '',
        reference_no: '',
        approval_reference: '',
        recipient_name: '',
        timing: line?.latest_entry ? 'after_count' : 'before_count',
        unit_cost: '',
        expiration_date: '',
        remarks: '',
        attested: false,
    });

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {labels[action] ?? statusLabel(action)}
                        {line ? ` — ${line.item_name}` : ''}
                    </DialogTitle>
                    <DialogDescription>
                        {action === 'approve'
                            ? 'Approval posts the confirmed variances once, preserves batch costs, clears affected reservations for review, and reopens normal stock movements.'
                            : action === 'start'
                              ? 'This establishes cut-off now and pauses normal stock movements for all consumables.'
                              : action === 'cancel'
                                ? 'Cancellation reopens stock without posting count adjustments. Authorized movements already recorded remain in stock history.'
                                : action === 'segregated_receipt'
                                  ? 'Keep this delivery physically separate. It increases stock but is excluded from the physical count.'
                                  : action === 'emergency_issue'
                                    ? 'Use an approved emergency requisition. The system records its timing against the count cut-off.'
                                    : 'Confirm this action against the signed count records and reconciliation evidence.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(transition.url(session.id), {
                            preserveScroll: true,
                            onSuccess: close,
                        });
                    }}
                >
                    {movement && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="movement-quantity">
                                    Quantity ({line?.unit_of_measure})
                                </Label>
                                <Input
                                    id="movement-quantity"
                                    type="number"
                                    required
                                    min={1}
                                    step={1}
                                    value={form.data.quantity}
                                    onChange={(event) =>
                                        form.setData(
                                            'quantity',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="movement-reference">
                                    {action === 'emergency_issue'
                                        ? 'Approved RIS / issuance reference'
                                        : 'Accepted receiving document reference'}
                                </Label>
                                <Input
                                    id="movement-reference"
                                    required
                                    maxLength={100}
                                    value={form.data.reference_no}
                                    onChange={(event) =>
                                        form.setData(
                                            'reference_no',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="movement-approval">
                                    Authorization reference
                                </Label>
                                <Input
                                    id="movement-approval"
                                    required
                                    maxLength={120}
                                    value={form.data.approval_reference}
                                    onChange={(event) =>
                                        form.setData(
                                            'approval_reference',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            {action === 'emergency_issue' ? (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="emergency-recipient">
                                            Recipient name from approved
                                            requisition
                                        </Label>
                                        <Input
                                            id="emergency-recipient"
                                            required
                                            maxLength={255}
                                            value={form.data.recipient_name}
                                            onChange={(event) =>
                                                form.setData(
                                                    'recipient_name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <p className="text-sm">
                                        {line?.latest_entry
                                            ? 'ISSUED AFTER COUNT'
                                            : 'BEFORE COUNT, AFTER-CUT-OFF'}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="receipt-cost">
                                            Actual unit cost (PHP)
                                        </Label>
                                        <Input
                                            id="receipt-cost"
                                            type="number"
                                            required
                                            min={0}
                                            step="0.01"
                                            value={form.data.unit_cost}
                                            onChange={(event) =>
                                                form.setData(
                                                    'unit_cost',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="receipt-expiration">
                                            Expiry date (optional)
                                        </Label>
                                        <DatePicker
                                            id="receipt-expiration"
                                            value={form.data.expiration_date}
                                            onChange={(value) =>
                                                form.setData(
                                                    'expiration_date',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                </>
                            )}
                        </>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="action-remarks">
                            {action === 'approve'
                                ? 'Approval / adjustment authorization reference and remarks'
                                : 'Remarks / reason'}
                        </Label>
                        <textarea
                            rows={3}
                            className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            id="action-remarks"
                            required={[
                                'approve',
                                'cancel',
                                'return_for_recount',
                                'emergency_issue',
                                'segregated_receipt',
                            ].includes(action)}
                            maxLength={3000}
                            value={form.data.remarks}
                            onChange={(event) =>
                                form.setData('remarks', event.target.value)
                            }
                        />
                    </div>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(event) =>
                                form.setData('attested', event.target.checked)
                            }
                        />
                        I confirm this action is accurate and authorized.
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                    <Button
                        variant={
                            action === 'cancel' ? 'destructive' : 'default'
                        }
                        disabled={form.processing || !form.data.attested}
                    >
                        Confirm {labels[action] ?? statusLabel(action)}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function CountShow({
    session,
    lines,
    actions,
    summary,
    allowedActions,
}: {
    session: CountSession;
    lines: Paginated<CountLine>;
    actions: CountAction[];
    summary: { items: number; counted: number };
    allowedActions: string[];
}) {
    const [observation, setObservation] = useState<CountLine | null>(null);
    const [decision, setDecision] = useState<{
        action: string;
        line: CountLine | null;
    } | null>(null);

    return (
        <>
            <Head title={session.document_no} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Link
                            href={index()}
                            className="text-sm text-muted-foreground underline"
                        >
                            Physical counts
                        </Link>
                        <h1 className="mt-2 text-2xl font-semibold">
                            {session.title}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {session.document_no} · Memorandum{' '}
                            {session.memorandum_reference}
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {statusLabel(session.status)}
                    </Badge>
                </div>
                <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                    <p>
                        {summary.counted} of {summary.items} items counted.{' '}
                        {session.cutoff_at
                            ? `Cut-off: ${new Date(session.cutoff_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' })}`
                            : 'Book balances will be frozen when the session starts.'}
                    </p>
                    <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                        {session.team_details}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {allowedActions.map((action) => (
                        <Button
                            key={action}
                            variant={
                                action === 'cancel' ? 'outline' : 'default'
                            }
                            onClick={() => setDecision({ action, line: null })}
                        >
                            {labels[action]}
                        </Button>
                    ))}
                    {session.cutoff_at &&
                        (
                            [
                                'worksheet',
                                'tags',
                                'reconciliation',
                                'adjustments',
                            ] as const
                        ).map((document) => (
                            <Button
                                key={document}
                                variant="outline"
                                render={
                                    <a
                                        href={printCount.url({
                                            session: session.id,
                                            document,
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                    />
                                }
                            >
                                Print {document}
                            </Button>
                        ))}
                </div>
                {session.status === 'verified' &&
                    !allowedActions.includes('approve') && (
                        <p className="text-sm text-muted-foreground">
                            An independent inventory manager must approve this
                            reconciliation.
                        </p>
                    )}
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item / tag</TableHead>
                                <TableHead>Book at cut-off</TableHead>
                                <TableHead>Expected at count</TableHead>
                                <TableHead>Reconciled physical count</TableHead>
                                <TableHead>Variance</TableHead>
                                <TableHead>Cut-off movements</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {lines.data.map((line) => {
                                const expected =
                                    line.book_quantity -
                                    line.before_count_issues;
                                const variance = line.latest_entry
                                    ? line.latest_entry.quantity - expected
                                    : null;

                                return (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {line.item_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {line.stock_number} ·{' '}
                                                {line.tag_no}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            {line.book_quantity}{' '}
                                            {line.unit_of_measure}
                                        </TableCell>
                                        <TableCell>{expected}</TableCell>
                                        <TableCell>
                                            {line.latest_entry?.quantity ??
                                                'Not counted'}
                                        </TableCell>
                                        <TableCell
                                            className={
                                                variance !== null &&
                                                variance !== 0
                                                    ? 'font-medium text-amber-700 dark:text-amber-400'
                                                    : ''
                                            }
                                        >
                                            {variance === null
                                                ? '—'
                                                : variance > 0
                                                  ? `+${variance}`
                                                  : variance}
                                        </TableCell>
                                        <TableCell>
                                            <p>
                                                {line.before_count_issues}{' '}
                                                before /{' '}
                                                {line.after_count_issues} after
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {line.segregated_receipts}{' '}
                                                segregated receipts
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-2">
                                                {session.status ===
                                                    'counting' && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setObservation(
                                                                    line,
                                                                )
                                                            }
                                                        >
                                                            {line.latest_entry
                                                                ? 'Recount'
                                                                : 'Record count'}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDecision({
                                                                    action: 'emergency_issue',
                                                                    line,
                                                                })
                                                            }
                                                        >
                                                            Emergency issue
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setDecision({
                                                                    action: 'segregated_receipt',
                                                                    line,
                                                                })
                                                            }
                                                        >
                                                            Segregated receipt
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                            <details className="mt-2 text-xs">
                                                <summary className="cursor-pointer">
                                                    Observation history (
                                                    {line.entries.length})
                                                </summary>
                                                {line.entries.map((entry) => (
                                                    <p
                                                        key={entry.id}
                                                        className="mt-2 whitespace-normal"
                                                    >
                                                        #{entry.id}:{' '}
                                                        {entry.observed_quantity ??
                                                            entry.quantity}{' '}
                                                        {line.unit_of_measure};{' '}
                                                        {
                                                            entry.after_count_issues_at_observation
                                                        }{' '}
                                                        after-count issues;{' '}
                                                        reconciled count{' '}
                                                        {entry.quantity};{' '}
                                                        {entry.condition};{' '}
                                                        {entry.location}.{' '}
                                                        {entry.counter_name} /{' '}
                                                        {entry.checker_name} /{' '}
                                                        {entry.validator_name}.{' '}
                                                        {entry.remarks}
                                                    </p>
                                                ))}
                                            </details>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {lines.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="py-12 text-center text-muted-foreground"
                                    >
                                        Start this session to generate the item
                                        worksheet and numbered tags.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <DataPagination links={lines.links} />
                <details className="rounded-lg border p-4">
                    <summary className="cursor-pointer font-medium">
                        Activity and approvals
                    </summary>
                    <ul className="mt-4 grid gap-3 text-sm">
                        {actions.map((action) => (
                            <li key={action.id}>
                                <span className="font-medium">
                                    {statusLabel(action.action)}
                                </span>{' '}
                                — {action.actor_name}
                                <p className="text-muted-foreground">
                                    {new Date(action.created_at).toLocaleString(
                                        'en-PH',
                                        { timeZone: 'Asia/Manila' },
                                    )}{' '}
                                    · {action.remarks}
                                </p>
                            </li>
                        ))}
                    </ul>
                </details>
            </div>
            {observation && (
                <ObservationDialog
                    key={observation.id}
                    session={session}
                    line={observation}
                    close={() => setObservation(null)}
                />
            )}
            {decision && (
                <ActionDialog
                    key={`${decision.action}-${decision.line?.id}`}
                    session={session}
                    action={decision.action}
                    line={decision.line}
                    close={() => setDecision(null)}
                />
            )}
        </>
    );
}
CountShow.layout = {
    breadcrumbs: [{ title: 'Physical counts', href: index() }],
};
