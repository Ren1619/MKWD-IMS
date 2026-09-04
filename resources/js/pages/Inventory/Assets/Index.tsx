import { Form, Head, router } from '@inertiajs/react';
import {
    Archive,
    Calculator,
    EllipsisVertical,
    Handshake,
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
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppPage } from '@/hooks/use-app-page';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import {
    assign,
    borrow,
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
import type {
    AssetCategory,
    AssetStateOptions,
    HrisReference,
    InventoryAsset,
    Paginated,
} from '@/types/inventory';

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

const registryTabs = [
    { value: 'all', label: 'All durable property' },
    { value: 'ppe', label: 'PPE' },
    { value: 'semi_expendable', label: 'Semi-expendable' },
    { value: 'needs_review', label: 'Needs review' },
] as const;

type RegistryTab = (typeof registryTabs)[number]['value'];

function isRegistryTab(value: unknown): value is RegistryTab {
    return registryTabs.some((tab) => tab.value === value);
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
                                <select
                                    id={`lifecycle-${asset.inventory_asset_id}`}
                                    name="lifecycle_status"
                                    className={selectClass}
                                    defaultValue={asset.lifecycle_status}
                                    required
                                >
                                    {options.lifecycles.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.lifecycle_status} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`condition-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Physical condition
                                </label>
                                <select
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
                                </select>
                                <InputError message={errors.condition_status} />
                            </div>
                            <div className="rounded-lg border bg-muted/40 p-3 text-sm">
                                Current custody:{' '}
                                <span className="font-medium">
                                    {formatStateLabel(asset.custody_status)}
                                </span>
                            </div>
                            <Button disabled={processing}>
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
                                <Input
                                    id={`accounting-available-${asset.inventory_asset_id}`}
                                    name="available_for_use_date"
                                    type="date"
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
                                    min="5"
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
                            <Button disabled={processing}>
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
                                <select
                                    id={`custodian-${asset.inventory_asset_id}`}
                                    name="hris_reference_id"
                                    className={selectClass}
                                    defaultValue=""
                                    required
                                >
                                    <option value="" disabled>
                                        Select employee
                                    </option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.name}
                                            {employee.code
                                                ? ` (${employee.code})`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.hris_reference_id}
                                />
                            </div>
                            <Button disabled={processing}>
                                {processing ? 'Assigning…' : 'Assign custodian'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

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
                <Form action={borrow(asset)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`borrower-${asset.inventory_asset_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Borrower
                                </label>
                                <select
                                    id={`borrower-${asset.inventory_asset_id}`}
                                    name="borrower_reference_id"
                                    className={selectClass}
                                    defaultValue=""
                                >
                                    <option value="">
                                        External/manual borrower
                                    </option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.name}
                                        </option>
                                    ))}
                                </select>
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
                                <Input
                                    id={`due-at-${asset.inventory_asset_id}`}
                                    name="due_at"
                                    type="datetime-local"
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
                            <Button disabled={processing}>
                                {processing ? 'Recording…' : 'Record borrowing'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

type AssetActionDialog =
    'accounting' | 'archive' | 'assign' | 'borrow' | 'state' | null;

function AssetActions({
    asset,
    employees,
    options,
    showingArchived,
}: {
    asset: InventoryAsset;
    employees: HrisReference[];
    options: AssetStateOptions;
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
                                ) : asset.is_borrowable ? (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setActiveDialog('borrow')
                                        }
                                    >
                                        <Handshake /> Borrow
                                    </DropdownMenuItem>
                                ) : null}
                                <DropdownMenuItem
                                    onClick={() => setActiveDialog('state')}
                                >
                                    <SlidersHorizontal /> State
                                </DropdownMenuItem>
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
                                        <UserCheck /> Assign custodian
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
                    {asset.custody_status !== 'borrowed' &&
                        asset.is_borrowable && (
                            <BorrowDialog
                                asset={asset}
                                employees={employees}
                                open={activeDialog === 'borrow'}
                                onOpenChange={handleDialogOpenChange('borrow')}
                            />
                        )}
                    <AssetStateDialog
                        asset={asset}
                        options={options}
                        open={activeDialog === 'state'}
                        onOpenChange={handleDialogOpenChange('state')}
                    />
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
    capitalizationThreshold,
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
        accounting_classification?: string;
    };
    assetStateOptions: AssetStateOptions;
    capitalizationThreshold: number;
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();
    const { auth } = useAppPage().props;
    const canManageInventory = auth.permissions.manage_inventory;
    const showingArchived = filters.records === 'archived';
    const [selectedAsset, setSelectedAsset] = useState<InventoryAsset | null>(
        null,
    );
    const registryTitle =
        filters.accounting_classification === 'ppe'
            ? 'PPE Registry'
            : filters.accounting_classification === 'semi_expendable'
              ? 'Semi-Expendable Property Registry'
              : filters.accounting_classification === 'needs_review'
                ? 'Assets Needing Accounting Review'
                : 'Durable Property Registry';
    const activeRegistryTab: RegistryTab = isRegistryTab(
        filters.accounting_classification,
    )
        ? filters.accounting_classification
        : 'all';

    const changeRegistryTab = (value: unknown): void => {
        if (!isRegistryTab(value) || value === activeRegistryTab) {
            return;
        }

        router.visit(
            index({
                query: {
                    ...filters,
                    accounting_classification:
                        value === 'all' ? undefined : value,
                },
            }),
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

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
                            Separate PPE and semi-expendable accountability,
                            custody, valuation, and property tags.
                        </p>
                    </div>
                    {canManageInventory && (
                        <Dialog>
                            <DialogTrigger render={<Button />}>
                                <Plus /> New asset
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>Register asset</DialogTitle>
                                    <DialogDescription>
                                        Add property acquired by the
                                        organization.
                                    </DialogDescription>
                                </DialogHeader>
                                <Form
                                    action={store()}
                                    resetOnSuccess
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
                                                <select
                                                    id="asset-category"
                                                    name="category_id"
                                                    className={selectClass}
                                                    defaultValue=""
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
                                                </select>
                                                <InputError
                                                    message={errors.category_id}
                                                />
                                            </div>
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
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.serial_number
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
                                                />
                                                <InputError
                                                    message={
                                                        errors.property_number
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="asset-type"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Type or article
                                                </label>
                                                <Input
                                                    id="asset-type"
                                                    name="type"
                                                    placeholder="e.g. Notebook computer"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={errors.type}
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
                                                <Input
                                                    id="acquisition-date"
                                                    name="acquisition_date"
                                                    type="date"
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
                                                    htmlFor="acquisition-cost"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Acquisition cost
                                                </label>
                                                <Input
                                                    id="acquisition-cost"
                                                    name="acquisition_cost"
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    placeholder="e.g. 54999.00"
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
                                                    htmlFor="available-for-use-date"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Available-for-use date
                                                </label>
                                                <Input
                                                    id="available-for-use-date"
                                                    name="available_for_use_date"
                                                    type="date"
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Required for PPE. This is
                                                    the basis for starting
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
                                                    Residual value percentage
                                                </label>
                                                <Input
                                                    id="residual-value-percentage"
                                                    name="residual_value_percentage"
                                                    type="number"
                                                    min="5"
                                                    max="99.99"
                                                    step="0.01"
                                                    defaultValue="5"
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Applied only to PPE; COA's
                                                    standard starting value is
                                                    5%.
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
                                                    Residual value basis
                                                </label>
                                                <textarea
                                                    id="residual-value-basis"
                                                    name="residual_value_basis"
                                                    className={`${selectClass} min-h-20 py-2`}
                                                    defaultValue="COA default residual value of 5%."
                                                    maxLength={1000}
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
                                                    Useful life in months
                                                </label>
                                                <Input
                                                    id="useful-life"
                                                    name="depreciation_useful_life_months"
                                                    type="number"
                                                    min="1"
                                                    max="1200"
                                                    defaultValue="60"
                                                    placeholder="e.g. 60"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.depreciation_useful_life_months
                                                    }
                                                />
                                            </div>
                                            <div className="rounded-lg border bg-muted/40 p-3 text-sm md:col-span-2">
                                                The system classifies an item
                                                automatically: PHP{' '}
                                                {capitalizationThreshold.toLocaleString()}{' '}
                                                and above becomes PPE with PAR
                                                and depreciation; lower-cost
                                                durable property becomes
                                                semi-expendable with ICS and no
                                                depreciation.
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
                                                <select
                                                    id="asset-lifecycle"
                                                    name="lifecycle_status"
                                                    className={selectClass}
                                                    defaultValue="active"
                                                    required
                                                >
                                                    {assetStateOptions.lifecycles.map(
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
                                                </select>
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
                                                <select
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
                                                </select>
                                                <InputError
                                                    message={
                                                        errors.condition_status
                                                    }
                                                />
                                            </div>
                                            <Button
                                                disabled={processing}
                                                className="md:col-span-2"
                                            >
                                                {processing
                                                    ? 'Registering…'
                                                    : 'Register asset'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
                <Tabs
                    value={activeRegistryTab}
                    onValueChange={changeRegistryTab}
                >
                    <TabsList aria-label="Asset accounting registries">
                        {registryTabs.map((tab) => (
                            <TabsTrigger key={tab.value} value={tab.value}>
                                {tab.value === 'ppe'
                                    ? `${tab.label} - PHP ${capitalizationThreshold.toLocaleString()}+`
                                    : tab.value === 'semi_expendable'
                                      ? `${tab.label} - below PHP ${capitalizationThreshold.toLocaleString()}`
                                      : tab.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {showingArchived
                                ? 'Archived assets'
                                : 'Asset registry'}
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
                            action={index()}
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
                                    {filters.accounting_classification && (
                                        <input
                                            type="hidden"
                                            name="accounting_classification"
                                            value={
                                                filters.accounting_classification
                                            }
                                        />
                                    )}
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="asset-lifecycle-filter"
                                            className="text-sm font-medium"
                                        >
                                            Lifecycle
                                        </label>
                                        <select
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
                                        </select>
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
                                        <select
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
                                        </select>
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
                                        <select
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
                                        </select>
                                        <InputError
                                            message={errors.custody_status}
                                        />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="asset-records-filter"
                                            className="text-sm font-medium"
                                        >
                                            Records
                                        </label>
                                        <select
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
                                        </select>
                                        <InputError message={errors.records} />
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
                                            Book value
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
                                                    <div>
                                                        PHP{' '}
                                                        {Number(
                                                            asset.book_value,
                                                        ).toLocaleString()}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {asset.is_depreciable
                                                            ? `Residual ${asset.residual_value_percentage}%`
                                                            : 'No depreciation'}
                                                    </div>
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
    breadcrumbs: [{ title: 'Property and equipment', href: index() }],
};
