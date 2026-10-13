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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { transition } from '@/routes/inventory/deliveries';
import type { HrisReference } from '@/types/inventory';
import type { Delivery } from './types';
export default function SupplierReturnAuthorization({
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
                    <DialogTitle>
                        Record signed supplier-return notice
                    </DialogTitle>
                    <DialogDescription>
                        {document.supplier_return_no}. Record the four
                        signatories and signed references before physical
                        handover. Conforming stock and PO receipt quantities
                        stay unchanged.
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
                                value="authorize_supplier_return"
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="return-notice-date">
                                    Signed notice date
                                </Label>
                                <DatePicker
                                    id="return-notice-date"
                                    name="return_notice_date"
                                    max={today}
                                    defaultValue={today}
                                    required
                                />
                            </div>
                            {[
                                ['return_purpose', 'Procurement purpose'],
                                ['return_reason', 'Reason for return'],
                                [
                                    'return_action_required',
                                    'Action required from supplier',
                                ],
                            ].map(([name, title]) => (
                                <div key={name} className="grid gap-2">
                                    <Label htmlFor={name}>{title}</Label>
                                    <Input
                                        id={name}
                                        name={name}
                                        maxLength={
                                            name === 'return_purpose'
                                                ? 1000
                                                : 3000
                                        }
                                        required
                                    />
                                </div>
                            ))}
                            {[
                                [
                                    'return_preparer',
                                    'Prepared by / Sr. Supply Officer',
                                ],
                                ['return_end_user', 'Conformed by / End user'],
                                [
                                    'return_procurement_head',
                                    'Conformed by / Procurement Head',
                                ],
                                [
                                    'return_chair',
                                    'Approved by / Inspectorate Team Chairperson',
                                ],
                            ].map(([role, title]) => (
                                <fieldset
                                    key={role}
                                    className="grid gap-3 rounded-lg border p-3"
                                >
                                    <legend>{title}</legend>
                                    <div className="grid gap-2">
                                        <Label htmlFor={role + '-person'}>
                                            Signatory
                                        </Label>
                                        <HrisReferenceCombobox
                                            id={role + '-person'}
                                            name={role + '_reference_id'}
                                            references={employees}
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={role + '-signature'}>
                                            Signed evidence reference
                                        </Label>
                                        <Input
                                            id={role + '-signature'}
                                            name={role + '_signature_reference'}
                                            required
                                            maxLength={120}
                                        />
                                    </div>
                                </fieldset>
                            ))}
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="return-notice-remarks">
                                    Signed notice / approval findings
                                </Label>
                                <Input
                                    id="return-notice-remarks"
                                    name="remarks"
                                    maxLength={3000}
                                    required
                                />
                            </div>
                            <Label
                                htmlFor="return-notice-attested"
                                className="sm:col-span-2"
                            >
                                <input
                                    id="return-notice-attested"
                                    name="attested"
                                    type="checkbox"
                                    value="1"
                                    required
                                    className="mr-2"
                                />
                                Confirm all four signatures and the recorded
                                non-conformities.
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
                                        : 'Record signed authorization'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
