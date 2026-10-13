import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import InputError from '@/components/input-error';
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
import { transition } from '@/routes/inventory/deliveries';
import type { HrisReference } from '@/types/inventory';
import type { Delivery } from './types';
import { localReceiptTime } from './types';

export default function PostponementDialog({
    document,
    employees,
    today,
    close,
}: {
    document: Delivery;
    employees: HrisReference[];
    today: string;
    close: () => void;
}) {
    const [key] = useState(() => crypto.randomUUID());
    const previous = document.actions
        .filter((a) => a.action === 'postpone')
        .at(-1)?.metadata;
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !processing) {
close();
}
            }}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Record inspection postponement</DialogTitle>
                    <DialogDescription>
                        {document.document_no}. Preserve the signed notice and
                        proposed schedule. Received quantities remain committed;
                        stock is unchanged.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={transition(document.id)}
                    onStart={() => setProcessing(true)}
                    onFinish={() => setProcessing(false)}
                    onSuccess={close}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ errors, processing: submitting }) => (
                        <>
                            <input
                                type="hidden"
                                name="action"
                                value="postpone"
                            />
                            <input
                                type="hidden"
                                name="notice_submission_key"
                                value={key}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="notice_date">Notice date</Label>
                                <DatePicker
                                    id="notice_date"
                                    name="notice_date"
                                    max={today}
                                    defaultValue={today}
                                    required
                                />
                                <InputError message={errors.notice_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="postponement_reason">
                                    Reason
                                </Label>
                                <FormSelect
                                    id="postponement_reason"
                                    name="postponement_reason"
                                    defaultValue="no_quorum"
                                >
                                    <option value="no_quorum">
                                        Inspectorate Team unavailable / no
                                        quorum
                                    </option>
                                    <option value="technical_representative_unavailable">
                                        Technical representative unavailable
                                    </option>
                                    <option value="safety_force_majeure">
                                        Force majeure / safety concerns
                                    </option>
                                    <option value="other">
                                        Other (explain below)
                                    </option>
                                </FormSelect>
                            </div>
                            {[
                                [
                                    'original_inspection_at',
                                    'Original scheduled date and time',
                                ],
                                [
                                    'proposed_inspection_at',
                                    'New proposed date and time',
                                ],
                                [
                                    'original_site',
                                    'Original venue / inspection site',
                                ],
                                [
                                    'proposed_site',
                                    'Proposed venue / inspection site',
                                ],
                                [
                                    'supplier_contact_name',
                                    'Supplier / contractor contact name',
                                ],
                                [
                                    'supplier_address',
                                    'Supplier / contractor address',
                                ],
                                ['lot_reference', 'Quantity / lot reference'],
                                [
                                    'chair_signature_reference',
                                    'Signed Inspectorate Chairperson reference',
                                ],
                                [
                                    'authority_signature_reference',
                                    'Signed approving authority / GM reference',
                                ],
                                [
                                    'notice_sent_reference',
                                    'Supplier notification / transmittal reference',
                                ],
                            ].map(([name, title]) => {
                                const value =
                                    name === 'original_inspection_at'
                                        ? typeof previous?.proposed_inspection_at ===
                                          'string'
                                            ? previous.proposed_inspection_at
                                            : localReceiptTime()
                                        : name === 'original_site' &&
                                            typeof previous?.proposed_site ===
                                                'string'
                                          ? previous.proposed_site
                                          : undefined;

                                return (
                                    <div className="grid gap-2" key={name}>
                                        <Label htmlFor={'postpone-' + name}>
                                            {title}
                                        </Label>
                                        {name.endsWith('_at') ? (
                                            <DatePicker
                                                includeTime
                                                id={'postpone-' + name}
                                                name={name}
                                                defaultValue={value}
                                                readOnly={
                                                    !!previous &&
                                                    [
                                                        'original_inspection_at',
                                                        'original_site',
                                                    ].includes(name)
                                                }
                                                required
                                            />
                                        ) : (
                                            <Input
                                                id={'postpone-' + name}
                                                name={name}
                                                type="text"
                                                defaultValue={value}
                                                readOnly={
                                                    !!previous &&
                                                    [
                                                        'original_inspection_at',
                                                        'original_site',
                                                    ].includes(name)
                                                }
                                                required
                                                maxLength={
                                                    name === 'supplier_address'
                                                        ? 1000
                                                        : name.endsWith(
                                                                '_reference',
                                                            )
                                                          ? 120
                                                          : 255
                                                }
                                            />
                                        )}
                                        <InputError message={errors[name]} />
                                    </div>
                                );
                            })}
                            {[
                                [
                                    'chair_reference_id',
                                    'Inspectorate Team Chairperson',
                                ],
                                [
                                    'authority_reference_id',
                                    'Noted by / approving authority / General Manager',
                                ],
                            ].map(([name, title]) => (
                                <div className="grid gap-2" key={name}>
                                    <Label htmlFor={name}>{title}</Label>
                                    <HrisReferenceCombobox
                                        id={name}
                                        name={name}
                                        references={employees}
                                        required
                                    />
                                    <InputError message={errors[name]} />
                                </div>
                            ))}
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="postpone-remarks">
                                    Reason details / findings
                                </Label>
                                <Input
                                    id="postpone-remarks"
                                    name="remarks"
                                    required
                                    maxLength={3000}
                                />
                            </div>
                            <Label
                                htmlFor="postpone-attested"
                                className="sm:col-span-2"
                            >
                                <input
                                    id="postpone-attested"
                                    name="attested"
                                    type="checkbox"
                                    value="1"
                                    required
                                    className="mr-2"
                                />
                                Confirm the signed notice, signatories and
                                supplier notification evidence.
                            </Label>
                            <div className="sm:col-span-2">
                                {Object.entries(errors).map(
                                    ([name, message]) => (
                                        <InputError
                                            key={name}
                                            message={message}
                                        />
                                    ),
                                )}
                            </div>
                            <div className="flex gap-2 sm:col-span-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={submitting}
                                    onClick={close}
                                >
                                    Close
                                </Button>
                                <Button disabled={submitting}>
                                    {submitting
                                        ? 'Recording…'
                                        : 'Record preserved notice'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
