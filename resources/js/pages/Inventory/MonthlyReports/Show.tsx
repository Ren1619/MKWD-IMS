import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
    show,
    transition,
    print as printMonthly,
} from '@/routes/inventory/monthly_reports';
import { Readiness } from './Readiness';
import { label, manilaTime, areaClass } from './types';
import type { Packet, Readiness as ReadinessData } from './types';
const titles: Record<string, string> = {
    check: 'Check reconciled monthly reports',
    note: 'Note monthly reports',
    approve: 'Approve monthly package',
    refresh: 'Refresh snapshot and restart sign-off',
    cancel: 'Cancel monthly package',
    transmit: 'Record Accounting transmittal',
};
function MonthlyDecision({
    packet,
    action,
    close,
}: {
    packet: Packet;
    action: string;
    close: () => void;
}) {
    const form = useForm({
        action,
        snapshot_id: packet.snapshot.id,
        remarks: '',
        attested: false,
        signature_reference: '',
        transmittal_reference: '',
        transmittal_recipient: '',
        jev_reference: '',
    });

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) {
                    close();
                }
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{titles[action]}</DialogTitle>
                    <DialogDescription>
                        {packet.document_no} / Snapshot{' '}
                        {packet.snapshot.version}. Confirm the records and
                        signed evidence supporting this decision.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            signature_reference: [
                                'check',
                                'note',
                                'approve',
                            ].includes(action)
                                ? data.signature_reference
                                : null,
                            transmittal_reference:
                                action === 'transmit'
                                    ? data.transmittal_reference
                                    : null,
                            transmittal_recipient:
                                action === 'transmit'
                                    ? data.transmittal_recipient
                                    : null,
                            jev_reference:
                                action === 'transmit'
                                    ? data.jev_reference
                                    : null,
                        }));
                        form.patch(transition.url(packet.id), {
                            onSuccess: close,
                        });
                    }}
                >
                    {['check', 'note', 'approve'].includes(action) && (
                        <div className="space-y-2">
                            <Label htmlFor="signature-reference">
                                Signed review / approval evidence reference
                            </Label>
                            <Input
                                id="signature-reference"
                                required
                                maxLength={255}
                                value={form.data.signature_reference}
                                onChange={(e) =>
                                    form.setData(
                                        'signature_reference',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    )}
                    {action === 'refresh' && (
                        <p className="rounded-lg bg-muted p-3 text-sm">
                            A new snapshot will preserve the current report
                            values. Earlier snapshots and decisions stay in
                            history. Checking, noting, and approval must be
                            repeated.
                        </p>
                    )}
                    {action === 'transmit' && (
                        <>
                            {(
                                [
                                    [
                                        'transmittal_reference',
                                        'Transmittal letter reference',
                                        true,
                                    ],
                                    [
                                        'transmittal_recipient',
                                        'ABD Department Manager / Accounting recipient',
                                        true,
                                    ],
                                    [
                                        'jev_reference',
                                        'Accounting JEV reference (if assigned)',
                                        false,
                                    ],
                                ] as const
                            ).map(([key, title, required]) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{title}</Label>
                                    <Input
                                        id={key}
                                        required={required}
                                        maxLength={
                                            key === 'jev_reference' ? 120 : 255
                                        }
                                        value={form.data[key]}
                                        onChange={(e) =>
                                            form.setData(key, e.target.value)
                                        }
                                    />
                                </div>
                            ))}
                            <p className="text-xs text-muted-foreground">
                                Record the actual handover. Staff send the
                                signed transmittal and Accounting prepares the
                                journal entry.
                            </p>
                        </>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="monthly-remarks">
                            Decision remarks
                        </Label>
                        <textarea
                            id="monthly-remarks"
                            required
                            maxLength={3000}
                            className={areaClass}
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
                        I confirm this decision and its supporting records are
                        accurate.
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
export default function MonthlyShow({
    document,
    currentReadiness,
    isStale,
    allowedActions,
}: {
    document: Packet;
    currentReadiness: ReadinessData | null;
    isStale: boolean;
    allowedActions: string[];
}) {
    const [action, setAction] = useState<string | null>(null);
    const [reportKey, setReportKey] = useState(
        Object.keys(document.snapshot.payload.reports)[0],
    );
    const table = document.snapshot.payload.reports[reportKey];
    const finalized = ['approved', 'transmitted'].includes(document.status);

    return (
        <>
            <Head title={document.document_no} />
            <div className="space-y-6 p-4 sm:p-6">
                <div className="flex flex-wrap justify-between gap-3">
                    <div>
                        <Link href={index()} className="text-sm underline">
                            Monthly report packages
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {document.document_no}
                        </h1>
                        <Badge variant="outline">
                            {label(document.status)}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <a
                                    href={printMonthly.url({
                                        report: document.id,
                                        document: 'packet',
                                    })}
                                    target="_blank"
                                    rel="noreferrer"
                                />
                            }
                        >
                            Print complete package
                        </Button>
                        {finalized && (
                            <Button
                                variant="outline"
                                nativeButton={false}
                                render={
                                    <a
                                        href={printMonthly.url({
                                            report: document.id,
                                            document: 'transmittal',
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                    />
                                }
                            >
                                Print transmittal letter
                            </Button>
                        )}
                    </div>
                </div>
                <section className="space-y-3 rounded-lg border p-4">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        {[
                            [
                                'Reporting month',
                                document.period_month.slice(0, 7),
                            ],
                            [
                                'Revision / snapshot',
                                document.revision +
                                    ' / ' +
                                    document.snapshot.version,
                            ],
                            [
                                'Stock master classification',
                                document.classification_name,
                            ],
                            [
                                'Zero balances',
                                document.include_zero ? 'Included' : 'Excluded',
                            ],
                            ['Reporting fund label', document.fund_cluster],
                            [
                                'Prepared snapshot',
                                manilaTime(document.snapshot.created_at),
                            ],
                        ].map(([key, value]) => (
                            <div key={key}>
                                <dt className="text-muted-foreground">{key}</dt>
                                <dd>{value}</dd>
                            </div>
                        ))}
                    </dl>
                    <p className="text-sm">
                        {document.reconciliation_reference}
                    </p>
                    {document.correction_reason && (
                        <p className="text-sm">
                            Correction: {document.correction_reason}
                        </p>
                    )}
                    {document.supersedes && (
                        <Link
                            href={show(document.supersedes.id)}
                            className="text-sm underline"
                        >
                            Earlier approved package:{' '}
                            {document.supersedes.document_no}
                        </Link>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Source fingerprint:{' '}
                        <span className="font-mono break-all">
                            {document.snapshot.source_hash}
                        </span>
                    </p>
                </section>
                {isStale && (
                    <p className="rounded-lg border border-amber-500 p-4 text-sm">
                        Source records changed after this snapshot. The preparer
                        must refresh it and repeat sign-off before approval.
                    </p>
                )}
                <Readiness
                    data={currentReadiness ?? document.snapshot.readiness}
                />
                <section className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
                    <h2 className="font-semibold sm:col-span-2">
                        Assigned signatories
                    </h2>
                    {Object.entries(document.signatories).map(
                        ([key, person]) => (
                            <div key={key} className="text-sm">
                                <p className="text-muted-foreground">
                                    {person.role}
                                </p>
                                <p>{person.name}</p>
                            </div>
                        ),
                    )}
                </section>
                <section className="space-y-4">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div className="w-full space-y-2 sm:max-w-xl">
                            <Label htmlFor="monthly-report-choice">
                                Saved report preview
                            </Label>
                            <FormSelect
                                id="monthly-report-choice"
                                value={reportKey}
                                onChange={(e) => setReportKey(e.target.value)}
                            >
                                {Object.entries(
                                    document.snapshot.payload.reports,
                                ).map(([key, report]) => (
                                    <option value={key} key={key}>
                                        {report.title} ({report.rows.length})
                                    </option>
                                ))}
                            </FormSelect>
                        </div>
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <a
                                    href={printMonthly.url({
                                        report: document.id,
                                        document: reportKey,
                                    })}
                                    target="_blank"
                                    rel="noreferrer"
                                />
                            }
                        >
                            Print selected report
                        </Button>
                    </div>
                    <div className="space-y-3 md:hidden">
                        {table.rows.map((row, i) => (
                            <article
                                key={i}
                                className="space-y-1 rounded-lg border p-3"
                            >
                                {table.columns.map((column, j) => (
                                    <p className="text-sm" key={j}>
                                        <span className="text-muted-foreground">
                                            {column}:{' '}
                                        </span>
                                        {row[j] ?? '—'}
                                    </p>
                                ))}
                            </article>
                        ))}
                    </div>
                    <div className="hidden md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {table.columns.map((column, i) => (
                                        <TableHead key={i}>{column}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {table.rows.map((row, i) => (
                                    <TableRow key={i}>
                                        {row.map((cell, j) => (
                                            <TableCell key={j}>
                                                {cell ?? '—'}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    {table.rows.length === 0 && (
                        <p className="rounded-lg border p-8 text-center text-muted-foreground">
                            No approved source transactions belong to this
                            report and period.
                        </p>
                    )}
                </section>
                <div className="flex flex-wrap gap-2">
                    {allowedActions.map((value) => (
                        <Button
                            key={value}
                            variant={
                                value === 'cancel' || value === 'refresh'
                                    ? 'outline'
                                    : 'default'
                            }
                            disabled={
                                ['check', 'note', 'approve'].includes(value) &&
                                (isStale ||
                                    (currentReadiness?.blockers.length ?? 0) >
                                        0)
                            }
                            onClick={() => setAction(value)}
                        >
                            {titles[value]}
                        </Button>
                    ))}
                    {finalized && (
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <Link
                                    href={create({
                                        query: {
                                            period_month:
                                                document.period_month.slice(
                                                    0,
                                                    7,
                                                ),
                                            fund_cluster: document.fund_cluster,
                                            series_category_id:
                                                document.series_category_id ??
                                                undefined,
                                            include_zero: document.include_zero
                                                ? 1
                                                : 0,
                                        },
                                    })}
                                />
                            }
                        >
                            Prepare correction revision
                        </Button>
                    )}
                </div>
                <section className="space-y-3">
                    <h2 className="font-semibold">
                        Preparation, sign-off, and transmittal history
                    </h2>
                    {document.actions.map((entry) => (
                        <article
                            key={entry.id}
                            className="space-y-1 rounded-lg border p-3 text-sm"
                        >
                            <p className="font-medium">
                                {label(entry.action)} — {entry.actor_name}
                            </p>
                            <p className="text-muted-foreground">
                                {manilaTime(entry.created_at)} / Snapshot record{' '}
                                {entry.inventory_monthly_report_snapshot_id}
                            </p>
                            <p>{entry.remarks}</p>
                            {Object.entries(entry.metadata ?? {})
                                .filter(
                                    ([, value]) => typeof value === 'string',
                                )
                                .map(([key, value]) => (
                                    <p key={key}>
                                        {label(key)}: {String(value)}
                                    </p>
                                ))}
                        </article>
                    ))}
                </section>
                {action && (
                    <MonthlyDecision
                        key={
                            document.id +
                            '-' +
                            document.snapshot.id +
                            '-' +
                            action
                        }
                        packet={document}
                        action={action}
                        close={() => setAction(null)}
                    />
                )}
            </div>
        </>
    );
}
MonthlyShow.layout = {
    breadcrumbs: [{ title: 'Monthly Reports', href: index.url() }],
};
