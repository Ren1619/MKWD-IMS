import { Head, Link, router, useForm } from '@inertiajs/react';
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
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/inventory/returns';
import type { HrisReference, Paginated } from '@/types/inventory';
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
    display_reference: string;
    maximum: number | null;
};
const textAreaClass =
    'min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';
export default function ReturnCreate({
    sources,
    items,
    references,
    filters,
}: {
    sources: Paginated<ReturnSource>;
    items: CatalogItem[];
    references: HrisReference[];
    filters: { search?: string; supply_request_id?: number };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [catalogId, setCatalogId] = useState('');
    const form = useForm({
        idempotency_key: crypto.randomUUID(),
        source_mode: 'linked' as 'linked' | 'legacy',
        return_date: todayInManila(),
        department_name: '',
        returned_by_reference_id: '',
        returned_by_name: '',
        endorsement_reference: '',
        legacy_issue_reference: '',
        reason: '',
        lines: [] as InputLine[],
        attested: false,
    });
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
                          display_reference: `${source.issue_reference} · Batch ${source.batch_number} · ${source.recipient_name}`,
                          maximum: source.available_quantity,
                      },
                  ],
        );

        if (!selected && !form.data.department_name && source.department_name) {
            form.setData('department_name', source.department_name);
        }
    };
    const browse = (page = 1) =>
        router.get(
            create.url(),
            { search, page, supply_request_id: filters.supply_request_id },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Prepare return slip" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <Link
                        href={index()}
                        className="text-sm text-muted-foreground underline"
                    >
                        Return to stock
                    </Link>
                    <h1 className="mt-2 text-2xl font-semibold">
                        Prepare a Return Materials Slip
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record physical returns using the endorsement letter.
                        Verification and approval precede stock posting.
                    </p>
                </div>
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
                                event.target.value as 'linked' | 'legacy',
                            );
                            form.setData('lines', []);
                            form.clearErrors();
                        }}
                    >
                        <option value="linked">Recorded issue batches</option>
                        <option value="legacy">
                            Older issue supported by external records
                        </option>
                    </FormSelect>
                </div>
                {form.data.source_mode === 'linked' ? (
                    <section className="grid gap-4 rounded-lg border p-4">
                        <h2 className="font-semibold">Select issued batches</h2>
                        <p className="text-sm text-muted-foreground">
                            Choose batches for one accountable person. Each
                            retains its issued unit cost and expiry date.
                            Pending slips reserve returnable quantities.
                        </p>
                        <form
                            className="flex flex-wrap gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                browse();
                            }}
                        >
                            <Input
                                className="max-w-lg"
                                aria-label="Search source issues"
                                placeholder="Search issue number, item, or recipient"
                                maxLength={150}
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                            <Button variant="outline">Search issues</Button>
                            {filters.supply_request_id && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        router.get(
                                            create.url(),
                                            { search },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    Show issues from all requests
                                </Button>
                            )}
                        </form>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Select</TableHead>
                                        <TableHead>Material / issue</TableHead>
                                        <TableHead>Recipient</TableHead>
                                        <TableHead>Batch / expiry</TableHead>
                                        <TableHead>Unit cost</TableHead>
                                        <TableHead>Returnable</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sources.data.map((source) => (
                                        <TableRow key={source.id}>
                                            <TableCell>
                                                <input
                                                    type="checkbox"
                                                    aria-label={`Select ${source.item_name}, ${source.issue_reference}, batch ${source.batch_number}`}
                                                    disabled={
                                                        source.available_quantity ===
                                                            0 ||
                                                        (form.data.lines
                                                            .length >= 25 &&
                                                            !form.data.lines.some(
                                                                (line) =>
                                                                    line.source_allocation_id ===
                                                                    source.id,
                                                            ))
                                                    }
                                                    checked={form.data.lines.some(
                                                        (line) =>
                                                            line.source_allocation_id ===
                                                            source.id,
                                                    )}
                                                    onChange={() =>
                                                        toggleSource(source)
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {source.item_name}
                                                <p className="text-xs text-muted-foreground">
                                                    {source.issue_reference} ·{' '}
                                                    {source.issue_date.slice(
                                                        0,
                                                        10,
                                                    )}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {source.recipient_name}
                                                <p className="text-xs text-muted-foreground">
                                                    {source.department_name}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                Batch {source.batch_number}
                                                <p className="text-xs text-muted-foreground">
                                                    {source.expiration_date?.slice(
                                                        0,
                                                        10,
                                                    ) ?? 'No expiry recorded'}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                {money(source.unit_cost)}
                                            </TableCell>
                                            <TableCell>
                                                {source.available_quantity}{' '}
                                                {source.unit_of_measure}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {sources.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No recorded issue batches match.
                                                Use supported older records when
                                                no digital issue exists.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            {sources.links.map((link, position) => (
                                <Button
                                    key={position}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    disabled={!link.url || link.active}
                                    onClick={() => {
                                        if (link.url) {
                                            const nextPage = Number(
                                                new URL(
                                                    link.url,
                                                    window.location.origin,
                                                ).searchParams.get('page') ??
                                                    '1',
                                            );
                                            browse(nextPage);
                                        }
                                    }}
                                >
                                    {link.label
                                        .replace(/&laquo;|&raquo;/g, '')
                                        .trim()}
                                </Button>
                            ))}
                        </div>
                    </section>
                ) : (
                    <section className="grid gap-4 rounded-lg border p-4">
                        <h2 className="font-semibold">
                            Materials supported by older records
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Provide the original issue reference and a
                            documented valuation for each material. These
                            returns require independent inspection and approval.
                        </p>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid min-w-64 flex-1 gap-2">
                                <Label htmlFor="legacy-item">
                                    Existing consumable
                                </Label>
                                <FormSelect
                                    id="legacy-item"
                                    value={catalogId}
                                    onChange={(event) =>
                                        setCatalogId(event.target.value)
                                    }
                                >
                                    <option value="">Select material</option>
                                    {items.map((item) => (
                                        <option
                                            key={item.inventory_item_id}
                                            value={item.inventory_item_id}
                                        >
                                            {item.name} — {item.stock_number}
                                        </option>
                                    ))}
                                </FormSelect>
                            </div>
                            <Button
                                variant="outline"
                                disabled={
                                    !catalogId || form.data.lines.length >= 25
                                }
                                onClick={() => {
                                    const item = items.find(
                                        (entry) =>
                                            String(entry.inventory_item_id) ===
                                            catalogId,
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
                                                source_allocation_id: null,
                                                quantity: '1',
                                                unit_cost: '',
                                                valuation_basis: '',
                                                expiration_date: '',
                                                display_name: item.name,
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
                    className="grid gap-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            returned_by_reference_id:
                                data.source_mode === 'legacy'
                                    ? data.returned_by_reference_id || null
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
                                          valuation_basis: line.valuation_basis,
                                          expiration_date:
                                              line.expiration_date || null,
                                      },
                            ),
                        }));
                        form.post(store.url());
                    }}
                >
                    <section className="grid gap-4 rounded-lg border p-4">
                        <h2 className="font-semibold">
                            Selected materials ({form.data.lines.length})
                        </h2>
                        {form.data.lines.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Select issued batches or add a material to
                                begin.
                            </p>
                        )}
                        {form.data.lines.map((line, position) => (
                            <article
                                key={
                                    line.source_allocation_id ??
                                    `legacy-${line.inventory_item_id}`
                                }
                                className="grid gap-4 rounded-lg border p-4"
                            >
                                <div className="flex justify-between gap-3">
                                    <div>
                                        <h3 className="font-medium">
                                            {line.display_name}
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            {line.display_reference}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            form.setData(
                                                'lines',
                                                form.data.lines.filter(
                                                    (_, index) =>
                                                        index !== position,
                                                ),
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor={`quantity-${position}`}>
                                            Quantity physically returned
                                        </Label>
                                        <Input
                                            id={`quantity-${position}`}
                                            required
                                            type="number"
                                            min={1}
                                            max={line.maximum ?? 1000000}
                                            step={1}
                                            value={line.quantity}
                                            onChange={(event) =>
                                                updateLine(
                                                    position,
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    {form.data.source_mode === 'linked' ? (
                                        <p className="self-end text-sm">
                                            Issued unit cost:{' '}
                                            {money(line.unit_cost)} · Expiry:{' '}
                                            {line.expiration_date ||
                                                'Not recorded'}
                                        </p>
                                    ) : (
                                        <>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`cost-${position}`}
                                                >
                                                    Documented unit cost (PHP)
                                                </Label>
                                                <Input
                                                    id={`cost-${position}`}
                                                    required
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    value={line.unit_cost}
                                                    onChange={(event) =>
                                                        updateLine(
                                                            position,
                                                            'unit_cost',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`basis-${position}`}
                                                >
                                                    Valuation basis / accounting
                                                    reference
                                                </Label>
                                                <Input
                                                    id={`basis-${position}`}
                                                    required
                                                    maxLength={3000}
                                                    value={line.valuation_basis}
                                                    onChange={(event) =>
                                                        updateLine(
                                                            position,
                                                            'valuation_basis',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`expiry-${position}`}
                                                >
                                                    Expiry date (optional)
                                                </Label>
                                                <DatePicker
                                                    id={`expiry-${position}`}
                                                    value={line.expiration_date}
                                                    onChange={(value) =>
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
                        ))}
                    </section>
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
                                    form.setData('return_date', value)
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
                                value={form.data.department_name}
                                onChange={(event) =>
                                    form.setData(
                                        'department_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="return-endorsement">
                                Endorsement letter reference
                            </Label>
                            <Input
                                id="return-endorsement"
                                required
                                maxLength={120}
                                value={form.data.endorsement_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'endorsement_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        {form.data.source_mode === 'legacy' && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="legacy-reference">
                                        Original issue / supporting record
                                        reference
                                    </Label>
                                    <Input
                                        id="legacy-reference"
                                        required
                                        maxLength={120}
                                        value={form.data.legacy_issue_reference}
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
                                        Accountable employee (optional)
                                    </Label>
                                    <HrisReferenceCombobox
                                        references={references}
                                        name="returned_by_reference_id"
                                        id="return-employee"
                                        value={
                                            form.data.returned_by_reference_id
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
                                        Accountable person from older records
                                    </Label>
                                    <Input
                                        id="return-person"
                                        disabled={
                                            !!form.data.returned_by_reference_id
                                        }
                                        required={
                                            !form.data.returned_by_reference_id
                                        }
                                        maxLength={255}
                                        value={form.data.returned_by_name}
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
                                Purpose / reason for return
                            </Label>
                            <textarea
                                id="return-reason"
                                rows={3}
                                className={textAreaClass}
                                required
                                maxLength={3000}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </div>
                    </section>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(event) =>
                                form.setData('attested', event.target.checked)
                            }
                        />
                        I confirm these declared quantities and supporting
                        records match the physical return.
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                    <Button
                        className="self-start"
                        disabled={
                            form.processing ||
                            !form.data.attested ||
                            form.data.lines.length === 0
                        }
                    >
                        Submit RMS for inspection
                    </Button>
                </form>
            </div>
        </>
    );
}
ReturnCreate.layout = {
    breadcrumbs: [
        { title: 'Return to stock', href: index() },
        { title: 'Prepare RMS', href: create() },
    ],
};
