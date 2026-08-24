import { Form, Head, router } from '@inertiajs/react';
import {
    Handshake,
    Plus,
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
import { Input } from '@/components/ui/input';
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
    returnMethod,
    store,
    unassign,
    update_state,
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
}: {
    asset: InventoryAsset;
    options: AssetStateOptions;
}) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <SlidersHorizontal /> State
            </DialogTrigger>
            <DialogContent>
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

function CustodianDialog({
    asset,
    employees,
}: {
    asset: InventoryAsset;
    employees: HrisReference[];
}) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <UserCheck /> Assign
            </DialogTrigger>
            <DialogContent>
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
}: {
    asset: InventoryAsset;
    employees: HrisReference[];
}) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <Handshake /> Borrow
            </DialogTrigger>
            <DialogContent>
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

export default function AssetsIndex({
    assets,
    categories,
    employees,
    filters,
    assetStateOptions,
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
    };
    assetStateOptions: AssetStateOptions;
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();
    const { auth } = useAppPage().props;
    const canManageInventory = auth.permissions.manage_inventory;
    const showingArchived = filters.records === 'archived';
    const [selectedAsset, setSelectedAsset] = useState<InventoryAsset | null>(
        null,
    );

    return (
        <>
            <Head title="Property and Equipment" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Property and equipment
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Asset registry, custody history, borrowing, and
                            depreciation.
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
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="e.g. 54999.00"
                                                />
                                                <InputError
                                                    message={
                                                        errors.acquisition_cost
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
                            <table className="w-full min-w-[1050px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="pb-3">Property</th>
                                        <th className="pb-3">Asset</th>
                                        <th className="pb-3">
                                            Custodian / borrower
                                        </th>
                                        <th className="pb-3">Location</th>
                                        <th className="pb-3">Book value</th>
                                        <th className="pb-3">State</th>
                                        {canManageInventory && (
                                            <th className="pb-3 text-right">
                                                Actions
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {assets.data.map((asset) => (
                                        <Tooltip key={asset.inventory_asset_id}>
                                            <TooltipTrigger
                                                render={
                                                    <tr
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
                                                <td className="py-3">
                                                    <div className="font-mono text-xs">
                                                        {asset.property_number ??
                                                            'No property no.'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {asset.serial_number}
                                                    </div>
                                                </td>
                                                <td className="py-3">
                                                    <div className="font-medium">
                                                        {asset.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {asset.category?.name} ·{' '}
                                                        {[
                                                            asset.brand,
                                                            asset.model,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' ')}
                                                    </div>
                                                </td>
                                                <td className="py-3">
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
                                                </td>
                                                <td className="py-3 text-muted-foreground">
                                                    {asset.location ?? '—'}
                                                </td>
                                                <td className="py-3 text-right">
                                                    ₱
                                                    {Number(
                                                        asset.book_value,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="py-3">
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
                                                </td>
                                                {canManageInventory && (
                                                    <td className="py-3">
                                                        <div className="flex justify-end gap-2">
                                                            {showingArchived ? (
                                                                <Form
                                                                    action={restore(
                                                                        asset,
                                                                    )}
                                                                    options={{
                                                                        preserveScroll: true,
                                                                    }}
                                                                >
                                                                    {({
                                                                        processing,
                                                                    }) => (
                                                                        <Button
                                                                            size="sm"
                                                                            variant="outline"
                                                                            disabled={
                                                                                processing
                                                                            }
                                                                        >
                                                                            <RotateCcw />
                                                                            {processing
                                                                                ? 'Restoring…'
                                                                                : 'Restore'}
                                                                        </Button>
                                                                    )}
                                                                </Form>
                                                            ) : asset.custody_status ===
                                                              'borrowed' ? (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        router.visit(
                                                                            returnMethod(
                                                                                asset,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <RotateCcw />{' '}
                                                                    Return
                                                                </Button>
                                                            ) : asset.is_borrowable ? (
                                                                <BorrowDialog
                                                                    asset={
                                                                        asset
                                                                    }
                                                                    employees={
                                                                        employees
                                                                    }
                                                                />
                                                            ) : null}
                                                            {!showingArchived && (
                                                                <AssetStateDialog
                                                                    asset={
                                                                        asset
                                                                    }
                                                                    options={
                                                                        assetStateOptions
                                                                    }
                                                                />
                                                            )}
                                                            {!showingArchived && (
                                                                <>
                                                                    {asset.is_assignable && (
                                                                        <CustodianDialog
                                                                            asset={
                                                                                asset
                                                                            }
                                                                            employees={
                                                                                employees
                                                                            }
                                                                        />
                                                                    )}
                                                                    {asset.current_custodian && (
                                                                        <Button
                                                                            size="icon"
                                                                            variant="ghost"
                                                                            aria-label="Unassign custodian"
                                                                            onClick={() =>
                                                                                router.visit(
                                                                                    unassign(
                                                                                        asset,
                                                                                    ),
                                                                                )
                                                                            }
                                                                        >
                                                                            <UserMinus />
                                                                        </Button>
                                                                    )}
                                                                    <ArchiveRecordDialog
                                                                        action={destroy(
                                                                            asset,
                                                                        )}
                                                                        recordName={
                                                                            asset.name
                                                                        }
                                                                        recordType="asset"
                                                                        prerequisite="An asset must be returned and unassigned before it can be archived."
                                                                    />
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                )}
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Click for more info
                                            </TooltipContent>
                                        </Tooltip>
                                    ))}
                                    {assets.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={
                                                    canManageInventory ? 7 : 6
                                                }
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No assets found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
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
