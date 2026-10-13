import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    Calculator,
    EllipsisVertical,
    History,
    Plus,
    QrCode,
    RotateCcw,
    SlidersHorizontal,
    UserCheck,
    UserMinus,
} from 'lucide-react';
import { useState } from 'react';
import { ArchiveRecordDialog } from '@/components/archive-record-dialog';
import { AssetDetailsDialog } from '@/components/asset-details-dialog';
import { DataPagination } from '@/components/data-pagination';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
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
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppPage } from '@/hooks/use-app-page';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import { index as transfersIndex } from '@/routes/inventory/transfers';
import {
    assign,
    destroy,
    index,
    restore,
    property_tag,
    returnMethod,
    store,
    unassign,
    update_state,
    update_accounting,
} from '@/routes/inventory/assets';
import { store as storeAssetEvent } from '@/routes/inventory/assets/events';
import {
    index as semiExpendableIndex,
    store as storeSemiExpendable,
} from '@/routes/inventory/semi_expendable';
import { store as storeSemiExpendableEvent } from '@/routes/inventory/semi_expendable/events';
import type {
    AssetCategory,
    AssetStateOption,
    AssetStateOptions,
    HrisReference,
    InventoryAsset,
    Paginated,
} from '@/types/inventory';

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

function formatPhilippinePeso(value: number): string {
    return `PHP ${value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function isInteractiveRowTarget(target: EventTarget | null): boolean {
    return (
        target instanceof Element &&
        target.closest('button, a, input, select, textarea') !== null
    );
}

function formatStateLabel(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function AssetStateDialog({
    asset,
    options,
    open,
    onOpenChange,
}: {
    asset: InventoryAsset;
    options: AssetStateOptions;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent onClick={(event) => event.stopPropagation()}>
                <DialogHeader>
                    <DialogTitle>Update asset state</DialogTitle>
                    <DialogDescription>
                        Lifecycle and condition are maintained separately from
                        the custody state, which is calculated from assignment
                        and borrowing records.
                    </DialogDescription>
                </DialogHeader>
                <Form action={update_state(asset)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`lifecycle-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Lifecycle
                                </label>
                                <FormSelect
                                    id={`lifecycle-${asset.inventory_asset_id}`}
                                    name="lifecycle_status"
                                    className={selectClass}
                                    defaultValue={asset.lifecycle_status}
                                    required
                                >
                                    {options.lifecycles
                                        .filter(
                                            (option) =>
                                                !['disposed', 'lost'].includes(
                                                    option.value,
                                                ),
                                        )
                                        .map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                </FormSelect>
                                <InputError message={errors.lifecycle_status} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`condition-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Physical condition
                                </label>
                                <FormSelect
                                    id={`condition-${asset.inventory_asset_id}`}
                                    name="condition_status"
                                    className={selectClass}
                                    defaultValue={asset.condition_status}
                                    required
                                >
                                    {options.conditions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </FormSelect>
                                <InputError message={errors.condition_status} />
                            </div>
                            <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                                Current custody:{' '}
                                <span className="font-medium">
                                    {formatStateLabel(asset.custody_status)}
                                </span>
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Updating…' : 'Update state'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function AssetAccountingDialog({
    asset,
    open,
    onOpenChange,
}: {
    asset: InventoryAsset;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent onClick={(event) => event.stopPropagation()}>
                <DialogHeader>
                    <DialogTitle>Update PPE accounting settings</DialogTitle>
                    <DialogDescription>
                        Change the residual percentage or depreciation start
                        basis for {asset.name}. Every change is written to the
                        audit log.
                    </DialogDescription>
                </DialogHeader>
                <Form action={update_accounting(asset)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`accounting-available-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Available-for-use date
                                </label>
                                <DatePicker
                                    id={`accounting-available-${asset.inventory_asset_id}`}
                                    name="available_for_use_date"
                                    defaultValue={
                                        asset.available_for_use_date?.slice(
                                            0,
                                            10,
                                        ) ?? asset.acquisition_date.slice(0, 10)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.available_for_use_date}
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor={`accounting-residual-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Residual value percentage
                                </label>
                                <Input
                                    id={`accounting-residual-${asset.inventory_asset_id}`}
                                    name="residual_value_percentage"
                                    type="number"
                                    min="0"
                                    max="99.99"
                                    step="0.01"
                                    defaultValue={
                                        asset.residual_value_percentage ?? '5'
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.residual_value_percentage}
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor={`accounting-life-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Useful life (months)
                                </label>
                                <Input
                                    id={`accounting-life-${asset.inventory_asset_id}`}
                                    name="depreciation_useful_life_months"
                                    type="number"
                                    min="1"
                                    max="1200"
                                    defaultValue={
                                        asset.depreciation_useful_life_months
                                    }
                                    required
                                />
                                <InputError
                                    message={
                                        errors.depreciation_useful_life_months
                                    }
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor={`accounting-basis-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Basis or justification
                                </label>
                                <textarea
                                    id={`accounting-basis-${asset.inventory_asset_id}`}
                                    name="residual_value_basis"
                                    className={`${selectClass} min-h-24 py-2`}
                                    defaultValue={
                                        asset.residual_value_basis ??
                                        'COA default residual value of 5%.'
                                    }
                                    maxLength={1000}
                                    required
                                />
                                <InputError
                                    message={errors.residual_value_basis}
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Updating…'
                                    : 'Update accounting settings'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CustodianDialog({
    asset,
    employees,
    open,
    onOpenChange,
}: {
    asset: InventoryAsset;
    employees: HrisReference[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (asset.current_custodian_reference_id !== null) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent onClick={(event) => event.stopPropagation()}>
                    <DialogHeader>
                        <DialogTitle>Transfer accountability</DialogTitle>
                        <DialogDescription>
                            Prepare a PTR for {asset.name}. Custody changes
                            after verification, approval, and recorded handover.
                        </DialogDescription>
                    </DialogHeader>
                    <Button
                        nativeButton={false}
                        render={
                            <Link
                                href={transfersIndex({
                                    query: { asset: asset.inventory_asset_id },
                                })}
                            />
                        }
                    >
                        Prepare property transfer
                    </Button>
                </DialogContent>
            </Dialog>
        );
    }
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent onClick={(event) => event.stopPropagation()}>
                <DialogHeader>
                    <DialogTitle>Assign custodian</DialogTitle>
                    <DialogDescription>
                        {asset.name} · {asset.serial_number}
                    </DialogDescription>
                </DialogHeader>
                <Form action={assign(asset)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`custodian-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Custodian
                                </label>
                                <HrisReferenceCombobox
                                    id={`custodian-${asset.inventory_asset_id}`}
                                    name="hris_reference_id"
                                    references={employees}
                                    required
                                    aria-invalid={Boolean(
                                        errors.hris_reference_id,
                                    )}
                                />
                                <InputError
                                    message={errors.hris_reference_id}
                                />
                            </div>
                            {asset.accounting_classification ===
                                'semi_expendable' && (
                                <div>
                                    <label
                                        htmlFor={`replacement-justification-${asset.inventory_asset_id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Replacement justification (if needed)
                                    </label>
                                    <textarea
                                        id={`replacement-justification-${asset.inventory_asset_id}`}
                                        name="replacement_justification"
                                        className={`${selectClass} min-h-20 py-2`}
                                        placeholder="Required only when the employee already has an active low-valued item of the same type."
                                        maxLength={2000}
                                    />
                                    <InputError
                                        message={
                                            errors.replacement_justification
                                        }
                                    />
                                </div>
                            )}
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Assigning…' : 'Assign custodian'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/*
function BorrowDialog({
    asset,
    employees,
    open,
    onOpenChange,
}: {
    asset: InventoryAsset;
    employees: HrisReference[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent onClick={(event) => event.stopPropagation()}>
                <DialogHeader>
                    <DialogTitle>Record borrowing</DialogTitle>
                    <DialogDescription>
                        {asset.name} ·{' '}
                        {formatStateLabel(asset.lifecycle_status)}
                        {' · '}
                        {formatStateLabel(asset.condition_status)}
                    </DialogDescription>
                </DialogHeader>
                <Form action={returnMethod(asset)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`borrower-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Borrower
                                </label>
                                <HrisReferenceCombobox
                                    id={`borrower-${asset.inventory_asset_id}`}
                                    name="borrower_reference_id"
                                    references={employees}
                                    placeholder="External/manual borrower"
                                    aria-invalid={Boolean(
                                        errors.borrower_reference_id,
                                    )}
                                />
                                <InputError
                                    message={errors.borrower_reference_id}
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor={`borrower-name-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Manual borrower name
                                </label>
                                <Input
                                    id={`borrower-name-${asset.inventory_asset_id}`}
                                    name="borrower_name"
                                    placeholder="e.g. Juan Dela Cruz"
                                    maxLength={255}
                                />
                                <InputError message={errors.borrower_name} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`due-at-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Due date and time
                                </label>
                                <DatePicker
                                    id={`due-at-${asset.inventory_asset_id}`}
                                    name="due_at"
                                    includeTime
                                    min={new Date().toISOString().slice(0, 16)}
                                />
                                <InputError message={errors.due_at} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`borrow-notes-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Purpose or notes
                                </label>
                                <Input
                                    id={`borrow-notes-${asset.inventory_asset_id}`}
                                    name="notes"
                                    placeholder="e.g. Field inspection at Regional Office"
                                    maxLength={2000}
                                />
                                <InputError message={errors.notes} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Recording…' : 'Record borrowing'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
*/

function AssetEventDialog({
    asset,
    eventOptions: availableEventOptions,
    conditionOptions,
    open,
    onOpenChange,
}: {
    asset: InventoryAsset;
    eventOptions: AssetStateOption[];
    conditionOptions: AssetStateOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const eventOptions = availableEventOptions.filter(
        (option) =>
            option.value !== 'transfer' ||
            asset.current_custodian_reference_id === null,
    );
    const [eventType, setEventType] = useState(
        eventOptions[0]?.value ?? 'transfer',
    );
    const [idempotencyKey, setIdempotencyKey] = useState(() =>
        crypto.randomUUID(),
    );
    const requiresAmount = [
        'capital_addition',
        'maintenance',
        'impairment',
        'impairment_reversal',
    ].includes(eventType);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                onClick={(event) => event.stopPropagation()}
            >
                <DialogHeader>
                    <DialogTitle>
                        Record{' '}
                        {asset.accounting_classification === 'ppe'
                            ? 'PPE'
                            : 'semi-expendable'}{' '}
                        lifecycle transaction
                    </DialogTitle>
                    <DialogDescription>
                        Add an immutable, dated record for {asset.name}. Use a
                        correcting transaction if a posted record needs to be
                        superseded. Use Disposal / Waste Materials in the
                        navigation for committee approval and witnessed
                        disposal.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={
                        asset.accounting_classification === 'ppe'
                            ? storeAssetEvent(asset)
                            : storeSemiExpendableEvent(asset)
                    }
                    resetOnSuccess
                    onSuccess={() => {
                        setIdempotencyKey(crypto.randomUUID());
                        onOpenChange(false);
                    }}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="idempotency_key"
                                value={idempotencyKey}
                            />
                            <div>
                                <label
                                    htmlFor={`event-type-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Transaction type
                                </label>
                                <FormSelect
                                    id={`event-type-${asset.inventory_asset_id}`}
                                    name="event_type"
                                    className={selectClass}
                                    value={eventType}
                                    onChange={(event) =>
                                        setEventType(event.target.value)
                                    }
                                    required
                                >
                                    {eventOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </FormSelect>
                                <InputError message={errors.event_type} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`event-date-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Effective date
                                </label>
                                <DatePicker
                                    id={`event-date-${asset.inventory_asset_id}`}
                                    name="event_date"
                                    max={new Date().toISOString().slice(0, 10)}
                                    required
                                />
                                <InputError message={errors.event_date} />
                            </div>
                            <div className="sm:col-span-2">
                                <label
                                    htmlFor={`event-reference-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Source document / reference number
                                </label>
                                <Input
                                    id={`event-reference-${asset.inventory_asset_id}`}
                                    name="reference_no"
                                    placeholder="e.g. JEV, PTR, IIRUP, inspection or approval no."
                                    maxLength={120}
                                    required
                                />
                                <InputError message={errors.reference_no} />
                            </div>
                            {requiresAmount && (
                                <div>
                                    <label
                                        htmlFor={`event-amount-${asset.inventory_asset_id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        {eventType === 'maintenance'
                                            ? 'Maintenance cost'
                                            : 'Recognized amount'}
                                    </label>
                                    <Input
                                        id={`event-amount-${asset.inventory_asset_id}`}
                                        name="amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                            )}
                            {eventType === 'capital_addition' && (
                                <div>
                                    <label
                                        htmlFor={`event-life-${asset.inventory_asset_id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Remaining useful life (months)
                                    </label>
                                    <Input
                                        id={`event-life-${asset.inventory_asset_id}`}
                                        name="remaining_useful_life_months"
                                        type="number"
                                        min="1"
                                        max="1200"
                                        placeholder="Uses remaining original life if blank"
                                    />
                                    <InputError
                                        message={
                                            errors.remaining_useful_life_months
                                        }
                                    />
                                </div>
                            )}
                            {eventType === 'maintenance' && (
                                <>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Vendor / service provider
                                        </label>
                                        <Input name="vendor" maxLength={255} />
                                        <InputError message={errors.vendor} />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Downtime (hours)
                                        </label>
                                        <Input
                                            name="downtime_hours"
                                            type="number"
                                            min="0"
                                            step="0.25"
                                        />
                                        <InputError
                                            message={errors.downtime_hours}
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Parts used
                                        </label>
                                        <textarea
                                            name="parts_used"
                                            className={`${selectClass} min-h-20 py-2`}
                                            maxLength={3000}
                                        />
                                        <InputError
                                            message={errors.parts_used}
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Recommendations
                                        </label>
                                        <textarea
                                            name="recommendations"
                                            className={`${selectClass} min-h-20 py-2`}
                                            maxLength={3000}
                                        />
                                        <InputError
                                            message={errors.recommendations}
                                        />
                                    </div>
                                </>
                            )}
                            {['impairment', 'impairment_reversal'].includes(
                                eventType,
                            ) && (
                                <>
                                    <div className="sm:col-span-2">
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Impairment indicator / trigger
                                        </label>
                                        <Input
                                            name="impairment_trigger"
                                            maxLength={1000}
                                            required
                                        />
                                        <InputError
                                            message={errors.impairment_trigger}
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Assessment basis
                                        </label>
                                        <textarea
                                            name="assessment_basis"
                                            className={`${selectClass} min-h-20 py-2`}
                                            maxLength={3000}
                                            required
                                        />
                                        <InputError
                                            message={errors.assessment_basis}
                                        />
                                    </div>
                                </>
                            )}
                            {eventType === 'transfer' && (
                                <div className="sm:col-span-2">
                                    <label
                                        htmlFor={`event-location-${asset.inventory_asset_id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        New office / location
                                    </label>
                                    <Input
                                        id={`event-location-${asset.inventory_asset_id}`}
                                        name="to_location"
                                        maxLength={255}
                                        required
                                    />
                                    <InputError message={errors.to_location} />
                                </div>
                            )}
                            {eventType === 'physical_count' && (
                                <>
                                    <div>
                                        <label
                                            htmlFor={`event-quantity-${asset.inventory_asset_id}`}
                                            className="mb-1.5 block text-sm font-medium"
                                        >
                                            Quantity found
                                        </label>
                                        <Input
                                            id={`event-quantity-${asset.inventory_asset_id}`}
                                            name="quantity"
                                            type="number"
                                            min="0"
                                            required
                                        />
                                        <InputError message={errors.quantity} />
                                    </div>
                                    <div>
                                        <label
                                            htmlFor={`event-condition-${asset.inventory_asset_id}`}
                                            className="mb-1.5 block text-sm font-medium"
                                        >
                                            Observed condition
                                        </label>
                                        <FormSelect
                                            id={`event-condition-${asset.inventory_asset_id}`}
                                            name="condition_status"
                                            className={selectClass}
                                            required
                                        >
                                            {conditionOptions.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </FormSelect>
                                        <InputError
                                            message={errors.condition_status}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Counted by / committee
                                        </label>
                                        <Input
                                            name="counted_by"
                                            maxLength={255}
                                            required
                                        />
                                        <InputError
                                            message={errors.counted_by}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Count location
                                        </label>
                                        <Input
                                            name="count_location"
                                            maxLength={255}
                                            defaultValue={asset.location ?? ''}
                                            required
                                        />
                                        <InputError
                                            message={errors.count_location}
                                        />
                                    </div>
                                </>
                            )}
                            {eventType === 'unserviceable' && (
                                <>
                                    <input
                                        type="hidden"
                                        name="condition_status"
                                        value="non_usable"
                                    />
                                    <div>
                                        <label
                                            htmlFor={`event-appraisal-${asset.inventory_asset_id}`}
                                            className="mb-1.5 block text-sm font-medium"
                                        >
                                            Appraised value
                                        </label>
                                        <Input
                                            id={`event-appraisal-${asset.inventory_asset_id}`}
                                            name="appraised_value"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                        />
                                        <InputError
                                            message={errors.appraised_value}
                                        />
                                    </div>
                                </>
                            )}
                            {eventType === 'loss' && (
                                <>
                                    <div>
                                        <label
                                            htmlFor={`event-loss-type-${asset.inventory_asset_id}`}
                                            className="mb-1.5 block text-sm font-medium"
                                        >
                                            Incident type
                                        </label>
                                        <FormSelect
                                            id={`event-loss-type-${asset.inventory_asset_id}`}
                                            name="loss_type"
                                            className={selectClass}
                                            required
                                        >
                                            <option value="lost">Lost</option>
                                            <option value="stolen">
                                                Stolen
                                            </option>
                                            <option value="damaged">
                                                Damaged
                                            </option>
                                            <option value="destroyed">
                                                Destroyed
                                            </option>
                                        </FormSelect>
                                        <InputError
                                            message={errors.loss_type}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Incident date
                                        </label>
                                        <DatePicker
                                            id={`incident-date-${asset.inventory_asset_id}`}
                                            name="incident_date"
                                            required
                                        />
                                        <InputError
                                            message={errors.incident_date}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Date discovered
                                        </label>
                                        <DatePicker
                                            id={`date-discovered-${asset.inventory_asset_id}`}
                                            name="date_discovered"
                                            required
                                        />
                                        <InputError
                                            message={errors.date_discovered}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Police / security reference
                                        </label>
                                        <Input
                                            name="security_reference"
                                            maxLength={120}
                                        />
                                        <InputError
                                            message={errors.security_reference}
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label
                                            htmlFor={`event-circumstances-${asset.inventory_asset_id}`}
                                            className="mb-1.5 block text-sm font-medium"
                                        >
                                            Circumstances
                                        </label>
                                        <textarea
                                            id={`event-circumstances-${asset.inventory_asset_id}`}
                                            name="circumstances"
                                            className={`${selectClass} min-h-24 py-2`}
                                            maxLength={3000}
                                            required
                                        />
                                        <InputError
                                            message={errors.circumstances}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Recovery status
                                        </label>
                                        <Input
                                            name="recovery_status"
                                            maxLength={255}
                                        />
                                        <InputError
                                            message={errors.recovery_status}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Relief status
                                        </label>
                                        <Input
                                            name="relief_status"
                                            maxLength={255}
                                        />
                                        <InputError
                                            message={errors.relief_status}
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Liability status
                                        </label>
                                        <Input
                                            name="liability_status"
                                            maxLength={255}
                                        />
                                        <InputError
                                            message={errors.liability_status}
                                        />
                                    </div>
                                </>
                            )}
                            {eventType === 'disposal' && (
                                <>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Disposal method
                                        </label>
                                        <Input
                                            name="disposal_method"
                                            maxLength={255}
                                            required
                                        />
                                        <InputError
                                            message={errors.disposal_method}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Approval number
                                        </label>
                                        <Input
                                            name="approval_no"
                                            maxLength={120}
                                            required
                                        />
                                        <InputError
                                            message={errors.approval_no}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Approval date
                                        </label>
                                        <DatePicker
                                            id={`disposal-approval-date-${asset.inventory_asset_id}`}
                                            name="approval_date"
                                            required
                                        />
                                        <InputError
                                            message={errors.approval_date}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Proceeds
                                        </label>
                                        <Input
                                            name="proceeds"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            required
                                        />
                                        <InputError message={errors.proceeds} />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Appraised value
                                        </label>
                                        <Input
                                            name="appraised_value"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                        />
                                        <InputError
                                            message={errors.appraised_value}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Recipient / buyer
                                        </label>
                                        <Input
                                            name="recipient"
                                            maxLength={255}
                                        />
                                        <InputError
                                            message={errors.recipient}
                                        />
                                    </div>
                                </>
                            )}
                            {['relief_approved', 'relief_denied'].includes(
                                eventType,
                            ) && (
                                <>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Decision / approval number
                                        </label>
                                        <Input
                                            name="approval_no"
                                            maxLength={120}
                                            required
                                        />
                                        <InputError
                                            message={errors.approval_no}
                                        />
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-sm font-medium">
                                            Decision date
                                        </label>
                                        <DatePicker
                                            id={`relief-decision-date-${asset.inventory_asset_id}`}
                                            name="approval_date"
                                            required
                                        />
                                        <InputError
                                            message={errors.approval_date}
                                        />
                                    </div>
                                </>
                            )}
                            <div className="sm:col-span-2">
                                <label
                                    htmlFor={`event-notes-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    {eventType === 'maintenance'
                                        ? 'Work performed'
                                        : 'Remarks'}
                                </label>
                                <textarea
                                    id={`event-notes-${asset.inventory_asset_id}`}
                                    name="notes"
                                    className={`${selectClass} min-h-20 py-2`}
                                    maxLength={3000}
                                    required={eventType === 'maintenance'}
                                />
                                <InputError message={errors.notes} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="sm:col-span-2"
                            >
                                {processing
                                    ? 'Recording…'
                                    : 'Record immutable transaction'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

type AssetActionDialog =
    'accounting' | 'archive' | 'assign' | 'event' | 'state' | null;

function AssetActions({
    asset,
    employees,
    options,
    eventOptions,
    showingArchived,
}: {
    asset: InventoryAsset;
    employees: HrisReference[];
    options: AssetStateOptions;
    eventOptions: AssetStateOption[];
    showingArchived: boolean;
}) {
    const [activeDialog, setActiveDialog] = useState<AssetActionDialog>(null);

    const handleDialogOpenChange = (
        dialog: Exclude<AssetActionDialog, null>,
    ) => {
        return (open: boolean) => {
            setActiveDialog(open ? dialog : null);
        };
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label={`Open actions for ${asset.name}`}
                        />
                    }
                >
                    <EllipsisVertical />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="w-52"
                    onClick={(event) => event.stopPropagation()}
                >
                    <DropdownMenuGroup>
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {showingArchived ? (
                            <Form
                                action={restore(asset)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <DropdownMenuItem
                                        disabled={processing}
                                        render={
                                            <button
                                                type="submit"
                                                className="w-full"
                                            />
                                        }
                                    >
                                        <RotateCcw />
                                        {processing ? 'Restoring…' : 'Restore'}
                                    </DropdownMenuItem>
                                )}
                            </Form>
                        ) : (
                            <>
                                {asset.custody_status === 'borrowed' ? (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            router.visit(returnMethod(asset), {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <RotateCcw /> Return
                                    </DropdownMenuItem>
                                ) : null}
                                <DropdownMenuItem
                                    onClick={() => setActiveDialog('state')}
                                >
                                    <SlidersHorizontal /> State
                                </DropdownMenuItem>
                                {asset.accounting_status !== 'disposed' &&
                                    asset.accounting_status !==
                                        'derecognized' && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                setActiveDialog('event')
                                            }
                                        >
                                            <History /> Record transaction
                                        </DropdownMenuItem>
                                    )}
                                {asset.is_depreciable && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setActiveDialog('accounting')
                                        }
                                    >
                                        <Calculator /> Accounting
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    render={
                                        <a
                                            href={property_tag.url(asset)}
                                            target="_blank"
                                            rel="noreferrer"
                                        />
                                    }
                                >
                                    <QrCode /> Print property tag
                                </DropdownMenuItem>
                                {asset.is_assignable && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setActiveDialog('assign')
                                        }
                                    >
                                        <UserCheck />{' '}
                                        {asset.current_custodian_reference_id !==
                                        null
                                            ? 'Transfer accountability'
                                            : 'Assign custodian'}
                                    </DropdownMenuItem>
                                )}
                                {asset.current_custodian && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            router.visit(unassign(asset), {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <UserMinus /> Unassign custodian
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => setActiveDialog('archive')}
                                >
                                    <Archive /> Archive
                                </DropdownMenuItem>
                            </>
                        )}
                    </DropdownMenuGroup>
                </DropdownMenuContent>
            </DropdownMenu>

            {!showingArchived && (
                <>
                    <AssetStateDialog
                        asset={asset}
                        options={options}
                        open={activeDialog === 'state'}
                        onOpenChange={handleDialogOpenChange('state')}
                    />
                    {asset.accounting_status !== 'disposed' &&
                        asset.accounting_status !== 'derecognized' && (
                            <AssetEventDialog
                                asset={asset}
                                eventOptions={eventOptions}
                                conditionOptions={options.conditions}
                                open={activeDialog === 'event'}
                                onOpenChange={handleDialogOpenChange('event')}
                            />
                        )}
                    {asset.is_depreciable && (
                        <AssetAccountingDialog
                            asset={asset}
                            open={activeDialog === 'accounting'}
                            onOpenChange={handleDialogOpenChange('accounting')}
                        />
                    )}
                    {asset.is_assignable && (
                        <CustodianDialog
                            asset={asset}
                            employees={employees}
                            open={activeDialog === 'assign'}
                            onOpenChange={handleDialogOpenChange('assign')}
                        />
                    )}
                    <ArchiveRecordDialog
                        action={destroy(asset)}
                        recordName={asset.name}
                        recordType="asset"
                        prerequisite="An asset must be returned and unassigned before it can be archived."
                        open={activeDialog === 'archive'}
                        onOpenChange={handleDialogOpenChange('archive')}
                        showTrigger={false}
                    />
                </>
            )}
        </>
    );
}

export default function AssetsIndex({
    assets,
    categories,
    employees,
    filters,
    assetStateOptions,
    assetEventOptions,
    capitalizationThreshold,
    registryType,
    registrySummary,
}: {
    assets: Paginated<InventoryAsset>;
    categories: AssetCategory[];
    employees: HrisReference[];
    filters: {
        search?: string;
        records?: string;
        lifecycle_status?: string;
        condition_status?: string;
        custody_status?: string;
        value_class?: string;
        accountability_status?: string;
        physical_status?: string;
        ics_renewal?: string;
        sort?: string;
        direction?: string;
    };
    assetStateOptions: AssetStateOptions;
    assetEventOptions: AssetStateOption[];
    capitalizationThreshold: number;
    registryType: 'ppe' | 'semi_expendable';
    registrySummary: Record<string, number> | null;
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();
    const { auth } = useAppPage().props;
    const canManageInventory = auth.permissions.manage_inventory;
    const showingArchived = filters.records === 'archived';
    const [selectedAsset, setSelectedAsset] = useState<InventoryAsset | null>(
        null,
    );
    const [selectedCategoryId, setSelectedCategoryId] = useState('');
    const [registrationQuantity, setRegistrationQuantity] = useState(1);
    const selectedCategory = categories.find(
        (category) => String(category.inv_asset_cat_id) === selectedCategoryId,
    );
    const isPpeRegistry = registryType === 'ppe';
    const registryTitle = isPpeRegistry
        ? 'Property, Plant and Equipment'
        : 'Semi-Expendable Property';
    const registryIndex = isPpeRegistry ? index : semiExpendableIndex;
    const registryStore = isPpeRegistry ? store : storeSemiExpendable;

    return (
        <>
            <Head title={registryTitle} />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {registryTitle}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {isPpeRegistry
                                ? `Capitalized property costing PHP ${capitalizationThreshold.toLocaleString()} and above, with PAR accountability and depreciation.`
                                : `Durable property below PHP ${capitalizationThreshold.toLocaleString()}, with ICS accountability and no depreciation.`}
                        </p>
                    </div>
                    {canManageInventory && (
                        <Dialog
                            onOpenChange={(open) => {
                                if (!open) {
                                    setSelectedCategoryId('');
                                }
                            }}
                        >
                            <DialogTrigger render={<Button />}>
                                <Plus />{' '}
                                {isPpeRegistry
                                    ? 'New PPE item'
                                    : 'New semi-expendable item'}
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Register {registryTitle}
                                    </DialogTitle>
                                    <DialogDescription>
                                        Add property acquired by the
                                        organization.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    action={registryStore()}
                                    resetOnSuccess
                                    onSuccess={() => {
                                        setSelectedCategoryId('');
                                        setRegistrationQuantity(1);
                                    }}
                                    className="grid gap-4 md:grid-cols-2"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="md:col-span-2">
                                                <label
                                                    htmlFor="asset-name"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Asset name
                                                </label>
                                                <Input
                                                    id="asset-name"
                                                    name="name"
                                                    placeholder="e.g. Dell Latitude Laptop"
                                                    maxLength={255}
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-category"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Asset category
                                                </label>
                                                <FormSelect
                                                    id="asset-category"
                                                    name="category_id"
                                                    className={selectClass}
                                                    value={selectedCategoryId}
                                                    onChange={(event) =>
                                                        setSelectedCategoryId(
                                                            event.target.value,
                                                        )
                                                    }
                                                    required
                                                >
                                                    <option value="" disabled>
                                                        Asset category
                                                    </option>
                                                    {categories.map(
                                                        (category) => (
                                                            <option
                                                                key={
                                                                    category.inv_asset_cat_id
                                                                }
                                                                value={
                                                                    category.inv_asset_cat_id
                                                                }
                                                            >
                                                                {category.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </FormSelect>
                                                <InputError
                                                    message={errors.category_id}
                                                />
                                            </div>
                                            {!isPpeRegistry && (
                                                <>
                                                    <div>
                                                        <label
                                                            htmlFor="registration-quantity"
                                                            className="mb-1.5 block text-sm font-medium"
                                                        >
                                                            Number of identical
                                                            items
                                                        </label>
                                                        <Input
                                                            id="registration-quantity"
                                                            name="registration_quantity"
                                                            type="number"
                                                            min="1"
                                                            max="500"
                                                            value={
                                                                registrationQuantity
                                                            }
                                                            onChange={(event) =>
                                                                setRegistrationQuantity(
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ) || 1,
                                                                )
                                                            }
                                                            required
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.registration_quantity
                                                            }
                                                        />
                                                    </div>
                                                    {registrationQuantity >
                                                        1 && (
                                                        <div>
                                                            <label
                                                                htmlFor="property-number-prefix"
                                                                className="mb-1.5 block text-sm font-medium"
                                                            >
                                                                Property number
                                                                prefix
                                                            </label>
                                                            <Input
                                                                id="property-number-prefix"
                                                                name="property_number_prefix"
                                                                placeholder="e.g. SEP-ICT-2026"
                                                                maxLength={90}
                                                                required
                                                            />
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                Generates one
                                                                record per item,
                                                                ending in 0001,
                                                                0002, and so on.
                                                            </p>
                                                            <InputError
                                                                message={
                                                                    errors.property_number_prefix
                                                                }
                                                            />
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                            <div>
                                                <label
                                                    htmlFor="serial-number"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Serial number
                                                </label>
                                                <Input
                                                    id="serial-number"
                                                    name="serial_number"
                                                    placeholder="e.g. SN-9F3A21B7"
                                                    maxLength={100}
                                                    disabled={
                                                        registrationQuantity > 1
                                                    }
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    A serial or property number
                                                    is required.
                                                </p>
                                                <InputError
                                                    message={
                                                        errors.serial_number
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="old-property-number"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Old property number
                                                </label>
                                                <Input
                                                    id="old-property-number"
                                                    name="old_property_number"
                                                    maxLength={100}
                                                />
                                                <InputError
                                                    message={
                                                        errors.old_property_number
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="property-number"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Property number
                                                </label>
                                                <Input
                                                    id="property-number"
                                                    name="property_number"
                                                    placeholder="e.g. PPE-2026-001"
                                                    maxLength={100}
                                                    disabled={
                                                        registrationQuantity > 1
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.property_number
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="account-code"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Account code
                                                </label>
                                                <Input
                                                    key={`account-${selectedCategoryId}`}
                                                    id="account-code"
                                                    name="account_code"
                                                    placeholder="e.g. 10605020"
                                                    maxLength={50}
                                                    defaultValue={
                                                        selectedCategory?.account_code ??
                                                        ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.account_code
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-subcategory"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Asset subcategory
                                                </label>
                                                <FormSelect
                                                    id="asset-subcategory"
                                                    name="subcategory_id"
                                                    className={selectClass}
                                                    defaultValue=""
                                                    disabled={!selectedCategory}
                                                    required
                                                >
                                                    <option value="" disabled>
                                                        {selectedCategory
                                                            ? 'Select subcategory'
                                                            : 'Select a category first'}
                                                    </option>
                                                    {selectedCategory?.subcategories?.map(
                                                        (subcategory) => (
                                                            <option
                                                                key={
                                                                    subcategory.inventory_asset_subcategory_id
                                                                }
                                                                value={
                                                                    subcategory.inventory_asset_subcategory_id
                                                                }
                                                            >
                                                                {
                                                                    subcategory.name
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </FormSelect>
                                                <InputError
                                                    message={
                                                        errors.subcategory_id
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-brand"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Brand
                                                </label>
                                                <Input
                                                    id="asset-brand"
                                                    name="brand"
                                                    placeholder="e.g. Dell"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={errors.brand}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-manufacturer"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Manufacturer
                                                </label>
                                                <Input
                                                    id="asset-manufacturer"
                                                    name="manufacturer"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={
                                                        errors.manufacturer
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-model"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Model
                                                </label>
                                                <Input
                                                    id="asset-model"
                                                    name="model"
                                                    placeholder="e.g. Latitude 5450"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={errors.model}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-location"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Location
                                                </label>
                                                <Input
                                                    id="asset-location"
                                                    name="location"
                                                    placeholder="e.g. ICT Office, Room 204"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={errors.location}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="fund-cluster"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Fund cluster
                                                </label>
                                                <Input
                                                    id="fund-cluster"
                                                    name="fund_cluster"
                                                    placeholder="e.g. 01-Regular Agency Fund"
                                                    maxLength={100}
                                                />
                                                <InputError
                                                    message={
                                                        errors.fund_cluster
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="acquisition-date"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Acquisition date
                                                </label>
                                                <DatePicker
                                                    id="acquisition-date"
                                                    name="acquisition_date"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.acquisition_date
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="acquisition-method"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Acquisition method
                                                </label>
                                                <FormSelect
                                                    id="acquisition-method"
                                                    name="acquisition_method"
                                                    className={selectClass}
                                                    defaultValue="purchase"
                                                    required
                                                >
                                                    <option value="purchase">
                                                        Purchase
                                                    </option>
                                                    <option value="donation">
                                                        Donation
                                                    </option>
                                                    <option value="transfer">
                                                        Transfer
                                                    </option>
                                                    <option value="construction">
                                                        Construction
                                                    </option>
                                                    <option value="other">
                                                        Other
                                                    </option>
                                                </FormSelect>
                                                <InputError
                                                    message={
                                                        errors.acquisition_method
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="acquisition-cost"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Acquisition cost
                                                </label>
                                                <Input
                                                    id="acquisition-cost"
                                                    name="acquisition_cost"
                                                    type="number"
                                                    step="0.01"
                                                    placeholder={
                                                        isPpeRegistry
                                                            ? 'e.g. 75000.00'
                                                            : 'e.g. 25000.00'
                                                    }
                                                    min={
                                                        isPpeRegistry
                                                            ? 0.01
                                                            : 0.01
                                                    }
                                                    max={
                                                        isPpeRegistry
                                                            ? undefined
                                                            : capitalizationThreshold -
                                                              0.01
                                                    }
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.acquisition_cost
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="direct-costs"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Directly attributable costs
                                                </label>
                                                <Input
                                                    id="direct-costs"
                                                    name="directly_attributable_costs"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue="0"
                                                />
                                                <InputError
                                                    message={
                                                        errors.directly_attributable_costs
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Supplier / source
                                                </label>
                                                <Input
                                                    name="supplier_name"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={
                                                        errors.supplier_name
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Purchase order number
                                                </label>
                                                <Input
                                                    name="purchase_order_no"
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={
                                                        errors.purchase_order_no
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    IAR number
                                                </label>
                                                <Input
                                                    name="inspection_acceptance_report_no"
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={
                                                        errors.inspection_acceptance_report_no
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Invoice number
                                                </label>
                                                <Input
                                                    name="invoice_no"
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={errors.invoice_no}
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Disbursement voucher number
                                                </label>
                                                <Input
                                                    name="disbursement_voucher_no"
                                                    maxLength={120}
                                                />
                                                <InputError
                                                    message={
                                                        errors.disbursement_voucher_no
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Delivery date
                                                </label>
                                                <DatePicker
                                                    id="delivery-date"
                                                    name="delivery_date"
                                                />
                                                <InputError
                                                    message={
                                                        errors.delivery_date
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Inspection date
                                                </label>
                                                <DatePicker
                                                    id="inspection-date"
                                                    name="inspection_date"
                                                />
                                                <InputError
                                                    message={
                                                        errors.inspection_date
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Acceptance date
                                                </label>
                                                <DatePicker
                                                    id="acceptance-date"
                                                    name="acceptance_date"
                                                />
                                                <InputError
                                                    message={
                                                        errors.acceptance_date
                                                    }
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Description
                                                </label>
                                                <textarea
                                                    name="description"
                                                    className={`${selectClass} min-h-20 py-2`}
                                                    maxLength={2000}
                                                />
                                                <InputError
                                                    message={errors.description}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label className="mb-1.5 block text-sm font-medium">
                                                    Technical specifications
                                                </label>
                                                <textarea
                                                    name="specifications"
                                                    className={`${selectClass} min-h-20 py-2`}
                                                    maxLength={5000}
                                                />
                                                <InputError
                                                    message={
                                                        errors.specifications
                                                    }
                                                />
                                            </div>
                                            {isPpeRegistry && (
                                                <>
                                                    {selectedCategory?.is_depreciable !==
                                                        false && (
                                                        <>
                                                            <div>
                                                                <label
                                                                    htmlFor="available-for-use-date"
                                                                    className="mb-1.5 block text-sm font-medium"
                                                                >
                                                                    Available-for-use
                                                                    date
                                                                </label>
                                                                <DatePicker
                                                                    id="available-for-use-date"
                                                                    name="available_for_use_date"
                                                                />
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    Required for
                                                                    PPE. This is
                                                                    the basis
                                                                    for starting
                                                                    depreciation.
                                                                </p>
                                                                <InputError
                                                                    message={
                                                                        errors.available_for_use_date
                                                                    }
                                                                />
                                                            </div>
                                                            <div>
                                                                <label
                                                                    htmlFor="residual-value-percentage"
                                                                    className="mb-1.5 block text-sm font-medium"
                                                                >
                                                                    Residual
                                                                    value
                                                                    percentage
                                                                </label>
                                                                <Input
                                                                    key={`residual-${selectedCategoryId}`}
                                                                    id="residual-value-percentage"
                                                                    name="residual_value_percentage"
                                                                    type="number"
                                                                    min="0"
                                                                    max="99.99"
                                                                    step="0.01"
                                                                    defaultValue={
                                                                        selectedCategory?.default_residual_value_percentage ??
                                                                        '5'
                                                                    }
                                                                />
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    Applied only
                                                                    to PPE;
                                                                    COA's
                                                                    standard
                                                                    starting
                                                                    value is 5%.
                                                                </p>
                                                                <InputError
                                                                    message={
                                                                        errors.residual_value_percentage
                                                                    }
                                                                />
                                                            </div>
                                                            <div className="md:col-span-2">
                                                                <label
                                                                    htmlFor="residual-value-basis"
                                                                    className="mb-1.5 block text-sm font-medium"
                                                                >
                                                                    Residual
                                                                    value basis
                                                                </label>
                                                                <textarea
                                                                    id="residual-value-basis"
                                                                    name="residual_value_basis"
                                                                    className={`${selectClass} min-h-20 py-2`}
                                                                    defaultValue="COA default residual value of 5%."
                                                                    maxLength={
                                                                        1000
                                                                    }
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.residual_value_basis
                                                                    }
                                                                />
                                                            </div>
                                                            <div>
                                                                <label
                                                                    htmlFor="useful-life"
                                                                    className="mb-1.5 block text-sm font-medium"
                                                                >
                                                                    Useful life
                                                                    in months
                                                                </label>
                                                                <Input
                                                                    key={`life-${selectedCategoryId}`}
                                                                    id="useful-life"
                                                                    name="depreciation_useful_life_months"
                                                                    type="number"
                                                                    min="1"
                                                                    max="1200"
                                                                    defaultValue={
                                                                        selectedCategory?.default_useful_life_months ??
                                                                        60
                                                                    }
                                                                    placeholder="e.g. 60"
                                                                    required
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.depreciation_useful_life_months
                                                                    }
                                                                />
                                                            </div>
                                                        </>
                                                    )}
                                                </>
                                            )}
                                            {!isPpeRegistry && (
                                                <>
                                                    <div>
                                                        <label
                                                            htmlFor="semi-useful-life"
                                                            className="mb-1.5 block text-sm font-medium"
                                                        >
                                                            Estimated useful
                                                            life in months
                                                        </label>
                                                        <Input
                                                            key={`semi-life-${selectedCategoryId}`}
                                                            id="semi-useful-life"
                                                            name="depreciation_useful_life_months"
                                                            type="number"
                                                            min="1"
                                                            max="1200"
                                                            defaultValue={
                                                                selectedCategory?.default_useful_life_months ??
                                                                60
                                                            }
                                                            required
                                                        />
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Used for property
                                                            control and
                                                            low-value
                                                            accountability,
                                                            never PPE
                                                            depreciation.
                                                        </p>
                                                        <InputError
                                                            message={
                                                                errors.depreciation_useful_life_months
                                                            }
                                                        />
                                                    </div>
                                                    <div>
                                                        <label
                                                            htmlFor="semi-life-reason"
                                                            className="mb-1.5 block text-sm font-medium"
                                                        >
                                                            Useful-life override
                                                            reason
                                                        </label>
                                                        <Input
                                                            id="semi-life-reason"
                                                            name="useful_life_override_reason"
                                                            placeholder="Required only when changing the category default"
                                                            maxLength={1000}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.useful_life_override_reason
                                                            }
                                                        />
                                                    </div>
                                                </>
                                            )}
                                            <div className="rounded-lg border bg-muted/40 p-3 text-sm md:col-span-2">
                                                {isPpeRegistry
                                                    ? `This module accepts PPE costing PHP ${capitalizationThreshold.toLocaleString()} or more.`
                                                    : `This module accepts semi-expendable property below PHP ${capitalizationThreshold.toLocaleString()}.`}
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-unit"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Unit of measure
                                                </label>
                                                <Input
                                                    id="asset-unit"
                                                    name="unit_of_measure"
                                                    defaultValue="unit"
                                                    placeholder="e.g. unit"
                                                    maxLength={50}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.unit_of_measure
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-lifecycle"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Lifecycle
                                                </label>
                                                <FormSelect
                                                    id="asset-lifecycle"
                                                    name="lifecycle_status"
                                                    className={selectClass}
                                                    defaultValue="active"
                                                    required
                                                >
                                                    {assetStateOptions.lifecycles
                                                        .filter(
                                                            (option) =>
                                                                ![
                                                                    'disposed',
                                                                    'lost',
                                                                ].includes(
                                                                    option.value,
                                                                ),
                                                        )
                                                        .map((option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ))}
                                                </FormSelect>
                                                <InputError
                                                    message={
                                                        errors.lifecycle_status
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-condition"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Physical condition
                                                </label>
                                                <FormSelect
                                                    id="asset-condition"
                                                    name="condition_status"
                                                    className={selectClass}
                                                    defaultValue="good"
                                                    required
                                                >
                                                    {assetStateOptions.conditions.map(
                                                        (option) => (
                                                            <option
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </option>
                                                        ),
                                                    )}
                                                </FormSelect>
                                                <InputError
                                                    message={
                                                        errors.condition_status
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                data-test="register-asset-submit"
                                                disabled={processing}
                                                className="md:col-span-2"
                                            >
                                                {processing
                                                    ? 'Registering…'
                                                    : `Register ${isPpeRegistry ? 'PPE' : 'semi-expendable property'}`}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
                {isPpeRegistry && registrySummary && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        {[
                            ['Total PPE items', registrySummary.total],
                            [
                                'Total acquisition cost',
                                formatPhilippinePeso(
                                    registrySummary.acquisition_cost,
                                ),
                            ],
                            [
                                'Total net book value',
                                formatPhilippinePeso(
                                    registrySummary.net_book_value,
                                ),
                            ],
                            [
                                'Available for assignment',
                                registrySummary.available_for_assignment,
                            ],
                            [
                                'Items requiring action',
                                registrySummary.requiring_action,
                            ],
                        ].map(([label, value]) => (
                            <Card key={String(label)}>
                                <CardContent className="p-4">
                                    <p className="text-xs text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {typeof value === 'number'
                                            ? value.toLocaleString()
                                            : value}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                {!isPpeRegistry && registrySummary && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        {[
                            ['Total items', registrySummary.total],
                            [
                                'Total acquisition cost',
                                formatPhilippinePeso(
                                    registrySummary.acquisition_cost,
                                ),
                            ],
                            [
                                'Issued / accountable',
                                registrySummary.issued_or_accountable,
                            ],
                            ['On hand', registrySummary.on_hand],
                            [
                                'Available for reissue',
                                registrySummary.available_for_reissue,
                            ],
                            ['Exceptions', registrySummary.exceptions],
                        ].map(([label, value]) => (
                            <Card key={String(label)}>
                                <CardContent className="p-4">
                                    <p className="text-xs text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-semibold">
                                        {typeof value === 'number'
                                            ? value.toLocaleString()
                                            : value}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {showingArchived
                                ? 'Archived assets'
                                : registryTitle}
                        </CardTitle>
                        <CardDescription>
                            {assets.total}{' '}
                            {showingArchived
                                ? 'archived assets.'
                                : 'registered assets.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <Form
                            action={registryIndex()}
                            options={{ preserveState: true }}
                            className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                        >
                            {({ errors }) => (
                                <>
                                    <div className="grid w-full gap-1.5 sm:w-80">
                                        <label
                                            htmlFor="asset-search"
                                            className="text-sm font-medium"
                                        >
                                            Search assets
                                        </label>
                                        <Input
                                            id="asset-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="e.g. Dell Laptop, SN-9F3A21B7, or PPE-2026-001"
                                            maxLength={100}
                                            onChange={submitAfterDelay}
                                        />
                                        <InputError message={errors.search} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="asset-lifecycle-filter"
                                            className="text-sm font-medium"
                                        >
                                            Lifecycle
                                        </label>
                                        <FormSelect
                                            id="asset-lifecycle-filter"
                                            name="lifecycle_status"
                                            defaultValue={
                                                filters.lifecycle_status ?? ''
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">
                                                All lifecycles
                                            </option>
                                            {assetStateOptions.lifecycles.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </FormSelect>
                                        <InputError
                                            message={errors.lifecycle_status}
                                        />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="asset-condition-filter"
                                            className="text-sm font-medium"
                                        >
                                            Condition
                                        </label>
                                        <FormSelect
                                            id="asset-condition-filter"
                                            name="condition_status"
                                            defaultValue={
                                                filters.condition_status ?? ''
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">
                                                All conditions
                                            </option>
                                            {assetStateOptions.conditions.map(
                                                (option) => (
                                                    <option
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ),
                                            )}
                                        </FormSelect>
                                        <InputError
                                            message={errors.condition_status}
                                        />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="asset-custody-filter"
                                            className="text-sm font-medium"
                                        >
                                            Custody
                                        </label>
                                        <FormSelect
                                            id="asset-custody-filter"
                                            name="custody_status"
                                            defaultValue={
                                                filters.custody_status ?? ''
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">
                                                All custody states
                                            </option>
                                            <option value="available">
                                                Available
                                            </option>
                                            <option value="assigned">
                                                Assigned
                                            </option>
                                            <option value="borrowed">
                                                Borrowed
                                            </option>
                                        </FormSelect>
                                        <InputError
                                            message={errors.custody_status}
                                        />
                                    </div>
                                    {!isPpeRegistry && (
                                        <>
                                            <div className="grid w-full gap-1.5 sm:w-44">
                                                <label className="text-sm font-medium">
                                                    Value class
                                                </label>
                                                <FormSelect
                                                    name="value_class"
                                                    defaultValue={
                                                        filters.value_class ??
                                                        ''
                                                    }
                                                    className={selectClass}
                                                    onChange={submitImmediately}
                                                >
                                                    <option value="">
                                                        All values
                                                    </option>
                                                    <option value="low">
                                                        Low-valued
                                                    </option>
                                                    <option value="high">
                                                        High-valued
                                                    </option>
                                                </FormSelect>
                                            </div>
                                            <div className="grid w-full gap-1.5 sm:w-52">
                                                <label className="text-sm font-medium">
                                                    Accountability
                                                </label>
                                                <FormSelect
                                                    name="accountability_status"
                                                    defaultValue={
                                                        filters.accountability_status ??
                                                        ''
                                                    }
                                                    className={selectClass}
                                                    onChange={submitImmediately}
                                                >
                                                    <option value="">
                                                        All accountability
                                                    </option>
                                                    <option value="unassigned">
                                                        Unassigned
                                                    </option>
                                                    <option value="pending_recipient">
                                                        Pending recipient
                                                    </option>
                                                    <option value="accountable">
                                                        Accountable
                                                    </option>
                                                    <option value="returned">
                                                        Returned
                                                    </option>
                                                    <option value="accountability_extinguished">
                                                        Extinguished
                                                    </option>
                                                    <option value="pending_relief">
                                                        Pending relief
                                                    </option>
                                                    <option value="relieved">
                                                        Relieved
                                                    </option>
                                                </FormSelect>
                                            </div>
                                            <div className="grid w-full gap-1.5 sm:w-48">
                                                <label className="text-sm font-medium">
                                                    Physical state
                                                </label>
                                                <FormSelect
                                                    name="physical_status"
                                                    defaultValue={
                                                        filters.physical_status ??
                                                        ''
                                                    }
                                                    className={selectClass}
                                                    onChange={submitImmediately}
                                                >
                                                    <option value="">
                                                        All physical states
                                                    </option>
                                                    <option value="in_property_custody">
                                                        Property custody
                                                    </option>
                                                    <option value="issued">
                                                        Issued
                                                    </option>
                                                    <option value="returned">
                                                        Returned
                                                    </option>
                                                    <option value="transferred">
                                                        Transferred
                                                    </option>
                                                    <option value="for_disposal">
                                                        For disposal
                                                    </option>
                                                    <option value="disposed">
                                                        Disposed
                                                    </option>
                                                    <option value="missing">
                                                        Missing
                                                    </option>
                                                </FormSelect>
                                            </div>
                                            <div className="grid w-full gap-1.5 sm:w-44">
                                                <label className="text-sm font-medium">
                                                    ICS renewal
                                                </label>
                                                <FormSelect
                                                    name="ics_renewal"
                                                    defaultValue={
                                                        filters.ics_renewal ??
                                                        ''
                                                    }
                                                    className={selectClass}
                                                    onChange={submitImmediately}
                                                >
                                                    <option value="">
                                                        All renewal states
                                                    </option>
                                                    <option value="due_soon">
                                                        Due soon
                                                    </option>
                                                    <option value="overdue">
                                                        Overdue
                                                    </option>
                                                </FormSelect>
                                            </div>
                                        </>
                                    )}
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="asset-records-filter"
                                            className="text-sm font-medium"
                                        >
                                            Records
                                        </label>
                                        <FormSelect
                                            id="asset-records-filter"
                                            name="records"
                                            defaultValue={
                                                filters.records ?? 'active'
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="active">
                                                Active records
                                            </option>
                                            <option value="archived">
                                                Archived records
                                            </option>
                                        </FormSelect>
                                        <InputError message={errors.records} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-48">
                                        <label
                                            htmlFor="asset-sort"
                                            className="text-sm font-medium"
                                        >
                                            Sort by
                                        </label>
                                        <FormSelect
                                            id="asset-sort"
                                            name="sort"
                                            value={
                                                filters.sort ||
                                                'inventory_asset_id'
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="inventory_asset_id">
                                                Newest record
                                            </option>
                                            <option value="property_number">
                                                Property number
                                            </option>
                                            <option value="name">Name</option>
                                            <option value="acquisition_date">
                                                Acquisition date
                                            </option>
                                            <option value="acquisition_cost">
                                                Acquisition cost
                                            </option>
                                            <option value="updated_at">
                                                Last updated
                                            </option>
                                        </FormSelect>
                                        <InputError message={errors.sort} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-40">
                                        <label
                                            htmlFor="asset-direction"
                                            className="text-sm font-medium"
                                        >
                                            Direction
                                        </label>
                                        <FormSelect
                                            id="asset-direction"
                                            name="direction"
                                            defaultValue={
                                                filters.direction ?? 'desc'
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="asc">
                                                Ascending
                                            </option>
                                            <option value="desc">
                                                Descending
                                            </option>
                                        </FormSelect>
                                        <InputError
                                            message={errors.direction}
                                        />
                                    </div>
                                </>
                            )}
                        </Form>
                        <div className="overflow-x-auto rounded-lg border border-border/70">
                            <Table className="w-full min-w-[1050px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <TableRow>
                                        <TableHead className="pb-3">
                                            Property
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Asset
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Custodian / borrower
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Location
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            {isPpeRegistry
                                                ? 'Book value'
                                                : 'Accounting'}
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            State
                                        </TableHead>
                                        {canManageInventory && (
                                            <TableHead className="pb-3 text-right">
                                                Actions
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {assets.data.map((asset) => (
                                        <Tooltip key={asset.inventory_asset_id}>
                                            <TooltipTrigger
                                                render={
                                                    <TableRow
                                                        role="button"
                                                        tabIndex={0}
                                                        aria-haspopup="dialog"
                                                        aria-label={`View details for ${asset.name}`}
                                                        className="cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                                        onClick={(event) => {
                                                            if (
                                                                !isInteractiveRowTarget(
                                                                    event.target,
                                                                )
                                                            ) {
                                                                setSelectedAsset(
                                                                    asset,
                                                                );
                                                            }
                                                        }}
                                                        onKeyDown={(event) => {
                                                            if (
                                                                event.target ===
                                                                    event.currentTarget &&
                                                                (event.key ===
                                                                    'Enter' ||
                                                                    event.key ===
                                                                        ' ')
                                                            ) {
                                                                event.preventDefault();
                                                                setSelectedAsset(
                                                                    asset,
                                                                );
                                                            }
                                                        }}
                                                    />
                                                }
                                            >
                                                <TableCell className="py-3">
                                                    <div className="font-mono text-xs">
                                                        {asset.property_number ??
                                                            'No property no.'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {asset.serial_number}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <div className="font-medium">
                                                        {asset.name}
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className="mt-1"
                                                    >
                                                        {formatStateLabel(
                                                            asset.accounting_classification,
                                                        )}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <div>
                                                        {asset.current_custodian
                                                            ?.name ??
                                                            'Unassigned'}
                                                    </div>
                                                    {asset.active_borrowing && (
                                                        <div className="text-xs text-amber-600">
                                                            Borrowed by{' '}
                                                            {asset
                                                                .active_borrowing
                                                                .borrower_reference
                                                                ?.name ??
                                                                asset
                                                                    .active_borrowing
                                                                    .borrower_name}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-3 text-muted-foreground">
                                                    {asset.location ?? '—'}
                                                </TableCell>
                                                <TableCell className="py-3 text-right">
                                                    {isPpeRegistry ? (
                                                        <>
                                                            <div>
                                                                PHP{' '}
                                                                {Number(
                                                                    asset.book_value,
                                                                ).toLocaleString()}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                Residual{' '}
                                                                {
                                                                    asset.residual_value_percentage
                                                                }
                                                                %
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <div>
                                                                {formatStateLabel(
                                                                    asset.accounting_status,
                                                                )}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {asset.semi_expendable_value_class
                                                                    ? `${formatStateLabel(asset.semi_expendable_value_class)}-valued`
                                                                    : 'Needs review'}
                                                            </div>
                                                        </>
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <div className="flex max-w-48 flex-wrap gap-1.5">
                                                        <Badge
                                                            variant={
                                                                asset.lifecycle_status ===
                                                                'active'
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {formatStateLabel(
                                                                asset.lifecycle_status,
                                                            )}
                                                        </Badge>
                                                        <Badge variant="outline">
                                                            {formatStateLabel(
                                                                asset.condition_status,
                                                            )}
                                                        </Badge>
                                                        <Badge
                                                            variant={
                                                                asset.custody_status ===
                                                                'borrowed'
                                                                    ? 'destructive'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {formatStateLabel(
                                                                asset.custody_status,
                                                            )}
                                                        </Badge>
                                                        {!isPpeRegistry &&
                                                            asset.physical_status && (
                                                                <Badge variant="outline">
                                                                    {formatStateLabel(
                                                                        asset.physical_status,
                                                                    )}
                                                                </Badge>
                                                            )}
                                                        {!isPpeRegistry &&
                                                            asset.accountability_status && (
                                                                <Badge variant="secondary">
                                                                    {formatStateLabel(
                                                                        asset.accountability_status,
                                                                    )}
                                                                </Badge>
                                                            )}
                                                    </div>
                                                </TableCell>
                                                {canManageInventory && (
                                                    <TableCell className="py-3">
                                                        <div className="flex justify-end">
                                                            <AssetActions
                                                                asset={asset}
                                                                employees={
                                                                    employees
                                                                }
                                                                options={
                                                                    assetStateOptions
                                                                }
                                                                eventOptions={
                                                                    assetEventOptions
                                                                }
                                                                showingArchived={
                                                                    showingArchived
                                                                }
                                                            />
                                                        </div>
                                                    </TableCell>
                                                )}
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Click for more info
                                            </TooltipContent>
                                        </Tooltip>
                                    ))}
                                    {assets.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={
                                                    canManageInventory ? 7 : 6
                                                }
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No assets found.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataPagination links={assets.links} />
                    </CardContent>
                </Card>

                {selectedAsset && (
                    <AssetDetailsDialog
                        asset={selectedAsset}
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setSelectedAsset(null);
                            }
                        }}
                    />
                )}
            </div>
        </>
    );
}

AssetsIndex.layout = {
    breadcrumbs: [{ title: 'Property registry', href: index() }],
};
