import {
    Calculator,
    CalendarClock,
    ClipboardList,
    FileWarning,
    History,
    MapPin,
    PackageSearch,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { InventoryAsset } from '@/types/inventory';

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

function formatCurrency(value: number | string | null): string {
    if (value === null || value === '') {
        return 'Not provided';
    }

    return currencyFormatter.format(Number(value));
}

function formatDate(value: string | null, includeTime = false): string {
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

function formatLabel(value: string): string {
    return value
        .split(/[-_]/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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
    return (
        <div className={fullWidth ? 'sm:col-span-2' : undefined}>
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-sm break-words text-foreground">
                {value === null || value === '' ? 'Not provided' : value}
            </dd>
        </div>
    );
}

function DetailSection({
    icon: Icon,
    title,
    children,
}: {
    icon: typeof PackageSearch;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-card">
            <div className="flex items-center gap-2 border-b bg-muted/30 px-4 py-3">
                <Icon className="size-4 text-muted-foreground" />
                <h3 className="font-medium">{title}</h3>
            </div>
            <dl className="grid gap-x-6 gap-y-4 p-4 sm:grid-cols-2">
                {children}
            </dl>
        </section>
    );
}

export function AssetDetailsDialog({
    asset,
    open,
    onOpenChange,
}: {
    asset: InventoryAsset;
    open: boolean;
    onOpenChange: (open: boolean) => void;
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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] gap-0 overflow-hidden p-0 sm:max-w-4xl">
                <DialogHeader className="border-b bg-muted/30 p-5 pr-14 sm:p-6 sm:pr-16">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                asset.lifecycle_status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {formatLabel(asset.lifecycle_status)}
                        </Badge>
                        <Badge variant="outline">
                            {formatLabel(asset.condition_status)}
                        </Badge>
                        <Badge variant="outline">
                            {formatLabel(asset.accounting_classification)}
                        </Badge>
                        <Badge variant="outline">
                            {formatLabel(asset.accounting_status)}
                        </Badge>
                        <Badge
                            variant={
                                asset.custody_status === 'borrowed'
                                    ? 'destructive'
                                    : 'outline'
                            }
                        >
                            {formatLabel(asset.custody_status)}
                        </Badge>
                        <span className="font-mono text-xs text-muted-foreground">
                            {asset.property_number ?? asset.serial_number}
                        </span>
                    </div>
                    <DialogTitle className="text-xl">{asset.name}</DialogTitle>
                    <DialogDescription>
                        {asset.category?.name ?? 'Uncategorized'}
                        {[asset.brand, asset.model].filter(Boolean).length > 0
                            ? ` · ${[asset.brand, asset.model].filter(Boolean).join(' ')}`
                            : ''}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[calc(90vh-9rem)] overflow-y-auto p-4 sm:p-6">
                    <div className="grid gap-4">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    {isPpe
                                        ? 'Gross carrying amount'
                                        : 'Individual unit cost'}
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {formatCurrency(
                                        isPpe
                                            ? asset.gross_carrying_amount
                                            : asset.acquisition_cost,
                                    )}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    {isPpe
                                        ? 'Depreciation to date'
                                        : 'Value class'}
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {isPpe && asset.is_depreciable
                                        ? formatCurrency(
                                              asset.depreciation_amount,
                                          )
                                        : asset.semi_expendable_value_class
                                          ? `${formatLabel(asset.semi_expendable_value_class)}-valued`
                                          : 'Needs review'}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-primary/5 p-4 ring-1 ring-primary/15">
                                <p className="text-xs text-muted-foreground">
                                    {isPpe
                                        ? 'Current book value'
                                        : 'Accountability'}
                                </p>
                                <p className="mt-1 text-lg font-semibold text-primary">
                                    {isPpe
                                        ? formatCurrency(asset.book_value)
                                        : asset.accountability_status
                                          ? formatLabel(
                                                asset.accountability_status,
                                            )
                                          : 'Unassigned'}
                                </p>
                            </div>
                        </div>

                        <DetailSection
                            icon={PackageSearch}
                            title="Identification"
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
                            <DetailField label="Brand" value={asset.brand} />
                            <DetailField
                                label="Manufacturer"
                                value={asset.manufacturer}
                            />
                            <DetailField label="Model" value={asset.model} />
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

                        {!isPpe && (
                            <DetailSection
                                icon={UserRound}
                                title="ICS accountability"
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
                                            ? formatLabel(
                                                  asset.accountability_status,
                                              )
                                            : null
                                    }
                                />
                                <DetailField
                                    label="Current ICS"
                                    value={
                                        asset.latest_ics_document?.document_no
                                    }
                                />
                                <DetailField
                                    label="ICS renewal due"
                                    value={formatDate(
                                        asset.latest_ics_document
                                            ?.renewal_due_at ?? null,
                                    )}
                                />
                                <DetailField
                                    label="Accountability expiration"
                                    value={formatDate(
                                        asset.accountability_expires_at,
                                    )}
                                />
                                <DetailField
                                    label="Relief state"
                                    value={
                                        asset.relief_status
                                            ? formatLabel(asset.relief_status)
                                            : null
                                    }
                                />
                                {asset.semi_expendable_value_class ===
                                    'high' && (
                                    <DetailField
                                        label="High-value control"
                                        value="Accountability does not automatically end when estimated useful life expires. Return or approved relief is required."
                                        fullWidth
                                    />
                                )}
                            </DetailSection>
                        )}

                        <DetailSection
                            icon={MapPin}
                            title="Location and custody"
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

                        {asset.active_borrowing && (
                            <DetailSection
                                icon={CalendarClock}
                                title="Active borrowing"
                            >
                                <DetailField
                                    label="Borrower"
                                    value={activeBorrower}
                                />
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
                        >
                            <DetailField
                                label="Acquisition date"
                                value={formatDate(asset.acquisition_date)}
                            />
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
                            <DetailField
                                label="Available-for-use date"
                                value={formatDate(asset.available_for_use_date)}
                            />
                            <DetailField
                                label="Useful life"
                                value={`${asset.depreciation_useful_life_months} months`}
                            />
                            {isPpe && (
                                <DetailField
                                    label="Residual value percentage"
                                    value={`${asset.residual_value_percentage}%`}
                                />
                            )}
                            {isPpe && (
                                <DetailField
                                    label="Residual value"
                                    value={formatCurrency(asset.residual_value)}
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
                                    label="Depreciation to date"
                                    value={formatCurrency(
                                        asset.depreciation_amount,
                                    )}
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
                                <>
                                    <DetailField
                                        label="Fully depreciated"
                                        value={
                                            asset.is_fully_depreciated
                                                ? 'Yes'
                                                : 'No'
                                        }
                                    />
                                    <DetailField
                                        label="Current book value"
                                        value={formatCurrency(asset.book_value)}
                                    />
                                    <DetailField
                                        label="Impairment losses"
                                        value={formatCurrency(
                                            asset.impairment_losses,
                                        )}
                                    />
                                </>
                            )}
                            <DetailField
                                label="Appraised value"
                                value={formatCurrency(asset.appraised_value)}
                            />
                            <DetailField
                                label="Appraisal date"
                                value={formatDate(asset.appraisal_date)}
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
                            <DetailField
                                label="Derecognized date"
                                value={formatDate(asset.derecognized_at)}
                            />
                        </DetailSection>

                        <DetailSection
                            icon={ClipboardList}
                            title="Acquisition source documents"
                        >
                            <DetailField
                                label="Acquisition method"
                                value={
                                    asset.acquisition_method
                                        ? formatLabel(asset.acquisition_method)
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
                                value={asset.inspection_acceptance_report_no}
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

                        <DetailSection
                            icon={ClipboardList}
                            title="Physical count"
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
                            >
                                <DetailField
                                    label="Disposal method"
                                    value={asset.disposal_method}
                                />
                                <DetailField
                                    label="Disposal value"
                                    value={formatCurrency(asset.disposal_value)}
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
                                    value={formatDate(asset.loss_report_date)}
                                />
                                <DetailField
                                    label="Loss circumstances"
                                    value={asset.loss_circumstances}
                                    fullWidth
                                />
                            </DetailSection>
                        )}

                        {asset.events.length > 0 && (
                            <DetailSection
                                icon={History}
                                title="Lifecycle timeline"
                            >
                                {asset.events.map((event) => (
                                    <DetailField
                                        key={event.id}
                                        label={`${formatDate(event.event_date)} · ${formatLabel(event.event_type)}`}
                                        value={
                                            <div className="space-y-1">
                                                <p>
                                                    {[
                                                        event.reference_no,
                                                        event.amount
                                                            ? formatCurrency(
                                                                  event.amount,
                                                              )
                                                            : null,
                                                        event.from_location &&
                                                        event.to_location
                                                            ? `${event.from_location} → ${event.to_location}`
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') ||
                                                        'Recorded transaction'}
                                                </p>
                                                {event.notes && (
                                                    <p className="text-muted-foreground">
                                                        {event.notes}
                                                    </p>
                                                )}
                                                {event.details &&
                                                    Object.keys(event.details)
                                                        .length > 0 && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {Object.entries(
                                                                event.details,
                                                            )
                                                                .filter(
                                                                    ([
                                                                        ,
                                                                        value,
                                                                    ]) =>
                                                                        value !==
                                                                            null &&
                                                                        value !==
                                                                            '',
                                                                )
                                                                .map(
                                                                    ([
                                                                        key,
                                                                        value,
                                                                    ]) =>
                                                                        `${formatLabel(key)}: ${typeof value === 'object' ? JSON.stringify(value) : String(value)}`,
                                                                )
                                                                .join(' · ')}
                                                        </p>
                                                    )}
                                                {event.recorded_by && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Recorded by{' '}
                                                        {event.recorded_by.name}
                                                    </p>
                                                )}
                                            </div>
                                        }
                                        fullWidth
                                    />
                                ))}
                            </DetailSection>
                        )}

                        <DetailSection
                            icon={UserRound}
                            title="Record information"
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
            </DialogContent>
        </Dialog>
    );
}
