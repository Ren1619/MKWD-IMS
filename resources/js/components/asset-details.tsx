import {
    Calculator,
    CalendarClock,
    ClipboardList,
    ChevronRight,
    FileWarning,
    MapPin,
    PackageSearch,
    UserRound,
} from 'lucide-react';
import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';
import { PropertyHistory } from '@/components/property-history';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableRow,
} from '@/components/shared/data-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { InventoryAsset, InventoryAssetEvent } from '@/types/inventory';

const currencyFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
});

const dateTimeFormatter = new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatCurrency(value: number | string | null): string {
    if (value === null || value === '') {
        return 'Not provided';
    }

    return currencyFormatter.format(Number(value));
}

export function formatDate(value: string | null, includeTime = false): string {
    if (!value) {
        return 'Not provided';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return includeTime
        ? dateTimeFormatter.format(date)
        : dateFormatter.format(date);
}

export function formatLabel(value: string): string {
    return value
        .split(/[-_]/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function EventDetails({ details }: { details: Record<string, unknown> }) {
    const labels: Record<string, string> = {
        property_snapshot: 'Property details at the time of transaction',
        accounting_classification: 'Property classification',
        unit_of_measure: 'Unit of measure',
        source: 'Record origin',
        ppe: 'PPE',
        legacy_register: 'Existing property register',
    };
    const entries = Object.entries(details).filter(
        ([key, value]) =>
            key !== 'id' &&
            !/_ids?$/.test(key) &&
            value !== null &&
            value !== '' &&
            value !== undefined,
    );

    if (!entries.length) {
        return null;
    }

    return (
        <dl className="grid gap-3 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2">
            {entries.map(([key, value]) => {
                const nested = typeof value === 'object';
                let display: ReactNode;

                if (Array.isArray(value)) {
                    display = (
                        <div className="grid gap-2">
                            {value.map((item, index) => (
                                <div key={index}>
                                    {item !== null &&
                                    typeof item === 'object' ? (
                                        <EventDetails
                                            details={
                                                item as Record<string, unknown>
                                            }
                                        />
                                    ) : (
                                        String(item ?? 'Not recorded')
                                    )}
                                </div>
                            ))}
                        </div>
                    );
                } else if (value !== null && nested) {
                    display = (
                        <EventDetails
                            details={value as Record<string, unknown>}
                        />
                    );
                } else if (typeof value === 'boolean') {
                    display = value ? 'Yes' : 'No';
                } else if (
                    /(?:cost|amount|value)$/.test(key) &&
                    Number.isFinite(Number(value))
                ) {
                    display = formatCurrency(String(value));
                } else if (
                    typeof value === 'string' &&
                    /^\d{4}-\d{2}-\d{2}(?:T|$)/.test(value)
                ) {
                    display = formatDate(value, key.endsWith('_at'));
                } else if (
                    typeof value === 'string' &&
                    (key === 'source' ||
                        key.includes('status') ||
                        key.includes('classification') ||
                        key === 'value_class')
                ) {
                    display = labels[value] ?? formatLabel(value);
                } else {
                    display = String(value);
                }

                return (
                    <div
                        key={key}
                        className={nested ? 'sm:col-span-2' : undefined}
                    >
                        <dt className="text-xs font-medium text-muted-foreground">
                            {labels[key] ?? formatLabel(key)}
                        </dt>
                        <dd className="mt-1 text-sm break-words whitespace-pre-wrap">
                            {display}
                        </dd>
                    </div>
                );
            })}
        </dl>
    );
}

function DetailField({
    label,
    value,
    fullWidth = false,
}: {
    label: string;
    value: ReactNode;
    fullWidth?: boolean;
}) {
    const mode = useContext(DetailsMode);
    const displayValue =
        value === null || value === '' || value === undefined ? (
            <span className="text-sm text-muted-foreground">Not provided</span>
        ) : (
            value
        );

    if (mode === 'accounting' || mode === 'overview') {
        const important = [
            'Acquisition cost',
            'Gross carrying amount',
            'Depreciation to date',
            'Current book value',
            'Property number',
            'Current custodian',
            'Physical condition',
            'Location',
        ].includes(label);

        return (
            <TableRow className={important ? 'bg-primary/5' : undefined}>
                <TableHead
                    scope="row"
                    className="h-auto w-2/5 py-2 text-sm whitespace-normal"
                >
                    {label}
                </TableHead>
                <TableCell
                    className={
                        important
                            ? 'py-2 text-right text-base font-semibold whitespace-normal tabular-nums'
                            : 'py-2 text-right text-base whitespace-normal tabular-nums'
                    }
                >
                    {displayValue}
                </TableCell>
            </TableRow>
        );
    }

    return (
        <div className={fullWidth ? 'col-span-2' : undefined}>
            <dt className="text-sm font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-base break-words text-foreground">
                {value === null || value === '' || value === undefined ? (
                    <span className="text-sm text-muted-foreground">
                        Not provided
                    </span>
                ) : (
                    value
                )}
            </dd>
        </div>
    );
}

export type AssetDetailsMode = 'overview' | 'custody' | 'accounting';
const DetailsMode = createContext<AssetDetailsMode>('overview');

function AccountingGroup({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <Card
            role="region"
            aria-label={title}
            className="w-full min-w-0 gap-0 overflow-hidden py-0"
        >
            <CardHeader className="border-b bg-muted/30 px-4 py-3">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto px-0">
                <Table aria-label={title} data-accounting-table>
                    <TableBody aria-label={title}>{children}</TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function DetailSection({
    icon: Icon,
    title,
    children,
    group,
    secondary = false,
}: {
    icon: typeof PackageSearch;
    title: string;
    children: ReactNode;
    group: AssetDetailsMode;
    secondary?: boolean;
}) {
    const mode = useContext(DetailsMode);

    if (mode !== group) {
        return null;
    }

    if (group === 'accounting') {
        return (
            <section aria-label={title}>
                <h2 className="sr-only">{title}</h2>
                <div className="grid items-start gap-4 lg:grid-cols-3">
                    {children}
                </div>
            </section>
        );
    }

    const heading = (
        <span className="flex items-center gap-2 text-base font-semibold">
            <Icon className="size-4" />
            {title}
        </span>
    );
    const content =
        group === 'overview' ? (
            <div className="overflow-x-auto">
                <Table aria-label={title} data-overview-table>
                    <TableBody>{children}</TableBody>
                </Table>
            </div>
        ) : (
            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 p-4">{children}</dl>
        );

    if (secondary) {
        return (
            <details
                aria-label={title}
                className="group/details w-full min-w-0 rounded-xl border bg-card"
            >
                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-4 py-3 focus-visible:outline-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                    {heading}
                    <ChevronRight
                        aria-hidden="true"
                        className="size-4 shrink-0 text-muted-foreground transition-transform group-open/details:rotate-90"
                    />
                </summary>
                {content}
            </details>
        );
    }

    return (
        <section
            aria-label={title}
            className="w-full min-w-0 rounded-xl border bg-card"
        >
            <div className="border-b bg-muted/30 px-4 py-3">{heading}</div>
            {content}
        </section>
    );
}

export function AssetDetails({
    asset,
    mode = 'overview',
}: {
    asset: InventoryAsset;
    mode?: AssetDetailsMode;
}) {
    const activeBorrower =
        asset.active_borrowing?.borrower_reference?.name ??
        asset.active_borrowing?.borrower_name;
    const hasDisposalOrLossInformation = Boolean(
        asset.disposal_method ||
        asset.disposal_value ||
        asset.loss_report_no ||
        asset.loss_report_date ||
        asset.loss_type ||
        asset.loss_circumstances,
    );
    const isPpe = asset.accounting_classification === 'ppe';

    return (
        <DetailsMode.Provider value={mode}>
            <div className="grid gap-4">
                {mode === 'overview' && (
                    <div className="grid items-start gap-4 lg:grid-cols-3">
                        <div
                            data-overview-column="identification"
                            className="flex min-w-0 flex-col gap-4 lg:col-span-2"
                        >
                            <DetailSection
                                icon={PackageSearch}
                                title="Identification"
                                group="overview"
                            >
                                <DetailField
                                    label="Property number"
                                    value={asset.property_number}
                                />
                                <DetailField
                                    label="Old property number"
                                    value={asset.old_property_number}
                                />
                                <DetailField
                                    label="Serial number"
                                    value={asset.serial_number}
                                />
                                <DetailField
                                    label="Subcategory"
                                    value={asset.subcategory?.name}
                                />
                                <DetailField
                                    label="Category"
                                    value={asset.category?.name}
                                />
                                <DetailField
                                    label="Brand"
                                    value={asset.brand}
                                />
                                <DetailField
                                    label="Manufacturer"
                                    value={asset.manufacturer}
                                />
                                <DetailField
                                    label="Model"
                                    value={asset.model}
                                />
                                <DetailField
                                    label="Unit of measure"
                                    value={asset.unit_of_measure}
                                />
                                <DetailField
                                    label="Fund cluster"
                                    value={asset.fund_cluster}
                                />
                                <DetailField
                                    label="Description"
                                    value={asset.description}
                                    fullWidth
                                />
                                <DetailField
                                    label="Technical specifications"
                                    value={asset.specifications}
                                    fullWidth
                                />
                            </DetailSection>
                            <DetailSection
                                icon={ClipboardList}
                                title="Acquisition source documents"
                                group="overview"
                                secondary
                            >
                                <DetailField
                                    label="Acquisition method"
                                    value={
                                        asset.acquisition_method
                                            ? formatLabel(
                                                  asset.acquisition_method,
                                              )
                                            : null
                                    }
                                />
                                <DetailField
                                    label="Supplier / source"
                                    value={asset.supplier_name}
                                />
                                <DetailField
                                    label="Purchase order"
                                    value={asset.purchase_order_no}
                                />
                                <DetailField
                                    label="IAR number"
                                    value={
                                        asset.inspection_acceptance_report_no
                                    }
                                />
                                <DetailField
                                    label="Invoice number"
                                    value={asset.invoice_no}
                                />
                                <DetailField
                                    label="Disbursement voucher"
                                    value={asset.disbursement_voucher_no}
                                />
                                <DetailField
                                    label="Delivery date"
                                    value={formatDate(asset.delivery_date)}
                                />
                                <DetailField
                                    label="Inspection date"
                                    value={formatDate(asset.inspection_date)}
                                />
                                <DetailField
                                    label="Acceptance date"
                                    value={formatDate(asset.acceptance_date)}
                                />
                            </DetailSection>
                        </div>
                        <div
                            data-overview-column="custody"
                            className="flex min-w-0 flex-col gap-4"
                        >
                            <DetailSection
                                icon={MapPin}
                                title="Location and custody"
                                group="overview"
                            >
                                <DetailField
                                    label="Lifecycle"
                                    value={formatLabel(asset.lifecycle_status)}
                                />
                                <DetailField
                                    label="Physical condition"
                                    value={formatLabel(asset.condition_status)}
                                />
                                <DetailField
                                    label="Custody state"
                                    value={formatLabel(asset.custody_status)}
                                />
                                <DetailField
                                    label="Location"
                                    value={asset.location}
                                />
                                <DetailField
                                    label="Nature of occupancy"
                                    value={asset.nature_of_occupancy}
                                />
                                <DetailField
                                    label="Current custodian"
                                    value={asset.current_custodian?.name}
                                />
                                <DetailField
                                    label="Custodian code"
                                    value={asset.current_custodian?.code}
                                />
                            </DetailSection>
                            <DetailSection
                                icon={ClipboardList}
                                title="Physical count"
                                group="overview"
                                secondary
                            >
                                <DetailField
                                    label="Quantity per property card"
                                    value={asset.quantity_per_property_card}
                                />
                                <DetailField
                                    label="Quantity per physical count"
                                    value={asset.quantity_per_physical_count}
                                />
                                <DetailField
                                    label="Physical count remarks"
                                    value={asset.physical_count_remarks}
                                    fullWidth
                                />
                            </DetailSection>
                            {hasDisposalOrLossInformation && (
                                <DetailSection
                                    icon={FileWarning}
                                    title="Disposal or loss"
                                    group="overview"
                                >
                                    <DetailField
                                        label="Disposal method"
                                        value={asset.disposal_method}
                                    />
                                    <DetailField
                                        label="Disposal value"
                                        value={formatCurrency(
                                            asset.disposal_value,
                                        )}
                                    />
                                    <DetailField
                                        label="Loss type"
                                        value={
                                            asset.loss_type
                                                ? formatLabel(asset.loss_type)
                                                : null
                                        }
                                    />
                                    <DetailField
                                        label="Loss report number"
                                        value={asset.loss_report_no}
                                    />
                                    <DetailField
                                        label="Loss report date"
                                        value={formatDate(
                                            asset.loss_report_date,
                                        )}
                                    />
                                    <DetailField
                                        label="Loss circumstances"
                                        value={asset.loss_circumstances}
                                        fullWidth
                                    />
                                </DetailSection>
                            )}
                            <DetailSection
                                icon={UserRound}
                                title="Record information"
                                group="overview"
                                secondary
                            >
                                <DetailField
                                    label="Created"
                                    value={formatDate(asset.created_at, true)}
                                />
                                <DetailField
                                    label="Last updated"
                                    value={formatDate(asset.updated_at, true)}
                                />
                            </DetailSection>
                        </div>
                    </div>
                )}

                {!isPpe && (
                    <DetailSection
                        icon={UserRound}
                        title="ICS accountability"
                        group="custody"
                    >
                        <DetailField
                            label="Value class"
                            value={
                                asset.semi_expendable_value_class
                                    ? `${formatLabel(asset.semi_expendable_value_class)}-valued`
                                    : null
                            }
                        />
                        <DetailField
                            label="Accounting state"
                            value={formatLabel(asset.accounting_status)}
                        />
                        <DetailField
                            label="Physical state"
                            value={
                                asset.physical_status
                                    ? formatLabel(asset.physical_status)
                                    : null
                            }
                        />
                        <DetailField
                            label="Accountability state"
                            value={
                                asset.accountability_status
                                    ? formatLabel(asset.accountability_status)
                                    : null
                            }
                        />
                        <DetailField
                            label="Current ICS"
                            value={asset.latest_ics_document?.document_no}
                        />
                        <DetailField
                            label="ICS renewal due"
                            value={formatDate(
                                asset.latest_ics_document?.renewal_due_at ??
                                    null,
                            )}
                        />
                        <DetailField
                            label="Accountability expiration"
                            value={formatDate(asset.accountability_expires_at)}
                        />
                        <DetailField
                            label="Relief state"
                            value={
                                asset.relief_status
                                    ? formatLabel(asset.relief_status)
                                    : null
                            }
                        />
                        {asset.semi_expendable_value_class === 'high' && (
                            <DetailField
                                label="High-value control"
                                value="Accountability does not automatically end when estimated useful life expires. Return or approved relief is required."
                                fullWidth
                            />
                        )}
                    </DetailSection>
                )}

                {asset.active_borrowing && (
                    <DetailSection
                        icon={CalendarClock}
                        title="Active borrowing"
                        group="custody"
                    >
                        <DetailField label="Borrower" value={activeBorrower} />
                        <DetailField
                            label="Borrowed at"
                            value={formatDate(
                                asset.active_borrowing.borrowed_at,
                                true,
                            )}
                        />
                        <DetailField
                            label="Due at"
                            value={formatDate(
                                asset.active_borrowing.due_at,
                                true,
                            )}
                        />
                        <DetailField
                            label="Purpose / notes"
                            value={asset.active_borrowing.notes}
                        />
                    </DetailSection>
                )}

                <DetailSection
                    icon={Calculator}
                    title="Acquisition and valuation"
                    group="accounting"
                >
                    <div
                        data-accounting-column="depreciation"
                        className="flex min-w-0 flex-col gap-4 lg:col-span-2"
                    >
                        {isPpe && (
                            <AccountingGroup title="Depreciation">
                                <DetailField
                                    label="Available-for-use date"
                                    value={formatDate(
                                        asset.available_for_use_date,
                                    )}
                                />
                                <DetailField
                                    label="Useful life"
                                    value={`${asset.depreciation_useful_life_months} months`}
                                />
                                {isPpe && (
                                    <DetailField
                                        label="Depreciation method"
                                        value={
                                            asset.depreciation_method
                                                ? formatLabel(
                                                      asset.depreciation_method,
                                                  )
                                                : null
                                        }
                                    />
                                )}
                                {isPpe && (
                                    <DetailField
                                        label="Residual value percentage"
                                        value={`${asset.residual_value_percentage}%`}
                                    />
                                )}
                                {isPpe && (
                                    <DetailField
                                        label="Residual value"
                                        value={formatCurrency(
                                            asset.residual_value,
                                        )}
                                    />
                                )}
                                {isPpe && (
                                    <DetailField
                                        label="Residual value basis"
                                        value={asset.residual_value_basis}
                                        fullWidth
                                    />
                                )}
                                {isPpe && (
                                    <DetailField
                                        label="Monthly depreciation"
                                        value={formatCurrency(
                                            asset.monthly_depreciation,
                                        )}
                                    />
                                )}
                                {isPpe && (
                                    <DetailField
                                        label="Depreciation to date"
                                        value={formatCurrency(
                                            asset.depreciation_amount,
                                        )}
                                    />
                                )}
                                {isPpe && (
                                    <DetailField
                                        label="Fully depreciated"
                                        value={
                                            asset.is_fully_depreciated
                                                ? 'Yes'
                                                : 'No'
                                        }
                                    />
                                )}
                            </AccountingGroup>
                        )}
                        <AccountingGroup title="Accounting policy and status">
                            <DetailField
                                label="Accounting classification"
                                value={formatLabel(
                                    asset.accounting_classification,
                                )}
                            />
                            <DetailField
                                label="Accounting status"
                                value={formatLabel(asset.accounting_status)}
                            />
                            <DetailField
                                label="Policy version"
                                value={asset.accounting_policy_version}
                            />
                            <DetailField
                                label="Capitalization threshold applied"
                                value={formatCurrency(
                                    asset.capitalization_threshold_applied,
                                )}
                            />
                            <DetailField
                                label="Derecognized date"
                                value={formatDate(asset.derecognized_at)}
                            />
                        </AccountingGroup>
                    </div>{' '}
                    <div
                        data-accounting-column="acquisition"
                        className="flex min-w-0 flex-col gap-4"
                    >
                        <AccountingGroup title="Acquisition details">
                            <DetailField
                                label="Acquisition date"
                                value={formatDate(asset.acquisition_date)}
                            />
                            <DetailField
                                label="Acquisition cost"
                                value={formatCurrency(asset.acquisition_cost)}
                            />
                            <DetailField
                                label="Directly attributable costs"
                                value={formatCurrency(
                                    asset.directly_attributable_costs,
                                )}
                            />
                            {isPpe && (
                                <DetailField
                                    label="Gross carrying amount"
                                    value={formatCurrency(
                                        asset.gross_carrying_amount,
                                    )}
                                />
                            )}
                            {!isPpe && (
                                <>
                                    <DetailField
                                        label="Available-for-use date"
                                        value={formatDate(
                                            asset.available_for_use_date,
                                        )}
                                    />
                                    <DetailField
                                        label="Useful life"
                                        value={`${asset.depreciation_useful_life_months} months`}
                                    />
                                </>
                            )}
                        </AccountingGroup>
                        <AccountingGroup title="Current valuation">
                            {isPpe && (
                                <DetailField
                                    label="Current book value"
                                    value={formatCurrency(asset.book_value)}
                                />
                            )}
                            {isPpe && (
                                <DetailField
                                    label="Impairment losses"
                                    value={formatCurrency(
                                        asset.impairment_losses,
                                    )}
                                />
                            )}
                            <DetailField
                                label="Appraised value"
                                value={formatCurrency(asset.appraised_value)}
                            />
                            <DetailField
                                label="Appraisal date"
                                value={formatDate(asset.appraisal_date)}
                            />
                        </AccountingGroup>
                    </div>
                </DetailSection>
            </div>
        </DetailsMode.Provider>
    );
}

export function AssetActivity({ events }: { events: InventoryAssetEvent[] }) {
    return (
        <PropertyHistory
            title="Lifecycle timeline"
            columns={['Event', 'Date', 'Reference', 'Summary', 'Recorded by']}
            emptyMessage="No lifecycle events recorded."
            records={events.map((event) => ({
                id: event.id,
                status: event.event_type,
                search: [
                    event.event_type,
                    formatLabel(event.event_type),
                    event.event_date,
                    event.reference_no,
                    event.notes,
                    event.recorded_by?.name,
                    event.from_location,
                    event.to_location,
                ]
                    .filter(Boolean)
                    .join(' '),
                cells: [
                    <strong key="type">{formatLabel(event.event_type)}</strong>,
                    formatDate(event.event_date),
                    event.reference_no ?? 'Not recorded',
                    <p key="notes" className="line-clamp-2 max-w-sm">
                        {event.notes ?? 'Recorded transaction'}
                    </p>,
                    event.recorded_by?.name ?? 'Not recorded',
                ],
                details: (
                    <>
                        {event.notes && <p>{event.notes}</p>}
                        {event.amount !== null && (
                            <p>
                                <strong>Amount:</strong>{' '}
                                {formatCurrency(event.amount)}
                            </p>
                        )}
                        {(event.from_location || event.to_location) && (
                            <p>
                                <strong>Movement:</strong>{' '}
                                {event.from_location ?? 'Not recorded'} to{' '}
                                {event.to_location ?? 'Not recorded'}
                            </p>
                        )}
                        {event.quantity !== null && (
                            <p>
                                <strong>Quantity:</strong> {event.quantity}
                            </p>
                        )}
                        {event.condition_status && (
                            <p>
                                <strong>Condition:</strong>{' '}
                                {formatLabel(event.condition_status)}
                            </p>
                        )}
                        {event.details && (
                            <EventDetails details={event.details} />
                        )}
                    </>
                ),
            }))}
        />
    );
}
