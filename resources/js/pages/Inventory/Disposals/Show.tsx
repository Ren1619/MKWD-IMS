import { Form, Head, Link } from '@inertiajs/react';
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
import {
    index,
    print as printWmr,
    transition,
} from '@/routes/inventory/disposals';
import type { HrisReference } from '@/types/inventory';
import { label } from './types';
import type { Disposal } from './types';
export default function DisposalShow({
    document,
    allowedActions,
    employees,
    today,
}: {
    document: Disposal;
    allowedActions: string[];
    employees: HrisReference[];
    today: string;
}) {
    const [action, setAction] = useState(allowedActions[0] ?? '');
    const selected = allowedActions.includes(action)
        ? action
        : (allowedActions[0] ?? '');

    return (
        <>
            <Head title={document.document_no} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <Link href={index()} className="text-sm underline">
                            Waste materials
                        </Link>
                        <h1 className="mt-2 text-2xl font-semibold">
                            {document.document_no}
                        </h1>
                        <p>
                            {label(document.status)} · {label(document.method)}
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        render={
                            <a
                                href={printWmr.url(document.id)}
                                target="_blank"
                                rel="noreferrer"
                            />
                        }
                    >
                        Print WMR / certificate
                    </Button>
                </div>
                <dl className="grid gap-3 rounded-lg border p-4 md:grid-cols-2">
                    {[
                        ['Storage', document.place_of_storage],
                        ['Reason', document.reason],
                        ['Certified by', document.certifier_snapshot.name],
                        [
                            'Signed certification reference',
                            document.certification_reference,
                        ],
                        ['Certification', document.certification_attestation],
                    ].map(([title, value]) => (
                        <div key={title}>
                            <dt className="text-sm text-muted-foreground">
                                {title}
                            </dt>
                            <dd>{value}</dd>
                        </div>
                    ))}
                </dl>
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {[
                                    'Description / source',
                                    'Quantity',
                                    'Proceeds',
                                    'Official receipt',
                                ].map((t) => (
                                    <TableHead key={t}>{t}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {document.lines.map((l) => (
                                <TableRow key={l.id}>
                                    <TableCell>
                                        {l.description}
                                        <p className="text-sm text-muted-foreground">
                                            {l.inventory_asset_id === null
                                                ? 'Standalone waste'
                                                : 'Whole registered property'}{' '}
                                            · {l.source_reference}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {l.quantity} {l.unit}
                                    </TableCell>
                                    <TableCell>
                                        {l.proceeds ?? 'Not posted'}
                                    </TableCell>
                                    <TableCell>{l.or_number ?? '—'}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                {allowedActions.length > 0 && (
                    <Form
                        key={document.status}
                        action={transition(document.id)}
                        className="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="action">Decision</Label>
                                    <FormSelect
                                        id="action"
                                        name="action"
                                        value={selected}
                                        onChange={(e) =>
                                            setAction(e.target.value)
                                        }
                                    >
                                        {allowedActions.map((a) => (
                                            <option key={a} value={a}>
                                                {label(a)}
                                            </option>
                                        ))}
                                    </FormSelect>
                                </div>
                                <p className="self-center text-sm text-muted-foreground">
                                    Approval requires a different IMS manager
                                    from the preparer and a committee chair
                                    distinct from the certifying custodian.
                                    Inspection and disposition remain physical
                                    actions supported by signed evidence.
                                </p>
                                {(selected === 'approve'
                                    ? [
                                          [
                                              'chair_reference_id',
                                              'Approving committee chairperson',
                                          ],
                                      ]
                                    : selected === 'complete'
                                      ? [
                                            [
                                                'inspector_reference_id',
                                                'Property inspector',
                                            ],
                                            [
                                                'internal_control_reference_id',
                                                'Internal Control witness',
                                            ],
                                            [
                                                'accounting_witness_reference_id',
                                                'Accounting witness',
                                            ],
                                        ]
                                      : []
                                ).map(([name, title]) => (
                                    <div className="grid gap-2" key={name}>
                                        <Label htmlFor={name}>{title}</Label>
                                        <HrisReferenceCombobox
                                            id={name}
                                            name={name}
                                            references={employees}
                                            required
                                        />
                                        <InputError message={errors[name]} />
                                    </div>
                                ))}
                                {(selected === 'approve'
                                    ? [
                                          [
                                              'approval_no',
                                              'Signed committee approval reference',
                                          ],
                                          [
                                              'approval_date',
                                              'Physical approval date',
                                          ],
                                      ]
                                    : selected === 'complete'
                                      ? [
                                            [
                                                'disposition_date',
                                                'Physical disposition date',
                                            ],
                                            [
                                                'disposition_reference',
                                                'Disposition evidence reference',
                                            ],
                                            [
                                                'inspection_certificate_reference',
                                                'Signed inspection certificate reference',
                                            ],
                                            [
                                                'internal_control_signature_reference',
                                                'Internal Control witness signature reference',
                                            ],
                                            [
                                                'accounting_signature_reference',
                                                'Accounting witness signature reference',
                                            ],
                                            [
                                                'accounting_reference',
                                                'Accounting Unit posting / confirmation reference',
                                            ],
                                        ]
                                      : []
                                ).map(([name, title]) => (
                                    <div className="grid gap-2" key={name}>
                                        <Label htmlFor={name}>{title}</Label>
                                        {name.endsWith('_date') ? (
                                            <DatePicker
                                                id={name}
                                                name={name}
                                                max={today}
                                                defaultValue={today}
                                                required
                                            />
                                        ) : (
                                            <Input
                                                id={name}
                                                name={name}
                                                type="text"
                                                required
                                                maxLength={120}
                                            />
                                        )}
                                        <InputError message={errors[name]} />
                                    </div>
                                ))}
                                {selected === 'complete' && (
                                    <>
                                        <p className="text-sm text-muted-foreground md:col-span-2">
                                            The inspector and the two witnesses
                                            must be different people. Sales
                                            require positive proceeds and an
                                            official receipt per item.
                                            Destruction has zero proceeds.
                                            Completion posts today's ledger
                                            event; the physical disposition date
                                            is retained as evidence.
                                        </p>
                                        {document.lines.map((l, i) => (
                                            <fieldset
                                                key={l.id}
                                                className="grid gap-3 rounded-lg border p-3 md:col-span-2 md:grid-cols-2"
                                            >
                                                <legend>{l.description}</legend>
                                                <input
                                                    type="hidden"
                                                    name={`lines[${i}][id]`}
                                                    value={l.id}
                                                />
                                                {[
                                                    [
                                                        'proceeds',
                                                        'Sale proceeds (PHP)',
                                                    ],
                                                    [
                                                        'or_number',
                                                        'Official receipt number',
                                                    ],
                                                ].map(([name, title]) => (
                                                    <div
                                                        key={name}
                                                        className="grid gap-2"
                                                    >
                                                        <Label
                                                            htmlFor={
                                                                name +
                                                                '-' +
                                                                l.id
                                                            }
                                                        >
                                                            {title}
                                                        </Label>
                                                        <Input
                                                            id={
                                                                name +
                                                                '-' +
                                                                l.id
                                                            }
                                                            name={`lines[${i}][${name}]`}
                                                            type={
                                                                name ===
                                                                'proceeds'
                                                                    ? 'number'
                                                                    : 'text'
                                                            }
                                                            step={
                                                                name ===
                                                                'proceeds'
                                                                    ? '0.01'
                                                                    : undefined
                                                            }
                                                            min={
                                                                name ===
                                                                'proceeds'
                                                                    ? 0
                                                                    : undefined
                                                            }
                                                            maxLength={120}
                                                            defaultValue={
                                                                name ===
                                                                    'proceeds' &&
                                                                document.method ===
                                                                    'destroyed'
                                                                    ? '0'
                                                                    : undefined
                                                            }
                                                            required={
                                                                name ===
                                                                    'proceeds' ||
                                                                document.method !==
                                                                    'destroyed'
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
                                            </fieldset>
                                        ))}
                                    </>
                                )}
                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="attestation">
                                        Decision / physical findings attestation
                                    </Label>
                                    <Input
                                        id="attestation"
                                        name="attestation"
                                        required
                                        maxLength={2000}
                                    />
                                </div>
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
                                    disabled={processing}
                                    className="justify-self-start"
                                >
                                    {processing
                                        ? 'Recording…'
                                        : label(selected)}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {[
                                    'Date',
                                    'Decision',
                                    'IMS officer',
                                    'Attestation',
                                ].map((t) => (
                                    <TableHead key={t}>{t}</TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {document.actions.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell>
                                        {a.created_at.slice(0, 10)}
                                    </TableCell>
                                    <TableCell>{label(a.action)}</TableCell>
                                    <TableCell>{a.actor_name}</TableCell>
                                    <TableCell>{a.attestation}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}
