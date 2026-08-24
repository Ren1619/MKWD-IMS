import {
    Calculator,
    CalendarClock,
    ClipboardList,
    FileWarning,
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
                                    Acquisition cost
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {formatCurrency(asset.acquisition_cost)}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-muted/20 p-4">
                                <p className="text-xs text-muted-foreground">
                                    Depreciation to date
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {formatCurrency(asset.depreciation_amount)}
                                </p>
                            </div>
                            <div className="rounded-xl border bg-primary/5 p-4 ring-1 ring-primary/15">
                                <p className="text-xs text-muted-foreground">
                                    Current book value
                                </p>
                                <p className="mt-1 text-lg font-semibold text-primary">
                                    {formatCurrency(asset.book_value)}
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
                                label="Serial number"
                                value={asset.serial_number}
                            />
                            <DetailField
                                label="Type / article"
                                value={asset.type}
                            />
                            <DetailField
                                label="Category"
                                value={asset.category?.name}
                            />
                            <DetailField label="Brand" value={asset.brand} />
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
                        </DetailSection>

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
                                label="Acquisition cost"
                                value={formatCurrency(asset.acquisition_cost)}
                            />
                            <DetailField
                                label="Useful life"
                                value={`${asset.depreciation_useful_life_months} months`}
                            />
                            <DetailField
                                label="Depreciation to date"
                                value={formatCurrency(
                                    asset.depreciation_amount,
                                )}
                            />
                            <DetailField
                                label="Current book value"
                                value={formatCurrency(asset.book_value)}
                            />
                            <DetailField
                                label="Impairment losses"
                                value={formatCurrency(asset.impairment_losses)}
                            />
                            <DetailField
                                label="Appraised value"
                                value={formatCurrency(asset.appraised_value)}
                            />
                            <DetailField
                                label="Appraisal date"
                                value={formatDate(asset.appraisal_date)}
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
