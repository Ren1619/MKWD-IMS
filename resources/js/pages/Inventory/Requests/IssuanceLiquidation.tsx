import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
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
    allocations as previewAllocations,
    print as printLiquidation,
    store,
    transition,
} from '@/routes/inventory/department_liquidations';

type Material = {
    inventory_item_id: number;
    supply_request_line_id?: number;
    item_description: string;
    unit_of_measure: string;
    quantity_issued: number;
    previously_utilized: number;
    quantity_utilized?: number;
    balance?: number;
    usage_details?: string | null;
};
export type IssuanceAllocation = {
    supply_request_id: number;
    isf_no: string;
    department: string;
    charge_code: string | null;
    requested_by_name: string;
    return_slip_no?: string | null;
    return_slip_date?: string | null;
    lines: Material[];
};
export type LiquidationDocument = {
    id: number;
    supply_request_id: number | null;
    isf_no: string | null;
    return_slip_no: string | null;
    return_slip_date: string | null;
    document_no: string;
    department: string;
    liquidation_date: string;
    charge_code: string | null;
    requested_by_name: string;
    status: string;
    available_action: 'check' | 'approve' | null;
    lines: Material[];
    history: {
        action: string;
        actor_name: string;
        at: string;
        remarks: string | null;
    }[];
};

export function IssuanceLiquidationDialog({
    open,
    onOpenChange,
    allocations: allAllocations,
    documents: allDocuments,
    canManage,
    supplyRequestId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    allocations: IssuanceAllocation[];
    documents: LiquidationDocument[];
    canManage: boolean;
    supplyRequestId?: number;
}) {
    const [requestId, setRequestId] = useState('');
    const [selected, setSelected] = useState<LiquidationDocument | null>(null);
    const allocations = allAllocations.filter(
        (entry) =>
            supplyRequestId === undefined ||
            entry.supply_request_id === supplyRequestId,
    );
    const documents = allDocuments.filter(
        (entry) =>
            supplyRequestId === undefined ||
            entry.supply_request_id === supplyRequestId,
    );
    const allocation = allocations.find(
        (entry) =>
            String(entry.supply_request_id) === requestId ||
            (supplyRequestId !== undefined && documents.length === 0),
    );
    const close = () => {
        setRequestId('');
        setSelected(null);
        onOpenChange(false);
    };

    if (!open) {
        return null;
    }

    if (selected || allocation) {
        return (
            <DepartmentForm
                key={selected?.id ?? allocation?.supply_request_id}
                allocation={allocation}
                document={selected}
                canManage={canManage}
                onClose={close}
            />
        );
    }

    return (
        <Dialog open onOpenChange={(value) => !value && close()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>RIS liquidation</DialogTitle>
                    <DialogDescription>
                        Select one Issuance Request Form to account for its
                        released materials, utilization, and balance.
                    </DialogDescription>
                </DialogHeader>
                {canManage &&
                    supplyRequestId !== undefined &&
                    allocations.length > 0 && (
                        <Button
                            disabled={documents.some(
                                (entry) => entry.status !== 'approved',
                            )}
                            onClick={() =>
                                setRequestId(String(supplyRequestId))
                            }
                        >
                            New RIS liquidation
                        </Button>
                    )}
                {canManage && supplyRequestId === undefined && (
                    <div className="grid gap-2">
                        <Label htmlFor="liquidation-isf">
                            Issuance Request Form
                        </Label>
                        <FormSelect
                            id="liquidation-isf"
                            value={requestId}
                            onChange={(event) =>
                                setRequestId(event.target.value)
                            }
                        >
                            <option value="">Select an ISF</option>
                            {allocations.map((entry) => (
                                <option
                                    key={entry.supply_request_id}
                                    value={String(entry.supply_request_id)}
                                >
                                    {entry.isf_no} · {entry.department}
                                </option>
                            ))}
                        </FormSelect>
                    </div>
                )}
                {allocations.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No ISFs with released materials are available for
                        liquidation.
                    </p>
                )}
                {documents.length > 0 && (
                    <section className="grid gap-2">
                        <h3 className="font-semibold">
                            IS Liquidation history
                        </h3>
                        {documents.map((entry) => (
                            <Button
                                key={entry.id}
                                variant="outline"
                                className="h-auto justify-start py-3 text-left whitespace-normal"
                                onClick={() => setSelected(entry)}
                            >
                                {entry.document_no} ·{' '}
                                {entry.isf_no ?? 'Legacy department record'} ·{' '}
                                {entry.department} ·{' '}
                                {entry.liquidation_date.slice(0, 10)} ·{' '}
                                {entry.status}
                            </Button>
                        ))}
                    </section>
                )}
            </DialogContent>
        </Dialog>
    );
}

function DepartmentForm({
    allocation,
    document,
    canManage,
    onClose,
}: {
    allocation?: IssuanceAllocation;
    document: LiquidationDocument | null;
    canManage: boolean;
    onClose: () => void;
}) {
    const [materials, setMaterials] = useState(
        document?.lines ?? allocation?.lines ?? [],
    );
    const [loadingMaterials, setLoadingMaterials] = useState(false);
    const [previewError, setPreviewError] = useState('');
    const previewRequest = useRef(0);
    const editable = !document && canManage;
    const action = document?.available_action;
    const form = useForm({
        supply_request_id:
            document?.supply_request_id ?? allocation?.supply_request_id ?? 0,
        department: document?.department ?? allocation?.department ?? '',
        liquidation_date:
            document?.liquidation_date.slice(0, 10) ??
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date()),
        charge_code: document?.charge_code ?? allocation?.charge_code ?? '',
        return_slip_no:
            document?.return_slip_no ?? allocation?.return_slip_no ?? '',
        return_slip_date:
            document?.return_slip_date?.slice(0, 10) ??
            allocation?.return_slip_date?.slice(0, 10) ??
            '',
        remarks: '',
        attested: false,
        action: action ?? '',
        lines: materials.map((line) => ({
            supply_request_line_id: line.supply_request_line_id ?? 0,
            quantity_utilized: String(
                line.quantity_utilized ?? line.previously_utilized,
            ),
            usage_details: line.usage_details ?? '',
        })),
    });

    async function updateDate(date: string) {
        form.setData('liquidation_date', date);
        const requestId = ++previewRequest.current;
        setLoadingMaterials(true);
        setPreviewError('');

        try {
            const response = await fetch(
                previewAllocations.url({
                    query: {
                        supply_request_id: form.data.supply_request_id,
                        date,
                    },
                }),
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                throw new Error(
                    'Unable to load ISF materials for this date. Check the date and try again.',
                );
            }

            const data: { lines: Material[] } = await response.json();

            if (requestId === previewRequest.current) {
                setMaterials(data.lines);
                form.setData(
                    'lines',
                    data.lines.map((line) => ({
                        supply_request_line_id:
                            line.supply_request_line_id ?? 0,
                        quantity_utilized: String(line.previously_utilized),
                        usage_details: '',
                    })),
                );
            }
        } catch {
            if (requestId === previewRequest.current) {
                setPreviewError(
                    'Unable to load ISF materials for this date. Check the date and try again.',
                );
            }
        } finally {
            if (requestId === previewRequest.current) {
                setLoadingMaterials(false);
            }
        }
    }

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        RIS liquidation
                        {document ? ` — ${document.document_no}` : ''}
                    </DialogTitle>
                    <DialogDescription>
                        Only released materials from the selected ISF are
                        included. Balance equals issued minus utilized. Returns
                        are referenced separately.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const options = {
                            preserveScroll: true,
                            onSuccess: onClose,
                        };

                        if (document) {
                            form.patch(transition.url(document.id), options);
                        } else {
                            form.post(store.url(), options);
                        }
                    }}
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor="liquidation-isf-number">
                                Issuance Request Form No.
                            </Label>
                            <Input
                                id="liquidation-isf-number"
                                readOnly
                                value={
                                    document?.isf_no ??
                                    allocation?.isf_no ??
                                    'Legacy department record'
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="department-name">Department</Label>
                            <Input
                                id="department-name"
                                readOnly
                                value={form.data.department}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="department-document">
                                IS Liquidation No.
                            </Label>
                            <Input
                                id="department-document"
                                readOnly
                                value={
                                    document?.document_no ??
                                    'Assigned on submission'
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="department-date">Date</Label>
                            <DatePicker
                                id="department-date"
                                required
                                disabled={!editable}
                                value={form.data.liquidation_date}
                                onChange={(value) => void updateDate(value)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="department-charge">
                                Charge code
                            </Label>
                            <Input
                                id="department-charge"
                                readOnly
                                value={form.data.charge_code}
                                onChange={(event) =>
                                    form.setData(
                                        'charge_code',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1">
                            <Label htmlFor="liquidation-return-number">
                                Return Slip No. (if applicable)
                            </Label>
                            <Input
                                id="liquidation-return-number"
                                disabled={!editable}
                                value={form.data.return_slip_no}
                                onChange={(event) =>
                                    form.setData(
                                        'return_slip_no',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="liquidation-return-date">
                                Return slip date
                            </Label>
                            <DatePicker
                                id="liquidation-return-date"
                                disabled={!editable}
                                value={form.data.return_slip_date}
                                onChange={(value) =>
                                    form.setData('return_slip_date', value)
                                }
                            />
                        </div>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Balance = issued − utilized. A return slip is a separate
                        supporting reference and does not change this
                        calculation.
                    </p>
                    {loadingMaterials && (
                        <p
                            role="status"
                            className="animate-pulse text-sm text-muted-foreground"
                        >
                            Loading ISF materials…
                        </p>
                    )}
                    <InputError message={previewError} />
                    {materials.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No materials were released against this ISF by the
                            selected date.
                        </p>
                    )}
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Material specifics</TableHead>
                                    <TableHead>Total issued</TableHead>
                                    <TableHead>Previously utilized</TableHead>
                                    <TableHead>Total utilized</TableHead>
                                    <TableHead>Balance</TableHead>
                                    <TableHead>Usage details</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {materials.map((line, index) => (
                                    <TableRow
                                        key={
                                            line.supply_request_line_id ??
                                            line.inventory_item_id
                                        }
                                    >
                                        <TableCell>
                                            {line.item_description} (
                                            {line.unit_of_measure})
                                        </TableCell>
                                        <TableCell>
                                            {line.quantity_issued}
                                        </TableCell>
                                        <TableCell>
                                            {line.previously_utilized}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                step={1}
                                                min={line.previously_utilized}
                                                max={line.quantity_issued}
                                                required
                                                disabled={!editable}
                                                aria-label={`Total utilized for ${line.item_description}`}
                                                value={
                                                    form.data.lines[index]
                                                        .quantity_utilized
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (entry, i) =>
                                                                i === index
                                                                    ? {
                                                                          ...entry,
                                                                          quantity_utilized:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : entry,
                                                        ),
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {line.quantity_issued -
                                                Number(
                                                    form.data.lines[index]
                                                        .quantity_utilized,
                                                )}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                aria-label={`Usage details for ${line.item_description}`}
                                                disabled={!editable}
                                                placeholder="Activity, project, or purpose"
                                                value={
                                                    form.data.lines[index]
                                                        .usage_details
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.map(
                                                            (entry, i) =>
                                                                i === index
                                                                    ? {
                                                                          ...entry,
                                                                          usage_details:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : entry,
                                                        ),
                                                    )
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    {document && (
                        <div className="grid gap-3 sm:grid-cols-3">
                            <p>Requested by: {document.requested_by_name}</p>
                            <p>
                                Checked by:{' '}
                                {document.history.find(
                                    (entry) => entry.action === 'check',
                                )?.actor_name ?? 'Pending'}
                            </p>
                            <p>
                                Approved by:{' '}
                                {document.history.find(
                                    (entry) => entry.action === 'approve',
                                )?.actor_name ?? 'Pending'}
                            </p>
                        </div>
                    )}
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                    {(editable || action) && (
                        <>
                            <Label htmlFor="department-remarks">Remarks</Label>
                            <Input
                                id="department-remarks"
                                value={form.data.remarks}
                                onChange={(event) =>
                                    form.setData('remarks', event.target.value)
                                }
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.attested}
                                    onChange={(event) =>
                                        form.setData(
                                            'attested',
                                            event.target.checked,
                                        )
                                    }
                                />
                                I confirm the department’s material utilization
                                and balances are accurate.
                            </label>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !form.data.attested ||
                                    loadingMaterials ||
                                    Boolean(previewError) ||
                                    materials.length === 0
                                }
                            >
                                {editable
                                    ? 'Submit IS liquidation'
                                    : action === 'check'
                                      ? 'Check liquidation'
                                      : 'Approve liquidation'}
                            </Button>
                        </>
                    )}
                    {document && (
                        <>
                            <a
                                className="text-sm underline"
                                href={printLiquidation.url(document.id)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Print IS Liquidation
                            </a>
                            {document.history.map((entry, index) => (
                                <p
                                    key={index}
                                    className="text-sm text-muted-foreground"
                                >
                                    {entry.action}: {entry.actor_name} ·{' '}
                                    {new Date(entry.at).toLocaleString()}
                                    {entry.remarks ? ` — ${entry.remarks}` : ''}
                                </p>
                            ))}
                        </>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}
