import { useState } from 'react';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormSelect } from '@/components/ui/form-select';
import { Label } from '@/components/ui/label';
import { print as printSlip } from '@/routes/inventory/issuance_slips';
import { print as printRequest } from '@/routes/inventory/requests';
import type { HrisReference } from '@/types/inventory';

export type DocumentEmployee = HrisReference & { position_name: string | null };

export function RequestDocuments({
    requestId,
    status,
    isSuperAdmin,
    slips,
    employees,
    onLiquidation,
    onReturn,
}: {
    requestId: number;
    status: string;
    isSuperAdmin: boolean;
    slips: { id: number; slip_no: string; issued_at: string }[];
    employees: DocumentEmployee[];
    onLiquidation: () => void;
    onReturn: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [slipId, setSlipId] = useState(String(slips[0]?.id ?? ''));
    const [signatories, setSignatories] = useState({
        prepared_by: '',
        checked_by: '',
        approved_by: '',
    });
    const released = status === 'released';
    const slip = slips.find((entry) => String(entry.id) === slipId);
    const previewUrl = slip
        ? printSlip.url(slip.id, { query: signatories })
        : null;

    return (
        <>
            <div
                className="flex flex-wrap gap-2"
                aria-label="Request documents"
            >
                {released && (
                    <>
                        <Button variant="outline" onClick={onLiquidation}>
                            RIS Liquidation
                        </Button>
                        <Button variant="outline" onClick={onReturn}>
                            Return Slip
                        </Button>
                    </>
                )}
                <a
                    className={buttonVariants({ variant: 'outline' })}
                    href={printRequest.url(requestId)}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Issuance Request
                </a>
                {released && isSuperAdmin && (
                    <Button variant="outline" onClick={() => setOpen(true)}>
                        Issuance Slip
                    </Button>
                )}
            </div>
            {open && released && isSuperAdmin && (
                <Dialog open onOpenChange={setOpen}>
                    <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-6xl">
                        <DialogHeader>
                            <DialogTitle>Issuance Slip</DialogTitle>
                            <DialogDescription>
                                Preview the release and choose its signatories
                                before printing.
                            </DialogDescription>
                        </DialogHeader>
                        {slips.length > 1 && (
                            <div className="grid gap-2">
                                <Label htmlFor="issuance-slip-release">
                                    Release
                                </Label>
                                <FormSelect
                                    id="issuance-slip-release"
                                    value={slipId}
                                    onChange={(event) =>
                                        setSlipId(event.target.value)
                                    }
                                >
                                    {slips.map((entry) => (
                                        <option
                                            key={entry.id}
                                            value={String(entry.id)}
                                        >
                                            {entry.slip_no} (
                                            {entry.issued_at.slice(0, 10)})
                                        </option>
                                    ))}
                                </FormSelect>
                            </div>
                        )}
                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    'prepared_by',
                                    'checked_by',
                                    'approved_by',
                                ] as const
                            ).map((role) => {
                                const employee = employees.find(
                                    (entry) =>
                                        String(entry.id) === signatories[role],
                                );
                                const label = {
                                    prepared_by: 'Prepared By',
                                    checked_by: 'Checked By',
                                    approved_by: 'Approved By',
                                }[role];

                                return (
                                    <div
                                        key={role}
                                        className="grid content-start gap-2"
                                    >
                                        <Label htmlFor={`slip-${role}`}>
                                            {label}
                                        </Label>
                                        <HrisReferenceCombobox
                                            id={`slip-${role}`}
                                            name={role}
                                            references={employees}
                                            value={signatories[role]}
                                            onValueChange={(value) =>
                                                setSignatories((previous) => ({
                                                    ...previous,
                                                    [role]: value,
                                                }))
                                            }
                                        />
                                        {employee && (
                                            <div className="text-center text-sm">
                                                <span className="underline underline-offset-4">
                                                    {employee.name}
                                                </span>
                                                <p className="mt-1 text-muted-foreground">
                                                    {employee.position_name ??
                                                        'Position unavailable'}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                        {previewUrl ? (
                            <>
                                <iframe
                                    title="Issuance slip preview"
                                    src={previewUrl}
                                    className="h-[60dvh] w-full rounded-lg border bg-white"
                                />
                                <div className="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => setOpen(false)}
                                    >
                                        Close
                                    </Button>
                                    <a
                                        className={buttonVariants()}
                                        href={previewUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Print / Save PDF
                                    </a>
                                </div>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No issuance slip was recorded for this release.
                            </p>
                        )}
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
