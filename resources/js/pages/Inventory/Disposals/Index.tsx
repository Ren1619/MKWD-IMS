import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
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
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, show } from '@/routes/inventory/disposals';
import type { HrisReference, Paginated } from '@/types/inventory';
import { label } from './types';
import type { Disposal } from './types';
type PropertyOption = {
    inventory_asset_id: number;
    name: string;
    property_number: string | null;
    unit_of_measure: string;
    quantity_per_property_card: number;
};
export default function DisposalsIndex({
    documents,
    assets,
    employees,
    filters,
}: {
    documents: Paginated<Disposal>;
    assets: PropertyOption[];
    employees: HrisReference[];
    filters: { search?: string; status?: string };
}) {
    const [lines, setLines] = useState([{ key: 0, asset: '' }]);
    const [nextKey, setNextKey] = useState(1);
    return (
        <>
            <Head title="Disposal / Waste materials" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Disposal / Waste materials
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Record custodian certification, committee approval and
                        witnessed physical disposition. Replaced components use
                        standalone waste lines.
                    </p>
                </div>
                <Form
                    action={index()}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">
                            WMR number / property selection search
                        </Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            maxLength={150}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <FormSelect
                            id="status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                        >
                            <option value="">All stages</option>
                            {[
                                'submitted',
                                'approved',
                                'completed',
                                'rejected',
                                'cancelled',
                            ].map((s) => (
                                <option key={s} value={s}>
                                    {label(s)}
                                </option>
                            ))}
                        </FormSelect>
                    </div>
                    <Button>Search</Button>
                </Form>
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>WMR</TableHead>
                                <TableHead>Storage</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Lines</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {documents.data.map((d) => (
                                <TableRow key={d.id}>
                                    <TableCell>
                                        <Link
                                            className="underline"
                                            href={show(d.id)}
                                        >
                                            {d.document_no}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{d.place_of_storage}</TableCell>
                                    <TableCell>{label(d.method)}</TableCell>
                                    <TableCell>{label(d.status)}</TableCell>
                                    <TableCell>{d.lines_count}</TableCell>
                                </TableRow>
                            ))}
                            {documents.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5}>
                                        No waste-material reports found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <DataPagination links={documents.links} />
                <Form
                    action={store()}
                    className="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <h2 className="text-lg font-semibold md:col-span-2">
                                Prepare WMR
                            </h2>
                            {[
                                ['place_of_storage', 'Place of storage'],
                                [
                                    'certification_reference',
                                    'Signed custodian certification reference',
                                ],
                            ].map(([name, title]) => (
                                <div key={name} className="grid gap-2">
                                    <Label htmlFor={name}>{title}</Label>
                                    <Input
                                        id={name}
                                        name={name}
                                        required
                                        maxLength={
                                            name === 'place_of_storage'
                                                ? 255
                                                : 120
                                        }
                                    />
                                    <InputError message={errors[name]} />
                                </div>
                            ))}
                            <div className="grid gap-2">
                                <Label htmlFor="method">Proposed method</Label>
                                <FormSelect
                                    id="method"
                                    name="method"
                                    defaultValue="destroyed"
                                >
                                    {[
                                        'destroyed',
                                        'private_sale',
                                        'public_auction',
                                    ].map((m) => (
                                        <option key={m} value={m}>
                                            {label(m)}
                                        </option>
                                    ))}
                                </FormSelect>
                                <InputError message={errors.method} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="certifier">
                                    Certifying PPE / property custodian
                                </Label>
                                <HrisReferenceCombobox
                                    id="certifier"
                                    name="certifier_reference_id"
                                    references={employees}
                                    required
                                />
                                <InputError
                                    message={errors.certifier_reference_id}
                                />
                            </div>
                            {[
                                ['reason', 'Reason / condition of waste'],
                                [
                                    'certification_attestation',
                                    'Custodian certification / findings',
                                ],
                            ].map(([name, title]) => (
                                <div key={name} className="grid gap-2">
                                    <Label htmlFor={name}>{title}</Label>
                                    <Input
                                        id={name}
                                        name={name}
                                        required
                                        maxLength={2000}
                                    />
                                    <InputError message={errors[name]} />
                                </div>
                            ))}
                            <p className="text-sm text-muted-foreground md:col-span-2">
                                Registered property must be returned and
                                declared unserviceable or for disposal. A
                                standalone component requires its parent
                                property, job order or other source reference;
                                its disposition leaves the parent property
                                ledger unchanged. Search above to narrow the
                                first 100 available properties.
                            </p>
                            {lines.map((line, i) => {
                                const property = assets.find(
                                    (a) =>
                                        a.inventory_asset_id.toString() ===
                                        line.asset,
                                );
                                return (
                                    <fieldset
                                        key={line.key}
                                        className="grid gap-3 rounded-lg border p-3 md:col-span-2 md:grid-cols-2"
                                    >
                                        <legend className="px-1">
                                            Waste item {i + 1}
                                        </legend>
                                        <div className="grid gap-2 md:col-span-2">
                                            <Label
                                                htmlFor={'asset-' + line.key}
                                            >
                                                Registered whole property or
                                                standalone waste
                                            </Label>
                                            <FormSelect
                                                id={'asset-' + line.key}
                                                name={`lines[${i}][inventory_asset_id]`}
                                                value={line.asset}
                                                onChange={(e) =>
                                                    setLines(
                                                        lines.map((l) =>
                                                            l.key === line.key
                                                                ? {
                                                                      ...l,
                                                                      asset: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : l,
                                                        ),
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Standalone waste / removed
                                                    component
                                                </option>
                                                {assets.map((a) => (
                                                    <option
                                                        key={
                                                            a.inventory_asset_id
                                                        }
                                                        value={
                                                            a.inventory_asset_id
                                                        }
                                                    >
                                                        {a.property_number} —{' '}
                                                        {a.name}
                                                    </option>
                                                ))}
                                            </FormSelect>
                                            <InputError
                                                message={
                                                    errors[
                                                        `lines.${i}.inventory_asset_id`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div
                                            key={'details-' + line.asset}
                                            className="grid gap-3 md:col-span-2 md:grid-cols-2"
                                        >
                                            {[
                                                ['quantity', 'Quantity'],
                                                ['unit', 'Unit'],
                                                ['description', 'Description'],
                                                [
                                                    'source_reference',
                                                    'Parent property / source document reference',
                                                ],
                                            ].map(([name, title]) => (
                                                <div
                                                    className="grid gap-2"
                                                    key={name}
                                                >
                                                    <Label
                                                        htmlFor={
                                                            name +
                                                            '-' +
                                                            line.key
                                                        }
                                                    >
                                                        {title}
                                                    </Label>
                                                    <Input
                                                        id={
                                                            name +
                                                            '-' +
                                                            line.key
                                                        }
                                                        name={`lines[${i}][${name}]`}
                                                        type={
                                                            name === 'quantity'
                                                                ? 'number'
                                                                : 'text'
                                                        }
                                                        min={
                                                            name === 'quantity'
                                                                ? 1
                                                                : undefined
                                                        }
                                                        max={
                                                            name === 'quantity'
                                                                ? 1000000
                                                                : undefined
                                                        }
                                                        required
                                                        readOnly={
                                                            !!property &&
                                                            name !==
                                                                'source_reference'
                                                        }
                                                        defaultValue={
                                                            name === 'quantity'
                                                                ? (property?.quantity_per_property_card ??
                                                                  1)
                                                                : name ===
                                                                    'unit'
                                                                  ? (property?.unit_of_measure ??
                                                                    '')
                                                                  : name ===
                                                                      'description'
                                                                    ? (property?.name ??
                                                                      '')
                                                                    : ''
                                                        }
                                                        maxLength={
                                                            name ===
                                                            'description'
                                                                ? 2000
                                                                : name ===
                                                                    'unit'
                                                                  ? 50
                                                                  : 120
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `lines.${i}.${name}`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                        {lines.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setLines(
                                                        lines.filter(
                                                            (l) =>
                                                                l.key !==
                                                                line.key,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove line
                                            </Button>
                                        )}
                                    </fieldset>
                                );
                            })}
                            <Button
                                type="button"
                                variant="outline"
                                className="justify-self-start"
                                disabled={lines.length >= 50}
                                onClick={() => {
                                    setLines([
                                        ...lines,
                                        { key: nextKey, asset: '' },
                                    ]);
                                    setNextKey(nextKey + 1);
                                }}
                            >
                                Add waste item
                            </Button>
                            <div className="md:col-span-2">
                                {Object.entries(errors).map(
                                    ([key, message]) => (
                                        <InputError
                                            key={key}
                                            message={message}
                                        />
                                    ),
                                )}
                            </div>
                            <Button
                                className="justify-self-start"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Recording…'
                                    : 'Submit custodian-certified WMR'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
