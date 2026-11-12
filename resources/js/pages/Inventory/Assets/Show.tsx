import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    AssetActivity,
    AssetDetails,
    formatCurrency,
    formatDate,
    formatLabel,
} from '@/components/asset-details';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import { PageContainer } from '@/components/page-container';
import { PropertyHistory } from '@/components/property-history';
import { RecordChangeBadge } from '@/components/record-change-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
    DialogFooter,
} from '@/components/ui/dialog';
import { FormSelect } from '@/components/ui/form-select';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    index,
    show,
    property_tag,
    lapsing_schedule,
} from '@/routes/inventory/assets';
import {
    index as semiExpendableIndex,
    show as showSemiExpendable,
} from '@/routes/inventory/semi_expendable';
import type { HrisReference, InventoryAsset } from '@/types/inventory';

type PropertyDetails = InventoryAsset & {
    borrowings: {
        inventory_asset_borrowing_id: number;
        borrower_reference: HrisReference | null;
        borrower_name: string | null;
        status: string;
        borrowed_at: string;
        due_at: string | null;
        returned_at: string | null;
        notes: string | null;
        return_notes: string | null;
    }[];
    accountability_documents: {
        id: number;
        document_no: string;
        document_type: string;
        recipient_name: string;
        issued_at: string;
        acknowledged_at: string | null;
        closed_at: string | null;
        status: string;
        closure_reason: string | null;
    }[];
};

const tabs = ['overview', 'custody', 'accounting', 'activity'] as const;
type PropertyTab = (typeof tabs)[number];

function KeyFact({
    label,
    value,
    important = false,
}: {
    label: string;
    value: React.ReactNode;
    important?: boolean;
}) {
    return (
        <div
            data-priority={important ? 'primary' : 'secondary'}
            className={
                important
                    ? 'rounded-xl border border-primary/25 bg-primary/5 p-4'
                    : 'rounded-xl border bg-card p-4'
            }
        >
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={
                    important
                        ? 'mt-1 text-2xl font-semibold tracking-tight'
                        : 'mt-1 text-base font-semibold'
                }
            >
                {value ?? 'Not recorded'}
            </p>
        </div>
    );
}

function LapsingScheduleDialog({
    asset,
    employees,
    reportYear,
}: {
    asset: PropertyDetails;
    employees: HrisReference[];
    reportYear: number;
}) {
    const [preparedBy, setPreparedBy] = useState('');
    const [certifiedBy, setCertifiedBy] = useState('');
    const firstYear = Math.min(
        Number(asset.acquisition_date.slice(0, 4)),
        reportYear,
    );

    return (
        <Dialog>
            <DialogTrigger render={<Button variant="outline" />}>
                Lapsing schedule report
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>PPE lapsing schedule</DialogTitle>
                    <DialogDescription>
                        Select a year and two employees to sign the report. The
                        report opens in a new tab for printing.
                    </DialogDescription>
                </DialogHeader>
                <form
                    action={lapsing_schedule.url(asset)}
                    method="get"
                    target="_blank"
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="lapsing-year">Year</Label>
                        <FormSelect
                            id="lapsing-year"
                            name="year"
                            defaultValue={String(reportYear)}
                            required
                        >
                            {Array.from(
                                { length: reportYear - firstYear + 2 },
                                (_, index) => firstYear + index,
                            )
                                .reverse()
                                .map((year) => (
                                    <option key={year} value={year}>
                                        {year}
                                    </option>
                                ))}
                        </FormSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lapsing-prepared">Prepared by</Label>
                        <HrisReferenceCombobox
                            id="lapsing-prepared"
                            name="prepared_by"
                            references={employees}
                            value={preparedBy}
                            onValueChange={setPreparedBy}
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lapsing-certified">Certified by</Label>
                        <HrisReferenceCombobox
                            id="lapsing-certified"
                            name="certified_by"
                            references={employees}
                            value={certifiedBy}
                            onValueChange={setCertifiedBy}
                            required
                        />
                    </div>
                    {preparedBy && preparedBy === certifiedBy && (
                        <p role="alert" className="text-sm text-destructive">
                            Select two different employees.
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={
                                !preparedBy ||
                                !certifiedBy ||
                                preparedBy === certifiedBy
                            }
                        >
                            Generate report
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function PropertyShow({
    asset,
    employees = [],
    reportYear,
}: {
    asset: PropertyDetails;
    employees?: HrisReference[];
    reportYear: number;
}) {
    const page = usePage();
    const requestedTab = new URL(page.url, 'http://localhost').searchParams.get(
        'tab',
    );
    const tab: PropertyTab = tabs.includes(requestedTab as PropertyTab)
        ? (requestedTab as PropertyTab)
        : 'overview';
    const isPpe = asset.accounting_classification === 'ppe';
    const registryOptions = asset.deleted_at
        ? { query: { records: 'archived' } }
        : undefined;
    const registry = isPpe
        ? index(registryOptions)
        : semiExpendableIndex(registryOptions);
    const registryLabel = isPpe ? 'PPE' : 'Semi-Expendable Property';
    const auth = page.props.auth as
        { permissions?: { manage_inventory?: boolean } } | undefined;
    const activeDocument = asset.accountability_documents.find((document) =>
        ['active', 'pending_note', 'pending_recipient'].includes(
            document.status,
        ),
    );
    const currentBorrower =
        asset.active_borrowing?.borrower_reference?.name ??
        asset.active_borrowing?.borrower_name;
    const warning = asset.deleted_at
        ? 'This property record is archived.'
        : ['lost', 'for_disposal', 'disposed', 'transferred_out'].includes(
                asset.lifecycle_status,
            ) ||
            ['non_usable', 'defective', 'needs_repair'].includes(
                asset.condition_status,
            )
          ? 'This property needs attention. Review its condition and lifecycle records before taking action.'
          : null;
    setLayoutProps({
        breadcrumbs: [
            { title: registryLabel, href: registry },
            { title: asset.property_number ?? asset.item_description },
        ],
    });
    function changeTab(value: unknown) {
        if (!tabs.includes(value as PropertyTab)) {
            return;
        }

        const target = isPpe ? show : showSemiExpendable;
        router.push({
            url: target.url(asset, { query: { tab: String(value) } }),
            preserveState: true,
            preserveScroll: false,
        });
    }

    return (
        <>
            <Head title={`${asset.item_description} · ${registryLabel}`} />
            <PageContainer className="gap-4">
                <Link
                    href={registry}
                    className="w-fit text-sm underline underline-offset-4"
                >
                    Back to {registryLabel}
                </Link>
                <Tabs value={tab} onValueChange={changeTab} className="gap-5">
                    <div className="sticky top-0 z-20 -mx-1 space-y-3 rounded-xl border bg-background/95 p-4 shadow-sm backdrop-blur-sm">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                    <span className="font-mono text-sm text-muted-foreground">
                                        {asset.property_number ??
                                            'No property number'}
                                    </span>
                                    <Badge variant="outline">
                                        {registryLabel}
                                    </Badge>
                                    <Badge
                                        variant={
                                            asset.lifecycle_status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {formatLabel(asset.lifecycle_status)}
                                    </Badge>
                                    <Badge
                                        variant={
                                            [
                                                'non_usable',
                                                'defective',
                                                'needs_repair',
                                            ].includes(asset.condition_status)
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {formatLabel(asset.condition_status)}
                                    </Badge>
                                </div>
                                <h1 className="text-xl font-semibold sm:text-2xl">
                                    {asset.item_description}
                                    <RecordChangeBadge
                                        collection="asset"
                                        record={asset}
                                    />
                                </h1>
                            </div>
                            {auth?.permissions?.manage_inventory &&
                                !asset.deleted_at && (
                                    <Button
                                        variant="outline"
                                        nativeButton={false}
                                        render={
                                            <a
                                                href={property_tag.url(asset)}
                                                target="_blank"
                                                rel="noreferrer"
                                            />
                                        }
                                    >
                                        Print property tag
                                    </Button>
                                )}
                        </div>
                        <dl className="grid grid-cols-3 gap-x-3 gap-y-2 text-sm sm:gap-x-5">
                            <div>
                                <dt className="text-muted-foreground">
                                    Current custodian
                                </dt>
                                <dd className="font-semibold">
                                    {asset.current_custodian?.name ??
                                        'Unassigned'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Location
                                </dt>
                                <dd className="font-semibold">
                                    {asset.location ?? 'Not recorded'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {isPpe ? 'Current book value' : 'Unit cost'}
                                </dt>
                                <dd className="font-semibold">
                                    {formatCurrency(
                                        isPpe
                                            ? asset.book_value
                                            : asset.acquisition_cost,
                                    )}
                                </dd>
                            </div>
                        </dl>
                        <TabsList className="h-auto w-full flex-wrap gap-1 bg-muted/60 sm:flex-nowrap">
                            <TabsTrigger
                                value="overview"
                                className="min-h-10 flex-1"
                            >
                                Overview
                            </TabsTrigger>
                            <TabsTrigger
                                value="custody"
                                className="min-h-10 flex-1"
                            >
                                Borrowing &amp; Accountability
                            </TabsTrigger>
                            <TabsTrigger
                                value="accounting"
                                className="min-h-10 flex-1"
                            >
                                Accounting
                            </TabsTrigger>
                            <TabsTrigger
                                value="activity"
                                className="min-h-10 flex-1"
                            >
                                Activity{' '}
                                <span className="ml-1 text-xs">
                                    ({asset.events.length})
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </div>
                    {warning && (
                        <p
                            role="status"
                            className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-base font-medium text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                        >
                            {warning}
                        </p>
                    )}
                    <TabsContent value="overview" className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <KeyFact
                                label="Current custody"
                                value={
                                    currentBorrower
                                        ? `Borrowed by ${currentBorrower}`
                                        : formatLabel(asset.custody_status)
                                }
                                important
                            />
                            <KeyFact
                                label="Acquired"
                                value={formatDate(asset.acquisition_date)}
                            />
                            <KeyFact
                                label="Physical condition"
                                value={formatLabel(asset.condition_status)}
                            />
                        </div>
                        <AssetDetails asset={asset} mode="overview" />
                    </TabsContent>
                    <TabsContent value="custody" className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <KeyFact
                                label="Accountable officer"
                                value={
                                    asset.current_custodian?.name ??
                                    'Unassigned'
                                }
                                important
                            />
                            <KeyFact
                                label="Current accountability document"
                                value={
                                    activeDocument?.document_no ??
                                    'No active document'
                                }
                            />
                            <KeyFact
                                label="Accountability status"
                                value={formatLabel(
                                    activeDocument?.status ??
                                        asset.accountability_status ??
                                        asset.custody_status,
                                )}
                            />
                        </div>
                        {asset.active_borrowing && (
                            <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                                <h2 className="text-lg font-semibold">
                                    Currently borrowed by{' '}
                                    {currentBorrower ?? 'Not recorded'}
                                </h2>
                                <p className="mt-1 text-base">
                                    Due{' '}
                                    {formatDate(asset.active_borrowing.due_at)}{' '}
                                    · Borrowed{' '}
                                    {formatDate(
                                        asset.active_borrowing.borrowed_at,
                                    )}
                                </p>
                            </div>
                        )}
                        <div className="grid items-start gap-4 xl:grid-cols-2">
                            {' '}
                            <div className="min-w-0">
                                <PropertyHistory
                                    title="Accountability history"
                                    columns={[
                                        'Document',
                                        'Recipient',
                                        'Status',
                                        'Issued',
                                    ]}
                                    emptyMessage="No accountability documents for this property."
                                    records={asset.accountability_documents.map(
                                        (document) => ({
                                            id: document.id,
                                            status: document.status,
                                            search: [
                                                document.document_no,
                                                document.document_type,
                                                document.recipient_name,
                                                document.status,
                                                document.closure_reason,
                                                document.issued_at,
                                            ].join(' '),
                                            cells: [
                                                <strong key="document">
                                                    {document.document_no}
                                                </strong>,
                                                document.recipient_name,
                                                <Badge
                                                    key="status"
                                                    variant={
                                                        document.status ===
                                                        'pending_recipient'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {formatLabel(
                                                        document.status,
                                                    )}
                                                </Badge>,
                                                formatDate(document.issued_at),
                                            ],
                                            details: (
                                                <dl className="space-y-2">
                                                    <div>
                                                        <dt className="font-medium">
                                                            Acknowledged
                                                        </dt>
                                                        <dd>
                                                            {formatDate(
                                                                document.acknowledged_at,
                                                            )}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt className="font-medium">
                                                            Closed
                                                        </dt>
                                                        <dd>
                                                            {formatDate(
                                                                document.closed_at,
                                                            )}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt className="font-medium">
                                                            Closure reason
                                                        </dt>
                                                        <dd>
                                                            {document.closure_reason ??
                                                                'Not recorded'}
                                                        </dd>
                                                    </div>
                                                </dl>
                                            ),
                                        }),
                                    )}
                                />
                            </div>
                            <div className="min-w-0">
                                <PropertyHistory
                                    title="Borrowing history"
                                    columns={[
                                        'Borrower',
                                        'Status',
                                        'Borrowed',
                                        'Due',
                                        'Returned',
                                    ]}
                                    emptyMessage="No borrowing records for this property."
                                    records={asset.borrowings.map(
                                        (borrowing) => ({
                                            id: borrowing.inventory_asset_borrowing_id,
                                            status: borrowing.status,
                                            search: [
                                                borrowing.borrower_reference
                                                    ?.name,
                                                borrowing.borrower_reference
                                                    ?.code,
                                                borrowing.borrower_name,
                                                borrowing.status,
                                                borrowing.notes,
                                                borrowing.return_notes,
                                                borrowing.borrowed_at,
                                                borrowing.returned_at,
                                            ].join(' '),
                                            cells: [
                                                <strong key="borrower">
                                                    {borrowing
                                                        .borrower_reference
                                                        ?.name ??
                                                        borrowing.borrower_name ??
                                                        'Not recorded'}
                                                </strong>,
                                                <Badge
                                                    key="status"
                                                    variant={
                                                        borrowing.status ===
                                                        'borrowed'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {formatLabel(
                                                        borrowing.status,
                                                    )}
                                                </Badge>,
                                                formatDate(
                                                    borrowing.borrowed_at,
                                                ),
                                                formatDate(borrowing.due_at),
                                                formatDate(
                                                    borrowing.returned_at,
                                                ),
                                            ],
                                            details: (
                                                <>
                                                    <p>
                                                        <strong>
                                                            Borrowing notes:
                                                        </strong>{' '}
                                                        {borrowing.notes ??
                                                            'Not recorded'}
                                                    </p>
                                                    <p>
                                                        <strong>
                                                            Return notes:
                                                        </strong>{' '}
                                                        {borrowing.return_notes ??
                                                            'Not recorded'}
                                                    </p>
                                                </>
                                            ),
                                        }),
                                    )}
                                />
                            </div>
                        </div>
                        <details className="rounded-xl border bg-card p-4">
                            <summary className="cursor-pointer text-base font-medium">
                                Current custody and control details
                            </summary>
                            <div className="mt-4">
                                <AssetDetails asset={asset} mode="custody" />
                            </div>
                        </details>
                    </TabsContent>
                    <TabsContent value="accounting" className="space-y-4">
                        {isPpe && (
                            <div className="flex justify-end">
                                <LapsingScheduleDialog
                                    asset={asset}
                                    employees={employees}
                                    reportYear={reportYear}
                                />
                            </div>
                        )}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <KeyFact
                                label={
                                    isPpe ? 'Current book value' : 'Unit cost'
                                }
                                value={formatCurrency(
                                    isPpe
                                        ? asset.book_value
                                        : asset.acquisition_cost,
                                )}
                                important
                            />
                            <KeyFact
                                label={
                                    isPpe
                                        ? 'Depreciation to date'
                                        : 'Accounting status'
                                }
                                value={
                                    isPpe
                                        ? formatCurrency(
                                              asset.depreciation_amount,
                                          )
                                        : formatLabel(asset.accounting_status)
                                }
                            />
                            <KeyFact
                                label={
                                    isPpe
                                        ? 'Gross carrying amount'
                                        : 'Value class'
                                }
                                value={
                                    isPpe
                                        ? formatCurrency(
                                              asset.gross_carrying_amount,
                                          )
                                        : formatLabel(
                                              asset.semi_expendable_value_class ??
                                                  'Not recorded',
                                          )
                                }
                            />
                        </div>
                        {!isPpe && (
                            <p className="rounded-lg border bg-muted/30 p-4 text-base">
                                Semi-expendable property is not depreciated.
                                Accountability tracking continues after issuance
                                and expensing.
                            </p>
                        )}
                        <AssetDetails asset={asset} mode="accounting" />
                    </TabsContent>
                    <TabsContent value="activity" className="space-y-4">
                        <h2 className="text-lg font-semibold">
                            Transaction history{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                · newest first
                            </span>
                        </h2>
                        <AssetActivity events={asset.events} />
                    </TabsContent>
                </Tabs>
            </PageContainer>
        </>
    );
}
