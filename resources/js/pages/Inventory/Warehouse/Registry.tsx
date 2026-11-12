import { Head, Link, router, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { RecordChangeBadge } from '@/components/record-change-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogClose,
    DialogFooter,
    DialogTrigger,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as storageIndex } from '@/routes/inventory/warehouse';
import {
    index,
    store,
    update,
    destroy,
} from '@/routes/inventory/warehouse/registry';
import type { Paginated } from '@/types/inventory';
import type { Warehouse } from './types';

function WarehouseForm({
    warehouse,
    close,
}: {
    warehouse: Warehouse | null;
    close: () => void;
}) {
    const form = useForm({
        name: warehouse?.name ?? '',
        address: warehouse?.address ?? '',
        description: warehouse?.description ?? '',
    });

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {warehouse ? 'Edit stockroom' : 'Add stockroom'}
                    </DialogTitle>
                    <DialogDescription>
                        Register the stockroom name and location used for batch
                        storage.
                    </DialogDescription>
                </DialogHeader>
                <form
                    noValidate
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (warehouse) {
                            form.patch(update.url(warehouse.id), {
                                onSuccess: close,
                            });
                        } else {
                            form.post(store.url(), { onSuccess: close });
                        }
                    }}
                >
                    {(['name', 'address'] as const).map((field) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={`warehouse-${field}`}>
                                {field === 'name'
                                    ? 'Stockroom name'
                                    : 'Location / Address (optional)'}
                            </Label>
                            <Input
                                id={`warehouse-${field}`}
                                value={form.data[field]}
                                aria-invalid={!!form.errors[field]}
                                maxLength={field === 'name' ? 150 : 500}
                                onChange={(event) =>
                                    form.setData(field, event.target.value)
                                }
                            />
                            <InputError message={form.errors[field]} />
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <Label htmlFor="warehouse-description">
                            Description (optional)
                        </Label>
                        <textarea
                            id="warehouse-description"
                            className="min-h-24 rounded-md border bg-background p-3 text-sm"
                            maxLength={3000}
                            value={form.data.description}
                            aria-invalid={!!form.errors.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={close}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving...' : 'Save stockroom'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteWarehouse({
    warehouse,
    close,
}: {
    warehouse: Warehouse;
    close: () => void;
}) {
    const form = useForm({ warehouse: '' });

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {warehouse.name}?</DialogTitle>
                    <DialogDescription>
                        This removes the stockroom from the registry and
                        placement choices. Storage history is retained.
                        Stockrooms holding stock must be emptied first.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.delete(destroy.url(warehouse.id), {
                            onSuccess: close,
                        });
                    }}
                    className="grid gap-4"
                >
                    {Object.entries(form.errors).map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={close}>
                            Keep stockroom
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing
                                ? 'Deleting...'
                                : 'Delete stockroom'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RegistryContainer({
    embedded,
    children,
}: {
    embedded: boolean;
    children: React.ReactNode;
}) {
    return embedded ? (
        <div className="grid gap-4">{children}</div>
    ) : (
        <PageContainer>{children}</PageContainer>
    );
}

export default function WarehouseRegistry({
    warehouses,
    filters,
    embedded = false,
    navigate,
    refresh,
}: {
    warehouses: Paginated<Warehouse>;
    filters: { search?: string };
    embedded?: boolean;
    navigate?: (url: string) => void;
    refresh?: () => void;
}) {
    const [editing, setEditing] = useState<Warehouse | null | undefined>(
        undefined,
    );
    const [deleting, setDeleting] = useState<Warehouse | null>(null);

    return (
        <>
            {!embedded && <Head title="Stockroom Management" />}
            <RegistryContainer embedded={embedded}>
                {!embedded && (
                    <Link
                        href={storageIndex()}
                        className="text-sm text-muted-foreground underline"
                    >
                        View stockroom storage
                    </Link>
                )}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Stockroom Management
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Manage stockrooms used for stock storage and batch
                            placement.
                        </p>
                    </div>
                    <Button onClick={() => setEditing(null)}>
                        Add stockroom
                    </Button>
                </div>
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const search = String(
                            new FormData(event.currentTarget).get('search') ??
                                '',
                        );

                        if (navigate) {
                            navigate(index.url({ query: { search } }));

                            return;
                        }

                        router.get(
                            index.url(),
                            {
                                search: String(
                                    new FormData(event.currentTarget).get(
                                        'search',
                                    ) ?? '',
                                ),
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <div className="grid min-w-0 flex-1 gap-2">
                        <Label htmlFor="registry-search">
                            Search stockrooms
                        </Label>
                        <Input
                            key={filters.search ?? ''}
                            id="registry-search"
                            name="search"
                            maxLength={150}
                            defaultValue={filters.search}
                            placeholder="Name or location / address"
                            className="h-11 w-full"
                        />
                    </div>
                    <Button type="submit">Search</Button>
                    <Button
                        variant="outline"
                        render={navigate ? undefined : <Link href={index()} />}
                        onClick={
                            navigate ? () => navigate(index.url()) : undefined
                        }
                    >
                        Reset
                    </Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Stockroom</TableHead>
                                <TableHead>Location / Address</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {warehouses.data.map((warehouse) => (
                                <TableRow key={warehouse.id}>
                                    <TableCell>
                                        <p className="font-medium">
                                            {warehouse.name}
                                        </p>
                                        <RecordChangeBadge
                                            collection="warehouses"
                                            record={warehouse}
                                        />
                                        <p className="max-w-sm text-sm whitespace-normal text-muted-foreground">
                                            {warehouse.description}
                                        </p>
                                    </TableCell>
                                    <TableCell className="max-w-sm whitespace-normal">
                                        {warehouse.address || 'Not specified'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setEditing(warehouse)
                                                }
                                                aria-label={`Edit ${warehouse.name}`}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() =>
                                                    setDeleting(warehouse)
                                                }
                                                aria-label={`Delete ${warehouse.name}`}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {warehouses.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-12 text-center text-muted-foreground"
                                    >
                                        {filters.search
                                            ? 'No stockrooms match your search.'
                                            : 'No stockrooms registered. Add a stockroom to start recording storage locations.'}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <DataPagination
                    links={warehouses.links}
                    onNavigate={navigate}
                />
            </RegistryContainer>
            {editing !== undefined && (
                <WarehouseForm
                    key={editing?.id ?? 'new'}
                    warehouse={editing}
                    close={() => {
                        setEditing(undefined);
                        refresh?.();
                    }}
                />
            )}
            {deleting && (
                <DeleteWarehouse
                    warehouse={deleting}
                    close={() => {
                        setDeleting(null);
                        refresh?.();
                    }}
                />
            )}
        </>
    );
}
WarehouseRegistry.layout = {
    breadcrumbs: [{ title: 'Stockroom Management', href: index() }],
};

export function ManageStockroomsDialog() {
    const [open, setOpen] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [url, setUrl] = useState(index.url());
    const request = useHttp<
        Record<string, never>,
        { warehouses: Paginated<Warehouse>; filters: { search?: string } }
    >({});

    async function load(nextUrl: string) {
        setUrl(nextUrl);
        setLoadError(false);

        try {
            await request.get(nextUrl);
        } catch {
            setLoadError(true);
        }
    }

    return (
        <Dialog
            open={open}
            disablePointerDismissal
            onOpenChange={(nextOpen, details) => {
                if (!nextOpen && details.reason !== 'close-press') {
                    details.cancel();

                    return;
                }

                setOpen(nextOpen);

                if (nextOpen) {
                    void load(index.url());
                }
            }}
        >
            <DialogTrigger render={<Button variant="outline" />}>
                Manage Stockrooms
            </DialogTrigger>
            <DialogContent
                showCloseButton={false}
                className="flex max-h-[90dvh] flex-col sm:max-w-4xl"
            >
                <DialogHeader>
                    <DialogTitle>Manage Stockrooms</DialogTitle>
                    <DialogDescription>
                        Search, add, edit, and delete stockrooms used for batch
                        storage.
                    </DialogDescription>
                </DialogHeader>
                <div
                    className="min-h-0 overflow-y-auto"
                    aria-busy={request.processing}
                >
                    {loadError ? (
                        <div role="alert" className="grid gap-3">
                            <p>Unable to load stockrooms. Please try again.</p>
                            <Button
                                variant="outline"
                                onClick={() => void load(url)}
                            >
                                Retry
                            </Button>
                        </div>
                    ) : request.response ? (
                        <WarehouseRegistry
                            {...request.response}
                            embedded
                            navigate={(nextUrl) => void load(nextUrl)}
                            refresh={() => void load(url)}
                        />
                    ) : (
                        <p
                            role="status"
                            className="animate-pulse py-8 text-center text-muted-foreground"
                        >
                            Loading stockrooms...
                        </p>
                    )}
                </div>
                <DialogFooter>
                    <DialogClose render={<Button variant="outline" />}>
                        Close
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
