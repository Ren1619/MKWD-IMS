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
import { DatePicker } from '@/components/ui/date-picker';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, show } from '@/routes/inventory/transfers';
import type { HrisReference, Paginated } from '@/types/inventory';
import { label } from './types';
import type { Transfer } from './types';

type PropertyOption = {
    inventory_asset_id: number;
    name: string;
    property_number: string | null;
    location: string | null;
};

export default function TransfersIndex({
    transfers,
    assets,
    employees,
    filters,
    today,
}: {
    transfers: Paginated<Transfer>;
    assets: PropertyOption[];
    employees: HrisReference[];
    filters: { search?: string; status?: string; asset?: number };
    today: string;
}) {
    const [transferType, setTransferType] = useState('reassignment');
    const external = ['agency_transfer', 'donation'].includes(transferType);

    return (
        <>
            <Head title="Property transfers" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Property transfers
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        PTR requests, verification, approval, and joint
                        handover. Physical inspection and signatures are
                        recorded through evidence references.
                    </p>
                </div>
                <Form
                    action={index()}
                    className="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_12rem_auto]"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="transfer-search">
                            PTR number or property selection search
                        </Label>
                        <Input
                            id="transfer-search"
                            name="search"
                            defaultValue={filters.search}
                            maxLength={150}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="transfer-status">Status</Label>
                        <FormSelect
                            id="transfer-status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                        >
                            <option value="">All statuses</option>
                            {[
                                'submitted',
                                'verified',
                                'approved',
                                'completed',
                                'rejected',
                                'cancelled',
                            ].map((value) => (
                                <option key={value} value={value}>
                                    {label(value)}
                                </option>
                            ))}
                        </FormSelect>
                    </div>
                    <Button className="self-end">Search</Button>
                </Form>
                <Form
                    action={store()}
                    resetOnSuccess
                    className="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <h2 className="text-lg font-semibold md:col-span-2">
                                Prepare property transfer
                            </h2>
                            <p className="text-sm text-muted-foreground md:col-span-2">
                                Property selection shows up to 100 matching
                                assigned records. Search above or open this page
                                from a property record. Agency transfers and
                                donations require a separate accounting review.
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="transfer-property">
                                    Property
                                </Label>
                                <FormSelect
                                    id="transfer-property"
                                    name="inventory_asset_id"
                                    defaultValue={
                                        filters.asset
                                            ? String(filters.asset)
                                            : ''
                                    }
                                    required
                                >
                                    <option value="">Select property</option>
                                    {assets.map((asset) => (
                                        <option
                                            key={asset.inventory_asset_id}
                                            value={asset.inventory_asset_id}
                                        >
                                            {asset.property_number ??
                                                'No property number'}{' '}
                                            — {asset.name}
                                        </option>
                                    ))}
                                </FormSelect>
                                <InputError
                                    message={errors.inventory_asset_id}
                                />
                            </div>
                            {!external && (
                                <div className="grid gap-2">
                                    <Label htmlFor="transfer-recipient">
                                        Receiving officer
                                    </Label>
                                    <HrisReferenceCombobox
                                        id="transfer-recipient"
                                        name="recipient_reference_id"
                                        references={employees}
                                        required
                                    />
                                    <InputError
                                        message={errors.recipient_reference_id}
                                    />
                                </div>
                            )}
                            {external && (
                                <>
                                    {[
                                        ['agency_name', 'Receiving agency'],
                                        [
                                            'agency_officer_name',
                                            'Receiving agency officer',
                                        ],
                                        [
                                            'agency_officer_position',
                                            'Receiving officer designation',
                                        ],
                                    ].map(([name, title]) => (
                                        <div className="grid gap-2" key={name}>
                                            <Label htmlFor={name}>
                                                {title}
                                            </Label>
                                            <Input
                                                id={name}
                                                name={name}
                                                maxLength={255}
                                                required
                                            />
                                            <InputError
                                                message={errors[name]}
                                            />
                                        </div>
                                    ))}
                                </>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="transfer-type">
                                    Transfer type
                                </Label>
                                <FormSelect
                                    id="transfer-type"
                                    name="transfer_type"
                                    value={transferType}
                                    onChange={(event) =>
                                        setTransferType(event.target.value)
                                    }
                                >
                                    {[
                                        'reassignment',
                                        'resignation',
                                        'relocation',
                                        'other',
                                        'agency_transfer',
                                        'donation',
                                    ].map((value) => (
                                        <option key={value} value={value}>
                                            {label(value)}
                                        </option>
                                    ))}
                                </FormSelect>
                                <InputError message={errors.transfer_type} />
                                <p className="text-xs text-muted-foreground">
                                    Relocation keeps the current officer; other
                                    types change accountability. Agency
                                    transfers and donations permanently transfer
                                    ownership after authority, accounting
                                    review, and handover.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="transfer-date">
                                    Desired transfer date
                                </Label>
                                <DatePicker
                                    id="transfer-date"
                                    name="desired_date"
                                    min={today}
                                    defaultValue={today}
                                    required
                                />
                                <InputError message={errors.desired_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="transfer-location">
                                    Destination location
                                </Label>
                                <Input
                                    id="transfer-location"
                                    name="destination_location"
                                    maxLength={255}
                                    required
                                />
                                <InputError
                                    message={errors.destination_location}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="transfer-reason">
                                    Reason for transfer
                                </Label>
                                <Input
                                    id="transfer-reason"
                                    name="reason"
                                    maxLength={2000}
                                    required
                                />
                                <InputError message={errors.reason} />
                            </div>
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="transfer-justification">
                                    Low-value replacement justification, if
                                    applicable
                                </Label>
                                <Input
                                    id="transfer-justification"
                                    name="replacement_justification"
                                    maxLength={2000}
                                />
                                <InputError
                                    message={errors.replacement_justification}
                                />
                            </div>
                            <InputError
                                className="md:col-span-2"
                                message={errors.transfer}
                            />
                            <Button
                                className="justify-self-start"
                                disabled={processing || assets.length === 0}
                            >
                                {processing
                                    ? 'Submitting…'
                                    : 'Submit PTR request'}
                            </Button>
                        </>
                    )}
                </Form>
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>PTR</TableHead>
                                <TableHead>Property</TableHead>
                                <TableHead>From</TableHead>
                                <TableHead>To</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {transfers.data.map((transfer) => (
                                <TableRow key={transfer.id}>
                                    <TableCell>
                                        <Link
                                            href={show(transfer.id)}
                                            className="font-medium underline"
                                        >
                                            {transfer.document_no}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {transfer.source_snapshot.name}
                                    </TableCell>
                                    <TableCell>
                                        {
                                            transfer.source_snapshot
                                                .recipient_name
                                        }
                                    </TableCell>
                                    <TableCell>
                                        {transfer.recipient_snapshot.name}
                                    </TableCell>
                                    <TableCell>
                                        {label(transfer.status)}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {transfers.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={5}>
                                        No transfer requests match these
                                        filters.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <DataPagination links={transfers.links} />
            </div>
        </>
    );
}
