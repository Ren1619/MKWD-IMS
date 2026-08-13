import { Form, Head, router } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Plus, Trash2 } from 'lucide-react';
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
import { useAppPage } from '@/hooks/use-app-page';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import {
    destroy,
    index,
    stock_in,
    stock_out,
    store,
} from '@/routes/inventory/items';
import type {
    HrisReference,
    Paginated,
    SeriesCategory,
} from '@/types/inventory';

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

type InventoryItem = {
    inventory_item_id: number;
    name: string;
    stock_number: string | null;
    unit_of_measure: string;
    quantity: number;
    price: string;
    status: string;
    series_category: SeriesCategory;
    accountable_reference: HrisReference | null;
    batches: { inventory_item_batch_id: number; quantity_remaining: number }[];
};

function StockInDialog({ item }: { item: InventoryItem }) {
    return (
        <Dialog>
            <DialogTrigger render={<Button size="sm" variant="outline" />}>
                <ArrowDownToLine /> Receive
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Receive {item.name}</DialogTitle>
                    <DialogDescription>
                        Add a new FIFO stock batch.
                    </DialogDescription>
                </DialogHeader>
                <Form action={stock_in(item)} className="grid gap-4">
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
                            <Button disabled={processing}>
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

export default function ItemsIndex({
    items,
    seriesCategories,
    references,
    filters,
}: {
    items: Paginated<InventoryItem>;
    seriesCategories: SeriesCategory[];
    references: HrisReference[];
    filters: { search?: string; status?: string };
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();
    const { auth } = useAppPage().props;
    const canManageInventory = auth.permissions.manage_inventory;

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
                                                    htmlFor="unit-price"
                                                    className="mb-1.5 block text-sm font-medium"
                                                >
                                                    Unit price
                                                </label>
                                                <Input
                                                    id="unit-price"
                                                    name="price"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue="0"
                                                    placeholder="e.g. 245.50"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.price}
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
                        <CardTitle>Stock ledger</CardTitle>
                        <CardDescription>
                            {items.total} item types.
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
                                </>
                            )}
                        </Form>
                        <div className="overflow-x-auto rounded-lg border border-border/70">
                            <table className="w-full min-w-[900px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="pb-3">Stock no.</th>
                                        <th className="pb-3">Item</th>
                                        <th className="pb-3">Category</th>
                                        <th className="pb-3 text-right">
                                            On hand
                                        </th>
                                        <th className="pb-3 text-right">
                                            Unit price
                                        </th>
                                        <th className="pb-3">Status</th>
                                        {canManageInventory && (
                                            <th className="pb-3 text-right">
                                                Actions
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {items.data.map((item) => (
                                        <tr key={item.inventory_item_id}>
                                            <td className="py-3 font-mono text-xs">
                                                {item.stock_number ?? '—'}
                                            </td>
                                            <td className="py-3">
                                                <div className="font-medium">
                                                    {item.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {item.accountable_reference
                                                        ?.name ??
                                                        'No accountable person'}
                                                </div>
                                            </td>
                                            <td className="py-3 text-muted-foreground">
                                                {
                                                    item.series_category
                                                        ?.class_category
                                                        ?.major_category?.name
                                                }{' '}
                                                / {item.series_category?.name}
                                            </td>
                                            <td className="py-3 text-right font-semibold">
                                                {item.quantity}{' '}
                                                {item.unit_of_measure}
                                            </td>
                                            <td className="py-3 text-right">
                                                ₱
                                                {Number(
                                                    item.price,
                                                ).toLocaleString()}
                                            </td>
                                            <td className="py-3">
                                                <Badge
                                                    variant={
                                                        item.status === 'active'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {item.status}
                                                </Badge>
                                            </td>
                                            {canManageInventory && (
                                                <td className="py-3">
                                                    <div className="flex justify-end gap-2">
                                                        <StockInDialog
                                                            item={item}
                                                        />
                                                        <StockOutDialog
                                                            item={item}
                                                            references={
                                                                references
                                                            }
                                                        />
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            aria-label={`Delete ${item.name}`}
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        `Delete ${item.name} and its stock history?`,
                                                                    )
                                                                ) {
                                                                    router.visit(
                                                                        destroy(
                                                                            item,
                                                                        ),
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                    {items.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={
                                                    canManageInventory ? 7 : 6
                                                }
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No inventory items found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <DataPagination links={items.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [{ title: 'Consumable inventory', href: index() }],
};
