import { Form, Head } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Plus,
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-react';
import { useState } from 'react';
import { ArchiveRecordDialog } from '@/components/archive-record-dialog';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { InventoryItemDetailsDialog } from '@/components/inventory-item-details-dialog';
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
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppPage } from '@/hooks/use-app-page';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import {
    destroy,
    index,
    restore,
    stock_in,
    stock_out,
    store,
    update_replenishment,
} from '@/routes/inventory/items';
import type {
    ClassCategory,
    HrisReference,
    InventoryItem,
    Paginated,
    SeriesCategory,
} from '@/types/inventory';

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

function isInteractiveRowTarget(target: EventTarget | null): boolean {
    return (
        target instanceof Element &&
        target.closest('button, a, input, select, textarea') !== null
    );
}

function StockInDialog({ item }: { item: InventoryItem }) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <ArrowDownToLine /> Receive
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Receive {item.name}</DialogTitle>
                    <DialogDescription>
                        Record the cost and traceability details for this FIFO
                        batch.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={stock_in(item)}
                    resetOnSuccess
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`stock-in-quantity-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Quantity received
                                </label>
                                <Input
                                    id={`stock-in-quantity-${item.inventory_item_id}`}
                                    name="quantity"
                                    type="number"
                                    min="1"
                                    placeholder="e.g. 25"
                                    required
                                />
                                <InputError message={errors.quantity} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`stock-in-unit-cost-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Unit cost
                                </label>
                                <Input
                                    id={`stock-in-unit-cost-${item.inventory_item_id}`}
                                    name="unit_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="e.g. 245.50"
                                    required
                                />
                                <InputError message={errors.unit_cost} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`received-at-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Date received
                                </label>
                                <Input
                                    id={`received-at-${item.inventory_item_id}`}
                                    name="received_at"
                                    type="date"
                                    defaultValue={new Date()
                                        .toISOString()
                                        .slice(0, 10)}
                                />
                                <InputError message={errors.received_at} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`stock-in-expiration-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Expiration date
                                </label>
                                <Input
                                    id={`stock-in-expiration-${item.inventory_item_id}`}
                                    name="expiration_date"
                                    type="date"
                                />
                                <InputError message={errors.expiration_date} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`stock-in-source-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Supplier / source
                                </label>
                                <Input
                                    id={`stock-in-source-${item.inventory_item_id}`}
                                    name="source"
                                    placeholder="e.g. ABC Office Supplies"
                                    maxLength={255}
                                />
                                <InputError message={errors.source} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`stock-in-reference-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Receipt reference
                                </label>
                                <Input
                                    id={`stock-in-reference-${item.inventory_item_id}`}
                                    name="reference_no"
                                    placeholder="e.g. DR-2026-001"
                                    maxLength={100}
                                />
                                <InputError message={errors.reference_no} />
                            </div>
                            <div className="sm:col-span-2">
                                <label
                                    htmlFor={`stock-in-notes-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Batch notes
                                </label>
                                <textarea
                                    id={`stock-in-notes-${item.inventory_item_id}`}
                                    name="batch_notes"
                                    rows={3}
                                    maxLength={2000}
                                    className={`${selectClass} h-auto py-2`}
                                    placeholder="Optional receipt or batch details"
                                />
                                <InputError message={errors.batch_notes} />
                            </div>
                            <Button
                                disabled={processing}
                                className="sm:col-span-2"
                            >
                                {processing ? 'Receiving…' : 'Receive stock'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StockOutDialog({
    item,
    references,
}: {
    item: InventoryItem;
    references: HrisReference[];
}) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <ArrowUpFromLine /> Release
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Release {item.name}</DialogTitle>
                    <DialogDescription>
                        {item.quantity} {item.unit_of_measure} currently
                        available.
                    </DialogDescription>
                </DialogHeader>
                <Form action={stock_out(item)} className="grid gap-4">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`stock-out-quantity-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Quantity released
                                </label>
                                <Input
                                    id={`stock-out-quantity-${item.inventory_item_id}`}
                                    name="quantity"
                                    type="number"
                                    min="1"
                                    max={item.quantity}
                                    placeholder="e.g. 5"
                                    required
                                />
                                <InputError message={errors.quantity} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`recipient-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Recipient
                                </label>
                                <select
                                    id={`recipient-${item.inventory_item_id}`}
                                    name="recipient_reference_id"
                                    className={selectClass}
                                    defaultValue=""
                                >
                                    <option value="">
                                        External/manual recipient
                                    </option>
                                    {references.map((reference) => (
                                        <option
                                            key={reference.id}
                                            value={reference.id}
                                        >
                                            {reference.name} ({reference.type})
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.recipient_reference_id}
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor={`recipient-name-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Manual recipient name
                                </label>
                                <Input
                                    id={`recipient-name-${item.inventory_item_id}`}
                                    name="recipient_name"
                                    placeholder="e.g. Juan Dela Cruz"
                                    maxLength={255}
                                />
                                <InputError message={errors.recipient_name} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label
                                        htmlFor={`ris-no-${item.inventory_item_id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        RIS number
                                    </label>
                                    <Input
                                        id={`ris-no-${item.inventory_item_id}`}
                                        name="ris_no"
                                        placeholder="e.g. RIS-2026-001"
                                        maxLength={100}
                                    />
                                    <InputError message={errors.ris_no} />
                                </div>
                                <div>
                                    <label
                                        htmlFor={`responsibility-center-${item.inventory_item_id}`}
                                        className="mb-1.5 block text-sm font-medium"
                                    >
                                        Responsibility center
                                    </label>
                                    <Input
                                        id={`responsibility-center-${item.inventory_item_id}`}
                                        name="responsibility_center_code"
                                        placeholder="e.g. RC-101"
                                        maxLength={100}
                                    />
                                    <InputError
                                        message={
                                            errors.responsibility_center_code
                                        }
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    htmlFor={`stocked-out-at-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Release date
                                </label>
                                <Input
                                    id={`stocked-out-at-${item.inventory_item_id}`}
                                    name="stocked_out_at"
                                    type="date"
                                    defaultValue={new Date()
                                        .toISOString()
                                        .slice(0, 10)}
                                />
                                <InputError message={errors.stocked_out_at} />
                            </div>
                            <Button disabled={processing}>
                                {processing ? 'Releasing…' : 'Release stock'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ReplenishmentDialog({ item }: { item: InventoryItem }) {
    return (
        <Dialog>
            <DialogTrigger
                render={
                    <Button
                        size="icon"
                        variant="ghost"
                        aria-label={`Configure replenishment for ${item.name}`}
                    />
                }
            >
                <SlidersHorizontal />
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Replenishment settings</DialogTitle>
                    <DialogDescription>
                        Set when {item.name} should be flagged and the suggested
                        quantity to order.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={update_replenishment(item)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label
                                    htmlFor={`reorder-point-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Reorder point
                                </label>
                                <Input
                                    id={`reorder-point-${item.inventory_item_id}`}
                                    name="reorder_point"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    defaultValue={item.reorder_point}
                                    required
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Alert when on-hand stock reaches this level.
                                </p>
                                <InputError message={errors.reorder_point} />
                            </div>
                            <div>
                                <label
                                    htmlFor={`reorder-quantity-${item.inventory_item_id}`}
                                    className="mb-1.5 block text-sm font-medium"
                                >
                                    Suggested order quantity
                                </label>
                                <Input
                                    id={`reorder-quantity-${item.inventory_item_id}`}
                                    name="reorder_quantity"
                                    type="number"
                                    min="1"
                                    max="1000000"
                                    defaultValue={
                                        item.reorder_quantity ?? undefined
                                    }
                                    placeholder="Optional"
                                />
                                <InputError message={errors.reorder_quantity} />
                            </div>
                            <Button disabled={processing}>
                                {processing ? 'Saving…' : 'Save settings'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ItemsIndex({
    items,
    seriesCategories,
    classCategories,
    references,
    filters,
}: {
    items: Paginated<InventoryItem>;
    seriesCategories: SeriesCategory[];
    classCategories: ClassCategory[];
    references: HrisReference[];
    filters: {
        search?: string;
        status?: string;
        records?: string;
        alert?: string;
        class_category_id?: string;
    };
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();
    const { auth } = useAppPage().props;
    const canManageInventory = auth.permissions.manage_inventory;
    const showingArchived = filters.records === 'archived';
    const [selectedItem, setSelectedItem] = useState<InventoryItem | null>(
        null,
    );

    return (
        <>
            <Head title="Consumable Inventory" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Consumable inventory
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            FIFO stock batches and accountable releases.
                        </p>
                    </div>
                    {canManageInventory && (
                        <Dialog>
                            <DialogTrigger render={<Button />}>
                                <Plus /> New item
                            </DialogTrigger>
                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Create inventory item
                                    </DialogTitle>
                                    <DialogDescription>
                                        Set the opening balance; it will become
                                        the first stock batch.
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
                                                    htmlFor="item-name"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Item name
                                                </label>
                                                <Input
                                                    id="item-name"
                                                    name="name"
                                                    placeholder="e.g. A4 Copy Paper"
                                                    maxLength={255}
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="stock-number"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Stock number
                                                </label>
                                                <Input
                                                    id="stock-number"
                                                    name="stock_number"
                                                    placeholder="e.g. PAPER-001"
                                                    maxLength={100}
                                                />
                                                <InputError
                                                    message={
                                                        errors.stock_number
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="series-category"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Series category
                                                </label>
                                                <select
                                                    id="series-category"
                                                    name="series_category_id"
                                                    className={selectClass}
                                                    defaultValue=""
                                                    required
                                                >
                                                    <option value="" disabled>
                                                        Series category
                                                    </option>
                                                    {seriesCategories.map(
                                                        (category) => (
                                                            <option
                                                                key={
                                                                    category.inv_series_cat_id
                                                                }
                                                                value={
                                                                    category.inv_series_cat_id
                                                                }
                                                            >
                                                                {
                                                                    category
                                                                        .class_category
                                                                        ?.major_category
                                                                        ?.name
                                                                }{' '}
                                                                /{' '}
                                                                {
                                                                    category
                                                                        .class_category
                                                                        ?.name
                                                                }{' '}
                                                                /{' '}
                                                                {category.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <InputError
                                                    message={
                                                        errors.series_category_id
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="opening-quantity"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Opening quantity
                                                </label>
                                                <Input
                                                    id="opening-quantity"
                                                    name="quantity"
                                                    type="number"
                                                    min="0"
                                                    defaultValue="0"
                                                    placeholder="e.g. 25"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.quantity}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="reorder-point"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Reorder point
                                                </label>
                                                <Input
                                                    id="reorder-point"
                                                    name="reorder_point"
                                                    type="number"
                                                    min="0"
                                                    max="1000000"
                                                    defaultValue="10"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.reorder_point
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="reorder-quantity"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Suggested order quantity
                                                </label>
                                                <Input
                                                    id="reorder-quantity"
                                                    name="reorder_quantity"
                                                    type="number"
                                                    min="1"
                                                    max="1000000"
                                                    placeholder="Optional"
                                                />
                                                <InputError
                                                    message={
                                                        errors.reorder_quantity
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="opening-unit-cost"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Opening unit cost
                                                </label>
                                                <Input
                                                    id="opening-unit-cost"
                                                    name="unit_cost"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue="0"
                                                    placeholder="e.g. 245.50"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.unit_cost}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="item-unit"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Unit of measure
                                                </label>
                                                <Input
                                                    id="item-unit"
                                                    name="unit_of_measure"
                                                    defaultValue="pc"
                                                    placeholder="e.g. ream"
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
                                                    htmlFor="uacs-code"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    UACS object code
                                                </label>
                                                <Input
                                                    id="uacs-code"
                                                    name="uacs_object_code"
                                                    placeholder="e.g. 50203010-02"
                                                    maxLength={50}
                                                />
                                                <InputError
                                                    message={
                                                        errors.uacs_object_code
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="expiration-date"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Expiration date
                                                </label>
                                                <Input
                                                    id="expiration-date"
                                                    name="expiration_date"
                                                    type="date"
                                                />
                                                <InputError
                                                    message={
                                                        errors.expiration_date
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="opening-received-at"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Date received
                                                </label>
                                                <Input
                                                    id="opening-received-at"
                                                    name="received_at"
                                                    type="date"
                                                    defaultValue={new Date()
                                                        .toISOString()
                                                        .slice(0, 10)}
                                                />
                                                <InputError
                                                    message={errors.received_at}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="item-status"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Status
                                                </label>
                                                <select
                                                    id="item-status"
                                                    name="status"
                                                    className={selectClass}
                                                    defaultValue="active"
                                                    required
                                                >
                                                    <option value="active">
                                                        Active
                                                    </option>
                                                    <option value="inactive">
                                                        Inactive
                                                    </option>
                                                    <option value="disposed">
                                                        Disposed
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.status}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="opening-source"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Supplier / source
                                                </label>
                                                <Input
                                                    id="opening-source"
                                                    name="source"
                                                    placeholder="e.g. ABC Office Supplies"
                                                    maxLength={255}
                                                />
                                                <InputError
                                                    message={errors.source}
                                                />
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor="opening-reference"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Receipt reference
                                                </label>
                                                <Input
                                                    id="opening-reference"
                                                    name="reference_no"
                                                    placeholder="e.g. DR-2026-001"
                                                    maxLength={100}
                                                />
                                                <InputError
                                                    message={
                                                        errors.reference_no
                                                    }
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label
                                                    htmlFor="opening-batch-notes"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Opening batch notes
                                                </label>
                                                <textarea
                                                    id="opening-batch-notes"
                                                    name="batch_notes"
                                                    rows={3}
                                                    maxLength={2000}
                                                    className={`${selectClass} h-auto py-2`}
                                                    placeholder="Optional receipt or batch details"
                                                />
                                                <InputError
                                                    message={errors.batch_notes}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <label
                                                    htmlFor="accountable-person"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Accountable person
                                                </label>
                                                <select
                                                    id="accountable-person"
                                                    name="accountable_reference_id"
                                                    className={selectClass}
                                                    defaultValue=""
                                                >
                                                    <option value="">
                                                        No accountable person
                                                    </option>
                                                    {references
                                                        .filter(
                                                            (reference) =>
                                                                reference.type ===
                                                                'employee',
                                                        )
                                                        .map((reference) => (
                                                            <option
                                                                key={
                                                                    reference.id
                                                                }
                                                                value={
                                                                    reference.id
                                                                }
                                                            >
                                                                {reference.name}
                                                            </option>
                                                        ))}
                                                </select>
                                                <InputError
                                                    message={
                                                        errors.accountable_reference_id
                                                    }
                                                />
                                            </div>
                                            <Button
                                                disabled={processing}
                                                className="md:col-span-2"
                                            >
                                                {processing
                                                    ? 'Creating…'
                                                    : 'Create item'}
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
                                ? 'Archived stock records'
                                : 'Stock ledger'}
                        </CardTitle>
                        <CardDescription>
                            {items.total}{' '}
                            {showingArchived
                                ? 'archived item types.'
                                : 'item types.'}
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
                                            htmlFor="item-search"
                                            className="text-sm font-medium"
                                        >
                                            Search inventory
                                        </label>
                                        <Input
                                            id="item-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="e.g. A4 Copy Paper or PAPER-001"
                                            maxLength={100}
                                            onChange={submitAfterDelay}
                                        />
                                        <InputError message={errors.search} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="item-class-category-filter"
                                            className="text-sm font-medium"
                                        >
                                            Class category
                                        </label>
                                        <select
                                            id="item-class-category-filter"
                                            name="class_category_id"
                                            defaultValue={
                                                filters.class_category_id ?? ''
                                            }
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">
                                                All classes
                                            </option>
                                            {classCategories.map((category) => (
                                                <option
                                                    key={
                                                        category.inv_class_cat_id
                                                    }
                                                    value={
                                                        category.inv_class_cat_id
                                                    }
                                                >
                                                    {category.name}
                                                    {category.major_category
                                                        ? ` — ${category.major_category.name}`
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.class_category_id}
                                        />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="item-status-filter"
                                            className="text-sm font-medium"
                                        >
                                            Status
                                        </label>
                                        <select
                                            id="item-status-filter"
                                            name="status"
                                            defaultValue={filters.status ?? ''}
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">
                                                All statuses
                                            </option>
                                            <option value="active">
                                                Active
                                            </option>
                                            <option value="inactive">
                                                Inactive
                                            </option>
                                            <option value="disposed">
                                                Disposed
                                            </option>
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="item-alert-filter"
                                            className="text-sm font-medium"
                                        >
                                            Attention
                                        </label>
                                        <select
                                            id="item-alert-filter"
                                            name="alert"
                                            defaultValue={filters.alert ?? ''}
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">All items</option>
                                            <option value="low_stock">
                                                Low stock
                                            </option>
                                            <option value="expiring">
                                                Expiring within 30 days
                                            </option>
                                            <option value="expired">
                                                Expired stock
                                            </option>
                                        </select>
                                        <InputError message={errors.alert} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="item-records-filter"
                                            className="text-sm font-medium"
                                        >
                                            Records
                                        </label>
                                        <select
                                            id="item-records-filter"
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
                            <Table className="w-full min-w-[900px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <TableRow>
                                        <TableHead className="pb-3">
                                            Stock no.
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Item
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Category
                                        </TableHead>
                                        <TableHead className="pb-3 text-right">
                                            On hand
                                        </TableHead>
                                        <TableHead className="pb-3 text-right">
                                            Stock value
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Next expiry
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Status
                                        </TableHead>
                                        {canManageInventory && (
                                            <TableHead className="pb-3 text-right">
                                                Actions
                                            </TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {items.data.map((item) => (
                                        <Tooltip key={item.inventory_item_id}>
                                            <TooltipTrigger
                                                render={
                                                    <TableRow
                                                        role="button"
                                                        tabIndex={0}
                                                        aria-haspopup="dialog"
                                                        aria-label={`View receipt and release history for ${item.name}`}
                                                        className="cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                                        onClick={(event) => {
                                                            if (
                                                                !isInteractiveRowTarget(
                                                                    event.target,
                                                                )
                                                            ) {
                                                                setSelectedItem(
                                                                    item,
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
                                                                setSelectedItem(
                                                                    item,
                                                                );
                                                            }
                                                        }}
                                                    />
                                                }
                                            >
                                                <TableCell className="py-3 font-mono text-xs">
                                                    {item.stock_number ?? '—'}
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <div className="font-medium">
                                                        {item.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {item
                                                            .accountable_reference
                                                            ?.name ??
                                                            'No accountable person'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-3 text-muted-foreground">
                                                    {
                                                        item.series_category
                                                            ?.class_category
                                                            ?.major_category
                                                            ?.name
                                                    }{' '}
                                                    /{' '}
                                                    {item.series_category?.name}
                                                </TableCell>
                                                <TableCell className="py-3 text-right">
                                                    <div className="font-semibold">
                                                        {item.quantity}{' '}
                                                        {item.unit_of_measure}
                                                    </div>
                                                    {item.is_low_stock && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="mt-1"
                                                        >
                                                            Reorder at{' '}
                                                            {item.reorder_point}
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-3 text-right">
                                                    {currency.format(
                                                        Number(
                                                            item.inventory_value,
                                                        ),
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-3 text-muted-foreground">
                                                    <div>
                                                        {item.next_expiration_date
                                                            ? new Date(
                                                                  `${item.next_expiration_date}T00:00:00`,
                                                              ).toLocaleDateString()
                                                            : 'No expiry'}
                                                    </div>
                                                    {item.expiration_status ===
                                                        'expired' && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="mt-1"
                                                        >
                                                            Expired
                                                        </Badge>
                                                    )}
                                                    {item.expiration_status ===
                                                        'expiring' && (
                                                        <Badge
                                                            variant="outline"
                                                            className="mt-1 border-amber-500/50 text-amber-700 dark:text-amber-400"
                                                        >
                                                            Expiring soon
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <Badge
                                                        variant={
                                                            item.status ===
                                                            'active'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {item.status}
                                                    </Badge>
                                                </TableCell>
                                                {canManageInventory && (
                                                    <TableCell className="py-3">
                                                        <div className="flex justify-end gap-2">
                                                            {showingArchived ? (
                                                                <Form
                                                                    action={restore(
                                                                        item,
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
                                                            ) : (
                                                                <>
                                                                    <StockInDialog
                                                                        item={
                                                                            item
                                                                        }
                                                                    />
                                                                    <StockOutDialog
                                                                        item={
                                                                            item
                                                                        }
                                                                        references={
                                                                            references
                                                                        }
                                                                    />
                                                                    <ReplenishmentDialog
                                                                        item={
                                                                            item
                                                                        }
                                                                    />
                                                                    <ArchiveRecordDialog
                                                                        action={destroy(
                                                                            item,
                                                                        )}
                                                                        recordName={
                                                                            item.name
                                                                        }
                                                                        recordType="inventory item"
                                                                        prerequisite="An item must have zero stock before it can be archived."
                                                                    />
                                                                </>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                )}
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Click for receipt and release
                                                history
                                            </TooltipContent>
                                        </Tooltip>
                                    ))}
                                    {items.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={
                                                    canManageInventory ? 8 : 7
                                                }
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No inventory items found.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataPagination links={items.links} />
                    </CardContent>
                </Card>

                {selectedItem && (
                    <InventoryItemDetailsDialog
                        key={selectedItem.inventory_item_id}
                        item={selectedItem}
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setSelectedItem(null);
                            }
                        }}
                    />
                )}
            </div>
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [{ title: 'Consumable inventory', href: index() }],
};
