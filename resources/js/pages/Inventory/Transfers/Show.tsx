import { Form, Head, Link } from '@inertiajs/react';
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
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    const [action, setAction] = useState(allowedActions[0] ?? '');
    const external = ['agency_transfer', 'donation'].includes(
        transfer.transfer_type,
    );
    const selectedAction = allowedActions.includes(action)
        ? action
        : (allowedActions[0] ?? '');

    return (
        <>
            <Head title={transfer.document_no} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <Link href={index()} className="text-sm underline">
                            Property transfers
                        </Link>
                        <h1 className="mt-2 text-2xl font-semibold">
                            {transfer.document_no}
                        </h1>
                        <p>
                            {label(transfer.status)} ·{' '}
                            {label(transfer.transfer_type)}
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        render={
                            <a
                                href={printTransfer.url(transfer.id)}
                                target="_blank"
                                rel="noreferrer"
                            />
                        }
                    >
                        Print PTR
                    </Button>
                </div>
                <dl className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
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
                            Previous PAR / ICS
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
                {allowedActions.length > 0 && (
                    <Form
                        key={transfer.status}
                        action={transition(transfer.id)}
                        className="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
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
                                            setAction(event.target.value)
                                        }
                                    >
                                        {allowedActions.map((value) => (
                                            <option value={value} key={value}>
                                                {label(value)}
                                            </option>
                                        ))}
                                    </FormSelect>
                                    <InputError message={errors.action} />
                                </div>
                                <p className="self-center text-sm text-muted-foreground">
                                    Verification and approval require
                                    independent IMS managers. A returned or
                                    changed property requires cancellation and a
                                    corrected request.
                                </p>
                                {external && selectedAction === 'approve' && (
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
                                                <Label htmlFor={name}>
                                                    {title}
                                                </Label>
                                                <Input
                                                    id={name}
                                                    name={name}
                                                    maxLength={120}
                                                    required
                                                />
                                                <InputError
                                                    message={errors[name]}
                                                />
                                            </div>
                                        ))}
                                    </>
                                )}
                                {external && selectedAction === 'complete' && (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="accounting_reference">
                                                Accounting Unit JEV / posting
                                                confirmation reference
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
                                                Confirm permanent ownership
                                                transfer and receiving agency
                                                acceptance
                                            </Label>
                                            <InputError
                                                message={
                                                    errors.ownership_transfer_confirmed
                                                }
                                            />
                                            <p className="text-sm text-muted-foreground">
                                                PPE leaves the recognized
                                                register after completion.
                                                Already-expensed semi-expendable
                                                property is removed from local
                                                custody without another expense.
                                                Accounting entries remain the
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
                                            Inspection evidence reference
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
                                                defaultValue={today}
                                                required
                                            />
                                            <InputError
                                                message={errors.handover_date}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="handover-condition">
                                                Joint inspection condition
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
                                                Outgoing officer signed release
                                                reference
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
                                                required
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
                                            selectedAction === 'complete'
                                                ? 'Confirm joint inspection, release, and receipt by the named officers.'
                                                : 'Record your decision and findings.'
                                        }
                                    />
                                    <InputError message={errors.attestation} />
                                </div>
                                <InputError message={errors.transfer} />
                                <Button
                                    className="justify-self-start"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Recording…'
                                        : label(selectedAction)}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Decision</TableHead>
                                <TableHead>IMS officer</TableHead>
                                <TableHead>Attestation</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfer.actions.map((entry) => (
                                <TableRow key={entry.id}>
                                    <TableCell>
                                        {entry.created_at.slice(0, 10)}
                                    </TableCell>
                                    <TableCell>{label(entry.action)}</TableCell>
                                    <TableCell>{entry.actor_name}</TableCell>
                                    <TableCell>{entry.attestation}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}
