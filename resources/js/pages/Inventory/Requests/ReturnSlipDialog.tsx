import { Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { return_slip } from '@/routes/inventory/requests';
import { create as createStockReturn } from '@/routes/inventory/returns';

export function ReturnSlipDialog({
    request,
    onClose,
}: {
    request: {
        id: number;
        ris_no: string;
        isf_no: string | null;
        return_slip_no: string | null;
        return_slip_date: string | null;
    };
    onClose: () => void;
}) {
    const form = useForm({
        return_slip_no: request.return_slip_no ?? '',
        return_slip_date:
            request.return_slip_date?.slice(0, 10) ??
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        remarks: '',
        attested: false,
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Return slip — {request.isf_no ?? request.ris_no}
                    </DialogTitle>
                    <DialogDescription>
                        Record the supporting return slip reference for this
                        RIS. Recording a reference does not post a stock return
                        or change the liquidation balance.
                    </DialogDescription>
                </DialogHeader>
                <Link
                    href={createStockReturn({
                        query: { supply_request_id: request.id },
                    })}
                    className="text-sm font-medium underline underline-offset-4"
                >
                    Prepare an RMS to receive actual returned stock
                </Link>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(return_slip.url(request.id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="return-slip-no">Return Slip No.</Label>
                        <Input
                            id="return-slip-no"
                            required
                            maxLength={100}
                            value={form.data.return_slip_no}
                            onChange={(event) =>
                                form.setData(
                                    'return_slip_no',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="return-slip-date">
                            Return slip date
                        </Label>
                        <DatePicker
                            id="return-slip-date"
                            required
                            value={form.data.return_slip_date}
                            onChange={(value) =>
                                form.setData('return_slip_date', value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="return-slip-remarks">Remarks</Label>
                        <Input
                            id="return-slip-remarks"
                            value={form.data.remarks}
                            onChange={(event) =>
                                form.setData('remarks', event.target.value)
                            }
                        />
                    </div>
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(event) =>
                                form.setData('attested', event.target.checked)
                            }
                        />
                        I confirm this return slip reference is accurate.
                    </label>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.data.attested}
                    >
                        Save return slip reference
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
