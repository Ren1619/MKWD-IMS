import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/inventory/returns';
import type { HrisReference, Paginated } from '@/types/inventory';
import ReturnReasonCombobox from './ReturnReasonCombobox';
import RmsFormView from './RmsFormView';
import { money, todayInManila } from './types';
import type { ReturnSource } from './types';

type CatalogItem = {
    inventory_item_id: number;
    name: string;
    stock_number: string;
    unit_of_measure: string;
};
type InputLine = {
    source_allocation_id: number | null;
    inventory_item_id: number | null;
    quantity: string;
    unit_cost: string;
    valuation_basis: string;
    expiration_date: string;
    display_name: string;
    display_uom: string;
    display_recipient: string;
    issue_reference: string;
    display_reference: string;
    maximum: number | null;
};
const commonReturnReasons = [
    'Excess or unused materials',
    'Project or work completed',
    'Project or work cancelled',
    'Change in work requirements',
    'Incorrect materials issued',
    'Materials no longer needed by the department',
    'Damaged or defective materials',
];
export type PrepareRmsProps = {
    sources: Paginated<ReturnSource>;
    items: CatalogItem[];
    references: HrisReference[];
    filters: { search?: string; supply_request_id?: number | string };
    supplyRequest?: {
        id: number;
        reference: string;
        requester_name: string;
        office_name: string | null;
    } | null;
};

export default function PrepareRmsDialog({
    sources,
    items,
    references,
    filters,
    supplyRequest,
    onClose,
    onBrowse,
    sourceError,
    sourcesLoading = false,
}: PrepareRmsProps & {
    sourceError?: string | null;
    sourcesLoading?: boolean;
    onClose?: () => void;
    onBrowse?: (search: string, page: number) => void;
}) {
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [departmentEdited, setDepartmentEdited] = useState(false);
    const [catalogId, setCatalogId] = useState('');
    const form = useForm({
        idempotency_key: crypto.randomUUID(),
        supply_request_id: supplyRequest?.id ?? null,
        source_mode: 'linked' as 'linked' | 'legacy',
        return_date: todayInManila(),
        department_name: supplyRequest
            ? (sources.data.find((source) => source.department_name)
                  ?.department_name ??
              supplyRequest.office_name ??
              '')
            : '',
        returned_by_reference_id: '',
        returned_by_name: '',
        legacy_issue_reference: '',
        reason: '',
        lines: [] as InputLine[],
        attested: false,
    });
    const departmentName =
        supplyRequest && !departmentEdited && form.data.lines.length === 0
            ? (sources.data.find((source) => source.department_name)
                  ?.department_name ?? form.data.department_name)
            : form.data.department_name;
    const updateLine = (
        position: number,
        field: 'quantity' | 'unit_cost' | 'valuation_basis' | 'expiration_date',
        value: string,
    ) =>
        form.setData(
            'lines',
            form.data.lines.map((line, index) =>
                index === position ? { ...line, [field]: value } : line,
            ),
        );
    const toggleSource = (source: ReturnSource) => {
        const selected = form.data.lines.some(
            (line) => line.source_allocation_id === source.id,
        );
        form.setData(
            'lines',
            selected
                ? form.data.lines.filter(
                      (line) => line.source_allocation_id !== source.id,
                  )
                : [
                      ...form.data.lines,
                      {
                          source_allocation_id: source.id,
                          inventory_item_id: null,
                          quantity: '1',
                          unit_cost: source.unit_cost,
                          valuation_basis: '',
                          expiration_date:
                              source.expiration_date?.slice(0, 10) ?? '',
                          display_name: source.item_name,
                          display_uom: source.unit_of_measure,
                          display_recipient: source.recipient_name,
                          issue_reference: source.issue_reference,
                          display_reference: `${source.issue_reference} · Batch ${source.batch_number} · ${source.recipient_name}`,
                          maximum: source.available_quantity,
                      },
                  ],
        );

        if (
            !selected &&
            !departmentEdited &&
            form.data.lines.length === 0 &&
            source.department_name
        ) {
            form.setData('department_name', source.department_name);
        }
    };
    const browse = (page = 1) => {
        if (onBrowse) {
            onBrowse(search, page);

            return;
        }

        router.get(
            create.url(),
            { search, page, supply_request_id: filters.supply_request_id },
            { preserveState: true, preserveScroll: true },
        );
    };
    const closePreparation = () => {
        if (onClose) {
            onClose();

            return;
        }

        router.get(index.url(), {}, { preserveScroll: true });
    };

    return (
        <>
            <Dialog
                open
                disablePointerDismissal
                onOpenChange={(_open, details) => details.cancel()}
            >
                <DialogContent
                    showCloseButton={false}
                    className="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl"
                >
                    <DialogHeader className="shrink-0 border-b px-4 py-4 sm:px-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <DialogTitle className="text-lg">
                                Prepare RMS
                            </DialogTitle>
                            {supplyRequest && (
                                <span className="rounded-md bg-muted px-2 py-1 text-xs font-medium">
                                    {supplyRequest.reference}
                                </span>
                            )}
                        </div>
                        <DialogDescription>
                            {supplyRequest
                                ? `Requested by ${supplyRequest.requester_name}. `
                                : ''}
                            Stock updates after inspection and approval.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-5 sm:px-6">
                        <fieldset
                            disabled={form.processing}
                            className="flex min-w-0 flex-col gap-6 disabled:opacity-70"
                        >
                            {!supplyRequest && (
                                <div className="grid max-w-xl gap-2">
                                    <Label htmlFor="return-mode">
                                        Source of returned materials
                                    </Label>
                                    <FormSelect
                                        id="return-mode"
                                        value={form.data.source_mode}
                                        onChange={(event) => {
                                            form.setData(
                                                'source_mode',
                                                event.target.value as
                                                    'linked' | 'legacy',
                                            );
                                            form.setData('lines', []);
                                            form.clearErrors();
                                        }}
                                    >
                                        <option value="linked">
                                            Recorded issue batches
                                        </option>
                                        <option value="legacy">
                                            Older issue supported by external
                                            records
                                        </option>
                                    </FormSelect>
                                </div>
                            )}
                            {form.data.source_mode === 'linked' ? (
                                <section className="grid gap-4 rounded-lg border p-4">
                                    <h2 className="font-semibold">
                                        Select issued batches
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Choose batches for one accountable
                                        person.
                                    </p>
                                    {sourceError && (
                                        <div
                                            role="alert"
                                            className="rounded-lg border p-3"
                                        >
                                            {sourceError}
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="ml-3"
                                                onClick={() => browse()}
                                                disabled={sourcesLoading}
                                            >
                                                Retry
                                            </Button>
                                        </div>
                                    )}
                                    <form
                                        className="flex gap-2"
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            browse();
                                        }}
                                    >
                                        <Input
                                            className="min-w-0 flex-1"
                                            aria-label="Search source issues"
                                            placeholder="Search issue number, item, or recipient"
                                            maxLength={150}
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={sourcesLoading}
                                        >
                                            Search
                                        </Button>
                                    </form>
                                    {sourcesLoading ? (
                                        <div
                                            role="status"
                                            aria-label="Loading issued batches"
                                            className="space-y-3 py-2"
                                        >
                                            <span className="sr-only">
                                                Loading issued batches...
                                            </span>
                                            {[0, 1, 2].map((row) => (
                                                <div
                                                    key={row}
                                                    className="h-20 animate-pulse rounded-lg bg-muted motion-reduce:animate-none"
                                                />
                                            ))}
                                        </div>
                                    ) : sourceError &&
                                      sources.data.length === 0 ? null : (
                                        <>
                                            <div className="grid gap-2 md:hidden">
                                                {sources.data.map((source) => {
                                                    const selected =
                                                        form.data.lines.some(
                                                            (line) =>
                                                                line.source_allocation_id ===
                                                                source.id,
                                                        );

                                                    return (
                                                        <label
                                                            key={source.id}
                                                            className="flex items-start gap-3 rounded-lg border p-3 has-checked:border-primary has-checked:bg-primary/5"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                className="mt-1 size-4 shrink-0 accent-primary"
                                                                aria-label={`Select ${source.item_name}, ${source.issue_reference}, batch ${source.batch_number}`}
                                                                checked={
                                                                    selected
                                                                }
                                                                disabled={
                                                                    source.available_quantity ===
                                                                        0 ||
                                                                    (form.data
                                                                        .lines
                                                                        .length >=
                                                                        25 &&
                                                                        !selected)
                                                                }
                                                                onChange={() =>
                                                                    toggleSource(
                                                                        source,
                                                                    )
                                                                }
                                                            />
                                                            <span className="min-w-0 flex-1 space-y-1">
                                                                <span className="block font-medium break-words">
                                                                    {
                                                                        source.item_name
                                                                    }
                                                                </span>
                                                                <span className="block text-xs text-muted-foreground">
                                                                    {
                                                                        source.issue_reference
                                                                    }{' '}
                                                                    / Batch{' '}
                                                                    {
                                                                        source.batch_number
                                                                    }
                                                                </span>
                                                                <span className="block text-xs text-muted-foreground">
                                                                    {
                                                                        source.recipient_name
                                                                    }
                                                                </span>
                                                                <span className="flex flex-wrap justify-between gap-2 text-sm">
                                                                    <span>
                                                                        {
                                                                            source.available_quantity
                                                                        }{' '}
                                                                        {
                                                                            source.unit_of_measure
                                                                        }{' '}
                                                                        returnable
                                                                    </span>
                                                                    <span>
                                                                        {money(
                                                                            source.unit_cost,
                                                                        )}{' '}
                                                                        / unit
                                                                    </span>
                                                                </span>
                                                                {source.expiration_date && (
                                                                    <span className="block text-xs text-muted-foreground">
                                                                        Expiry:{' '}
                                                                        {source.expiration_date.slice(
                                                                            0,
                                                                            10,
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                                {sources.data.length === 0 && (
                                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                                        No issued batches match
                                                        your search.
                                                    </p>
                                                )}
                                            </div>
                                            <div className="hidden overflow-x-auto md:block">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Select
                                                            </TableHead>
                                                            <TableHead>
                                                                Material / issue
                                                            </TableHead>
                                                            <TableHead>
                                                                Recipient
                                                            </TableHead>
                                                            <TableHead>
                                                                Batch / expiry
                                                            </TableHead>
                                                            <TableHead>
                                                                Unit cost
                                                            </TableHead>
                                                            <TableHead>
                                                                Returnable
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {sources.data.map(
                                                            (source) => (
                                                                <TableRow
                                                                    key={
                                                                        source.id
                                                                    }
                                                                >
                                                                    <TableCell>
                                                                        <input
                                                                            type="checkbox"
                                                                            aria-label={`Select ${source.item_name}, ${source.issue_reference}, batch ${source.batch_number}`}
                                                                            disabled={
                                                                                source.available_quantity ===
                                                                                    0 ||
                                                                                (form
                                                                                    .data
                                                                                    .lines
                                                                                    .length >=
                                                                                    25 &&
                                                                                    !form.data.lines.some(
                                                                                        (
                                                                                            line,
                                                                                        ) =>
                                                                                            line.source_allocation_id ===
                                                                                            source.id,
                                                                                    ))
                                                                            }
                                                                            checked={form.data.lines.some(
                                                                                (
                                                                                    line,
                                                                                ) =>
                                                                                    line.source_allocation_id ===
                                                                                    source.id,
                                                                            )}
                                                                            onChange={() =>
                                                                                toggleSource(
                                                                                    source,
                                                                                )
                                                                            }
                                                                        />
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {
                                                                            source.item_name
                                                                        }
                                                                        <p className="text-xs text-muted-foreground">
                                                                            {
                                                                                source.issue_reference
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {source.issue_date.slice(
                                                                                0,
                                                                                10,
                                                                            )}
                                                                        </p>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {
                                                                            source.recipient_name
                                                                        }
                                                                        <p className="text-xs text-muted-foreground">
                                                                            {
                                                                                source.department_name
                                                                            }
                                                                        </p>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        Batch{' '}
                                                                        {
                                                                            source.batch_number
                                                                        }
                                                                        <p className="text-xs text-muted-foreground">
                                                                            {source.expiration_date?.slice(
                                                                                0,
                                                                                10,
                                                                            ) ??
                                                                                'No expiry recorded'}
                                                                        </p>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {money(
                                                                            source.unit_cost,
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {
                                                                            source.available_quantity
                                                                        }{' '}
                                                                        {
                                                                            source.unit_of_measure
                                                                        }
                                                                    </TableCell>
                                                                </TableRow>
                                                            ),
                                                        )}
                                                        {sources.data.length ===
                                                            0 && (
                                                            <TableRow>
                                                                <TableCell
                                                                    colSpan={6}
                                                                    className="py-8 text-center text-muted-foreground"
                                                                >
                                                                    {supplyRequest
                                                                        ? 'No issued batches match this supply request and search.'
                                                                        : 'No recorded issue batches match. Use supported older records when no digital issue exists.'}
                                                                </TableCell>
                                                            </TableRow>
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                            <div className="flex flex-wrap justify-end gap-2">
                                                {sources.links.map(
                                                    (link, position) => (
                                                        <Button
                                                            key={position}
                                                            variant={
                                                                link.active
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            disabled={
                                                                !link.url ||
                                                                link.active
                                                            }
                                                            onClick={() => {
                                                                if (link.url) {
                                                                    const nextPage =
                                                                        Number(
                                                                            new URL(
                                                                                link.url,
                                                                                window
                                                                                    .location
                                                                                    .origin,
                                                                            ).searchParams.get(
                                                                                'page',
                                                                            ) ??
                                                                                '1',
                                                                        );
                                                                    browse(
                                                                        nextPage,
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            {link.label
                                                                .replace(
                                                                    /&laquo;|&raquo;/g,
                                                                    '',
                                                                )
                                                                .trim()}
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        </>
                                    )}
                                </section>
                            ) : (
                                <section className="grid gap-4 rounded-lg border p-4">
                                    <h2 className="font-semibold">
                                        Materials supported by older records
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Add materials with an original issue
                                        reference and documented value.
                                    </p>
                                    <div className="flex flex-wrap items-end gap-3">
                                        <div className="grid min-w-0 flex-1 gap-2">
                                            <Label htmlFor="legacy-item">
                                                Existing consumable
                                            </Label>
                                            <FormSelect
                                                id="legacy-item"
                                                value={catalogId}
                                                onChange={(event) =>
                                                    setCatalogId(
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Select material
                                                </option>
                                                {items.map((item) => (
                                                    <option
                                                        key={
                                                            item.inventory_item_id
                                                        }
                                                        value={
                                                            item.inventory_item_id
                                                        }
                                                    >
                                                        {item.name} —{' '}
                                                        {item.stock_number}
                                                    </option>
                                                ))}
                                            </FormSelect>
                                        </div>
                                        <Button
                                            variant="outline"
                                            disabled={
                                                !catalogId ||
                                                form.data.lines.length >= 25
                                            }
                                            onClick={() => {
                                                const item = items.find(
                                                    (entry) =>
                                                        String(
                                                            entry.inventory_item_id,
                                                        ) === catalogId,
                                                );

                                                if (
                                                    item &&
                                                    !form.data.lines.some(
                                                        (line) =>
                                                            line.inventory_item_id ===
                                                            item.inventory_item_id,
                                                    )
                                                ) {
                                                    form.setData('lines', [
                                                        ...form.data.lines,
                                                        {
                                                            inventory_item_id:
                                                                item.inventory_item_id,
                                                            source_allocation_id:
                                                                null,
                                                            quantity: '1',
                                                            unit_cost: '',
                                                            valuation_basis: '',
                                                            expiration_date: '',
                                                            display_name:
                                                                item.name,
                                                            display_uom:
                                                                item.unit_of_measure,
                                                            display_recipient:
                                                                '',
                                                            issue_reference: '',
                                                            display_reference:
                                                                item.stock_number,
                                                            maximum: null,
                                                        },
                                                    ]);
                                                    setCatalogId('');
                                                }
                                            }}
                                        >
                                            Add material
                                        </Button>
                                    </div>
                                </section>
                            )}
                            <form
                                id="rms-preparation-form"
                                className="grid gap-6"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    form.transform((data) => ({
                                        ...data,
                                        department_name: departmentName,
                                        returned_by_reference_id:
                                            data.source_mode === 'legacy'
                                                ? data.returned_by_reference_id ||
                                                  null
                                                : null,
                                        returned_by_name:
                                            data.source_mode === 'legacy'
                                                ? data.returned_by_name
                                                : null,
                                        legacy_issue_reference:
                                            data.source_mode === 'legacy'
                                                ? data.legacy_issue_reference
                                                : null,
                                        lines: data.lines.map((line) =>
                                            data.source_mode === 'linked'
                                                ? {
                                                      source_allocation_id:
                                                          line.source_allocation_id,
                                                      quantity: line.quantity,
                                                  }
                                                : {
                                                      inventory_item_id:
                                                          line.inventory_item_id,
                                                      quantity: line.quantity,
                                                      unit_cost: line.unit_cost,
                                                      valuation_basis:
                                                          line.valuation_basis,
                                                      expiration_date:
                                                          line.expiration_date ||
                                                          null,
                                                  },
                                        ),
                                    }));
                                    form.post(store.url(), {
                                        headers: { 'X-RMS-Dialog': 'true' },
                                    });
                                }}
                            >
                                {form.data.lines.length > 0 && (
                                    <section className="grid gap-3 rounded-lg border p-4">
                                        <h2 className="font-semibold">
                                            Return quantities
                                        </h2>
                                        {form.data.lines.map(
                                            (line, position) => (
                                                <article
                                                    key={
                                                        line.source_allocation_id ??
                                                        `legacy-${line.inventory_item_id}`
                                                    }
                                                    className="grid gap-3 border-b pb-4 last:border-b-0 last:pb-0"
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <h3 className="font-medium">
                                                                {
                                                                    line.display_name
                                                                }
                                                            </h3>
                                                            <p className="text-xs break-words text-muted-foreground">
                                                                {
                                                                    line.display_reference
                                                                }
                                                            </p>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() =>
                                                                form.setData(
                                                                    'lines',
                                                                    form.data.lines.filter(
                                                                        (
                                                                            _,
                                                                            index,
                                                                        ) =>
                                                                            index !==
                                                                            position,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    </div>
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor={`quantity-${position}`}
                                                            >
                                                                Return quantity
                                                                {line.maximum !==
                                                                null
                                                                    ? ` (max ${line.maximum})`
                                                                    : ''}
                                                            </Label>
                                                            <Input
                                                                id={`quantity-${position}`}
                                                                required
                                                                type="number"
                                                                min={1}
                                                                max={
                                                                    line.maximum ??
                                                                    1000000
                                                                }
                                                                step={1}
                                                                aria-invalid={
                                                                    !!form
                                                                        .errors[
                                                                        `lines.${position}.quantity`
                                                                    ]
                                                                }
                                                                value={
                                                                    line.quantity
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateLine(
                                                                        position,
                                                                        'quantity',
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        {form.data
                                                            .source_mode ===
                                                            'legacy' && (
                                                            <>
                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={`cost-${position}`}
                                                                    >
                                                                        Documented
                                                                        unit
                                                                        cost
                                                                        (PHP)
                                                                    </Label>
                                                                    <Input
                                                                        id={`cost-${position}`}
                                                                        required
                                                                        type="number"
                                                                        min={0}
                                                                        step="0.01"
                                                                        value={
                                                                            line.unit_cost
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                position,
                                                                                'unit_cost',
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={`basis-${position}`}
                                                                    >
                                                                        Valuation
                                                                        basis /
                                                                        accounting
                                                                        reference
                                                                    </Label>
                                                                    <Input
                                                                        id={`basis-${position}`}
                                                                        required
                                                                        maxLength={
                                                                            3000
                                                                        }
                                                                        value={
                                                                            line.valuation_basis
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            updateLine(
                                                                                position,
                                                                                'valuation_basis',
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={`expiry-${position}`}
                                                                    >
                                                                        Expiry
                                                                        date
                                                                        (optional)
                                                                    </Label>
                                                                    <DatePicker
                                                                        id={`expiry-${position}`}
                                                                        value={
                                                                            line.expiration_date
                                                                        }
                                                                        onChange={(
                                                                            value,
                                                                        ) =>
                                                                            updateLine(
                                                                                position,
                                                                                'expiration_date',
                                                                                value,
                                                                            )
                                                                        }
                                                                    />
                                                                </div>
                                                            </>
                                                        )}
                                                    </div>
                                                </article>
                                            ),
                                        )}
                                    </section>
                                )}
                                <section className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                    <h2 className="font-semibold sm:col-span-2">
                                        Return details
                                    </h2>
                                    <div className="grid gap-2">
                                        <Label htmlFor="return-date">
                                            Physical return date
                                        </Label>
                                        <DatePicker
                                            id="return-date"
                                            required
                                            max={todayInManila()}
                                            value={form.data.return_date}
                                            onChange={(value) =>
                                                form.setData(
                                                    'return_date',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="return-department">
                                            Returning department
                                        </Label>
                                        <Input
                                            id="return-department"
                                            required
                                            maxLength={255}
                                            value={departmentName}
                                            onChange={(event) => {
                                                setDepartmentEdited(true);
                                                form.setData(
                                                    'department_name',
                                                    event.target.value,
                                                );
                                            }}
                                        />
                                    </div>
                                    {form.data.source_mode === 'legacy' && (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="legacy-reference">
                                                    Original issue / supporting
                                                    record reference
                                                </Label>
                                                <Input
                                                    id="legacy-reference"
                                                    required
                                                    maxLength={120}
                                                    value={
                                                        form.data
                                                            .legacy_issue_reference
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'legacy_issue_reference',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="return-employee">
                                                    Accountable employee
                                                    (optional)
                                                </Label>
                                                <HrisReferenceCombobox
                                                    references={references}
                                                    name="returned_by_reference_id"
                                                    id="return-employee"
                                                    value={
                                                        form.data
                                                            .returned_by_reference_id
                                                    }
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'returned_by_reference_id',
                                                            value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="return-person">
                                                    Accountable person from
                                                    older records
                                                </Label>
                                                <Input
                                                    id="return-person"
                                                    disabled={
                                                        !!form.data
                                                            .returned_by_reference_id
                                                    }
                                                    required={
                                                        !form.data
                                                            .returned_by_reference_id
                                                    }
                                                    maxLength={255}
                                                    value={
                                                        form.data
                                                            .returned_by_name
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'returned_by_name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </>
                                    )}
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="return-reason">
                                            Purpose / Reason for return
                                        </Label>
                                        <ReturnReasonCombobox
                                            id="return-reason"
                                            reasons={commonReturnReasons}
                                            value={form.data.reason}
                                            onValueChange={(value) =>
                                                form.setData('reason', value)
                                            }
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Select a common reason, then edit or
                                            add your detailed explanation in the
                                            same field. You can also type your
                                            own reason.
                                        </p>
                                        <InputError
                                            message={form.errors.reason}
                                        />
                                    </div>
                                </section>
                                <RmsFormView
                                    document={{
                                        document_no: 'Assigned on submission',
                                        document_date: todayInManila(),
                                        return_date: form.data.return_date,
                                        returned_by_name:
                                            form.data.source_mode === 'linked'
                                                ? (form.data.lines[0]
                                                      ?.display_recipient ?? '')
                                                : (references.find(
                                                      (reference) =>
                                                          String(
                                                              reference.id,
                                                          ) ===
                                                          form.data
                                                              .returned_by_reference_id,
                                                  )?.name ??
                                                  form.data.returned_by_name),
                                        department_name: departmentName,
                                        reason: form.data.reason,
                                        lines: form.data.lines.map(
                                            (line, i) => ({
                                                id: i,
                                                quantity_declared:
                                                    line.quantity,
                                                unit_of_measure:
                                                    line.display_uom,
                                                item_name: line.display_name,
                                                source_issue_reference:
                                                    line.issue_reference ||
                                                    form.data
                                                        .legacy_issue_reference,
                                                condition: null,
                                                inspection_remarks: null,
                                            }),
                                        ),
                                    }}
                                />
                                <label className="flex items-start gap-2 text-sm">
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
                                    I confirm these declared quantities and
                                    supporting records match the physical
                                    return.
                                </label>
                                {Object.keys(form.errors).length > 0 && (
                                    <div
                                        role="alert"
                                        className="grid gap-1 rounded-lg border border-destructive/30 p-3"
                                    >
                                        {[
                                            ...new Set(
                                                Object.values(form.errors),
                                            ),
                                        ].map((error) => (
                                            <InputError
                                                key={error}
                                                message={error}
                                            />
                                        ))}
                                    </div>
                                )}
                            </form>
                        </fieldset>
                    </div>
                    <DialogFooter className="m-0 shrink-0 border-t bg-background px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setConfirmCancel(true)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            form="rms-preparation-form"
                            disabled={
                                form.processing ||
                                sourcesLoading ||
                                !form.data.attested ||
                                form.data.lines.length === 0
                            }
                        >
                            {form.processing ? 'Submitting...' : 'Submit RMS'}
                        </Button>
                    </DialogFooter>
                    <Dialog
                        open={confirmCancel}
                        onOpenChange={setConfirmCancel}
                    >
                        <DialogContent
                            showCloseButton={false}
                            role="alertdialog"
                        >
                            <DialogHeader>
                                <DialogTitle>
                                    Cancel RMS preparation?
                                </DialogTitle>
                                <DialogDescription>
                                    Your unsaved RMS details will be discarded.
                                    Are you sure you want to cancel?
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    autoFocus
                                    onClick={() => setConfirmCancel(false)}
                                >
                                    Keep editing
                                </Button>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={closePreparation}
                                >
                                    Yes, cancel
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </DialogContent>
            </Dialog>
        </>
    );
}
