import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Printer } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { RecordChangeBadge } from '@/components/record-change-badge';
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
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    CardDescription,
} from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkflowStatus } from '@/components/workflow-status';
import {
    index,
    print as printTransfer,
    transition,
} from '@/routes/inventory/transfers';
import { label } from './types';
import type { Transfer } from './types';

export default function TransferShow({
    transfer,
    allowedActions,
    today,
}: {
    transfer: Transfer;
    allowedActions: string[];
    today: string;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Property transfers', href: index() },
            { title: transfer.document_no },
        ],
    });

    const decisionActions = allowedActions.filter(
        (action) => action !== 'acknowledge_receipt',
    );
    const receipt = transfer.actions.find(
        (action) => action.action === 'acknowledge_receipt',
    );
    const [action, setAction] = useState(decisionActions[0] ?? '');
    const external = ['agency_transfer', 'donation'].includes(
        transfer.transfer_type,
    );
    const selectedAction = decisionActions.includes(action)
        ? action
        : (decisionActions[0] ?? '');

    return (
        <>
            <Head title={transfer.document_no} />
            <PageContainer>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Property transfers
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold sm:text-3xl">
                            {transfer.document_no}
                            <RecordChangeBadge
                                collection="transfer"
                                record={transfer}
                            />
                        </h1>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {label(transfer.status)}
                            </Badge>
                            <span className="text-sm text-muted-foreground">
                                {label(transfer.transfer_type)}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            nativeButton={false}
                            variant="outline"
                            className="flex-1 sm:flex-none"
                            render={<Link href={index()} />}
                        >
                            <ArrowLeft />
                            Back
                        </Button>
                        <Button
                            nativeButton={false}
                            variant="outline"
                            className="flex-1 sm:flex-none"
                            render={
                                <a
                                    href={printTransfer.url(transfer.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                />
                            }
                        >
                            <Printer />
                            Print PTR
                        </Button>
                    </div>
                </div>
                <WorkflowStatus
                    steps={['submitted', 'verified', 'approved', 'completed']}
                    current={transfer.status}
                    terminal={['rejected', 'cancelled']}
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Transfer details</CardTitle>
                        <CardDescription>
                            Property and accountable officers recorded for this
                            PTR.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3 [&_dd]:mt-1 [&_dd]:font-medium [&_dd]:break-words">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Property
                                </dt>
                                <dd>
                                    {transfer.source_snapshot.property_number} ·{' '}
                                    {transfer.source_snapshot.name}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Previous accountability document
                                </dt>
                                <dd>{transfer.source_snapshot.document_no}</dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    From accountable officer
                                </dt>
                                <dd>
                                    {transfer.source_snapshot.recipient_name} ·{' '}
                                    {transfer.source_snapshot.recipient_office}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    To accountable officer
                                </dt>
                                <dd>
                                    {transfer.recipient_snapshot.name} ·{' '}
                                    {transfer.recipient_snapshot.office}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Destination / desired date
                                </dt>
                                <dd>
                                    {transfer.destination_location} ·{' '}
                                    {transfer.desired_date.slice(0, 10)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Reason
                                </dt>
                                <dd>{transfer.reason}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
                {receipt && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="size-5 text-primary" />
                                Receipt acknowledged
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            <p className="font-medium">
                                Receipt acknowledged by {receipt.actor_name}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Handover: {receipt.evidence.handover_date} ·
                                Recorded:{' '}
                                {new Date(receipt.created_at).toLocaleString()}
                            </p>
                            <p className="mt-2 text-sm">
                                {receipt.attestation}
                            </p>
                        </CardContent>
                    </Card>
                )}
                {allowedActions.includes('acknowledge_receipt') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Acknowledge receipt</CardTitle>
                            <CardDescription>
                                Confirm receipt of the property described in
                                this approved PTR. The property officer will use
                                your acknowledgment to complete the transfer.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={transition(transfer.id)}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="acknowledge_receipt"
                                        />
                                        <input
                                            type="hidden"
                                            name="attestation"
                                            value="I acknowledge receipt of the listed property and accept responsibility for its official use and safekeeping."
                                        />
                                        <div className="grid gap-2">
                                            <Label htmlFor="receipt-handover-date">
                                                Date received
                                            </Label>
                                            <DatePicker
                                                id="receipt-handover-date"
                                                name="handover_date"
                                                defaultValue={today}
                                                required
                                            />
                                            <InputError
                                                message={errors.handover_date}
                                            />
                                        </div>
                                        <label className="flex min-h-11 items-start gap-3 rounded-lg border p-3 text-sm md:col-span-2">
                                            <input
                                                type="checkbox"
                                                name="attested"
                                                value="1"
                                                required
                                                className="mt-0.5 size-5 shrink-0"
                                            />
                                            I confirm that I received this
                                            property and accept accountability.
                                        </label>
                                        <div className="md:col-span-2">
                                            <InputError
                                                message={
                                                    errors.attested ??
                                                    errors.attestation ??
                                                    errors.action ??
                                                    errors.transfer
                                                }
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            className="w-full sm:w-auto sm:justify-self-start md:col-span-2"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Acknowledging…'
                                                : 'Acknowledge receipt'}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}
                {decisionActions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Record decision</CardTitle>
                            <CardDescription>
                                Choose an available action and provide its
                                supporting evidence.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                key={transfer.status}
                                action={transition(transfer.id)}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="transfer-action">
                                                Decision
                                            </Label>
                                            <FormSelect
                                                id="transfer-action"
                                                name="action"
                                                value={selectedAction}
                                                onChange={(event) =>
                                                    setAction(
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {decisionActions.map(
                                                    (value) => (
                                                        <option
                                                            value={value}
                                                            key={value}
                                                        >
                                                            {label(value)}
                                                        </option>
                                                    ),
                                                )}
                                            </FormSelect>
                                            <InputError
                                                message={errors.action}
                                            />
                                        </div>
                                        <p className="self-center text-sm text-muted-foreground">
                                            Verification and approval require
                                            independent IMS managers. A returned
                                            or changed property requires
                                            cancellation and a corrected
                                            request.
                                        </p>
                                        {external &&
                                            selectedAction === 'approve' && (
                                                <>
                                                    {[
                                                        [
                                                            'authority_reference',
                                                            'Transfer / donation authority reference',
                                                        ],
                                                        [
                                                            'accounting_clearance_reference',
                                                            'Accounting Unit clearance reference',
                                                        ],
                                                    ].map(([name, title]) => (
                                                        <div
                                                            className="grid gap-2"
                                                            key={name}
                                                        >
                                                            <Label
                                                                htmlFor={name}
                                                            >
                                                                {title}
                                                            </Label>
                                                            <Input
                                                                id={name}
                                                                name={name}
                                                                maxLength={120}
                                                                required
                                                            />
                                                            <InputError
                                                                message={
                                                                    errors[name]
                                                                }
                                                            />
                                                        </div>
                                                    ))}
                                                </>
                                            )}
                                        {external &&
                                            selectedAction === 'complete' && (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="accounting_reference">
                                                            Accounting Unit JEV
                                                            / posting
                                                            confirmation
                                                            reference
                                                        </Label>
                                                        <Input
                                                            id="accounting_reference"
                                                            name="accounting_reference"
                                                            maxLength={120}
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.accounting_reference
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="ownership-confirmed">
                                                            <input
                                                                id="ownership-confirmed"
                                                                type="checkbox"
                                                                name="ownership_transfer_confirmed"
                                                                required
                                                                className="mr-2"
                                                            />
                                                            Confirm permanent
                                                            ownership transfer
                                                            and receiving agency
                                                            acceptance
                                                        </Label>
                                                        <InputError
                                                            message={
                                                                errors.ownership_transfer_confirmed
                                                            }
                                                        />
                                                        <p className="text-sm text-muted-foreground">
                                                            PPE leaves the
                                                            recognized register
                                                            after completion.
                                                            Already-expensed
                                                            semi-expendable
                                                            property is removed
                                                            from local custody
                                                            without another
                                                            expense. Accounting
                                                            entries remain the
                                                            Accounting Unit’s
                                                            responsibility.
                                                        </p>
                                                    </div>
                                                </>
                                            )}
                                        {['verify', 'complete'].includes(
                                            selectedAction,
                                        ) && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="inspection-reference">
                                                    Inspection evidence
                                                    reference
                                                </Label>
                                                <Input
                                                    id="inspection-reference"
                                                    name="inspection_reference"
                                                    required
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={
                                                        errors.inspection_reference
                                                    }
                                                />
                                            </div>
                                        )}
                                        {selectedAction === 'complete' && (
                                            <>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="handover-date">
                                                        Physical handover date
                                                    </Label>
                                                    <DatePicker
                                                        id="handover-date"
                                                        name="handover_date"
                                                        max={today}
                                                        defaultValue={
                                                            receipt?.evidence
                                                                .handover_date ??
                                                            today
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.handover_date
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="handover-condition">
                                                        Joint inspection
                                                        condition
                                                    </Label>
                                                    <FormSelect
                                                        id="handover-condition"
                                                        name="condition_status"
                                                        defaultValue="good"
                                                    >
                                                        {[
                                                            'good',
                                                            'fair',
                                                            'needs_repair',
                                                            'defective',
                                                            'non_usable',
                                                            'unknown',
                                                        ].map((value) => (
                                                            <option
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {label(value)}
                                                            </option>
                                                        ))}
                                                    </FormSelect>
                                                    <InputError
                                                        message={
                                                            errors.condition_status
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="release-reference">
                                                        Outgoing officer signed
                                                        release reference
                                                    </Label>
                                                    <Input
                                                        id="release-reference"
                                                        name="release_reference"
                                                        maxLength={120}
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.release_reference
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="receipt-reference">
                                                        Receiving officer signed
                                                        acceptance reference
                                                    </Label>
                                                    <Input
                                                        id="receipt-reference"
                                                        name="receipt_reference"
                                                        maxLength={120}
                                                        required={!receipt}
                                                        readOnly={Boolean(
                                                            receipt,
                                                        )}
                                                        value={
                                                            receipt
                                                                ? `${transfer.document_no}-RECEIPT-${receipt.id}`
                                                                : undefined
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.receipt_reference
                                                        }
                                                    />
                                                </div>
                                            </>
                                        )}
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label htmlFor="transfer-attestation">
                                                Attestation / findings
                                            </Label>
                                            <Input
                                                id="transfer-attestation"
                                                name="attestation"
                                                maxLength={2000}
                                                required
                                                placeholder={
                                                    selectedAction ===
                                                    'complete'
                                                        ? 'Confirm joint inspection, release, and receipt by the named officers.'
                                                        : 'Record your decision and findings.'
                                                }
                                            />
                                            <InputError
                                                message={errors.attestation}
                                            />
                                        </div>
                                        <InputError message={errors.transfer} />
                                        <Button
                                            type="submit"
                                            className="w-full sm:w-auto sm:justify-self-start md:col-span-2"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Recording…'
                                                : label(selectedAction)}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Decision history</CardTitle>
                        <CardDescription>
                            Recorded actions, receipt acknowledgments, and
                            attestations.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Actor</TableHead>
                                    <TableHead>Attestation</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transfer.actions.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell>
                                            {entry.created_at.slice(0, 10)}
                                        </TableCell>
                                        <TableCell>
                                            {label(entry.action)}
                                        </TableCell>
                                        <TableCell>
                                            {entry.actor_name}
                                        </TableCell>
                                        <TableCell className="min-w-56 whitespace-normal">
                                            {entry.attestation}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </PageContainer>
        </>
    );
}
