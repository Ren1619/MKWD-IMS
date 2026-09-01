import { Form, Head, Link } from '@inertiajs/react';
import {
    Archive,
    Boxes,
    Download,
    Landmark,
    PackageCheck,
    Printer,
    Search,
    TrendingDown,
} from 'lucide-react';
import { useState } from 'react';
import type { ChangeEventHandler } from 'react';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import { dashboard } from '@/routes';
import {
    exportMethod as exportReport,
    index,
    print as printReport,
} from '@/routes/inventory/reports';
import type {
    AssetStateOption,
    InventoryAsset,
    InventoryItem,
    InventoryReportFilters,
    InventoryReportOptions,
    InventoryReportSummary,
    Paginated,
} from '@/types/inventory';

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

const integer = new Intl.NumberFormat('en-PH');

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString();
}

function titleCase(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function FilterSelect({
    id,
    name,
    label,
    value,
    emptyLabel,
    options,
    onChange,
    error,
}: {
    id: string;
    name: string;
    label: string;
    value: string;
    emptyLabel: string;
    options: AssetStateOption[];
    onChange: ChangeEventHandler<HTMLSelectElement>;
    error?: string;
}) {
    return (
        <div className="grid w-full gap-1.5 sm:w-44">
            <label htmlFor={id} className="text-sm font-medium">
                {label}
            </label>
            <select
                id={id}
                name={name}
                defaultValue={value}
                className={selectClass}
                onChange={onChange}
            >
                <option value="">{emptyLabel}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

function ConsumableTable({ records }: { records: Paginated<InventoryItem> }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-border/70">
            <table className="w-full min-w-[980px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                <caption className="sr-only">
                    Consumable inventory valuation and replenishment report
                </caption>
                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th>Stock no.</th>
                        <th>Item and category</th>
                        <th className="text-right">On hand</th>
                        <th className="text-right">Reorder point</th>
                        <th className="text-right">Stock value</th>
                        <th>Next expiry</th>
                        <th>Attention</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {records.data.map((item) => (
                        <tr key={item.inventory_item_id}>
                            <td className="font-mono text-xs">
                                {item.stock_number ?? '—'}
                            </td>
                            <td>
                                <div className="font-medium">{item.name}</div>
                                <div className="text-xs text-muted-foreground">
                                    {item.series_category?.class_category
                                        ?.major_category?.name ??
                                        'Uncategorized'}{' '}
                                    /{' '}
                                    {item.series_category?.name ?? 'No series'}
                                </div>
                            </td>
                            <td className="text-right font-medium tabular-nums">
                                {integer.format(item.quantity)}{' '}
                                <span className="font-normal text-muted-foreground">
                                    {item.unit_of_measure}
                                </span>
                            </td>
                            <td className="text-right tabular-nums">
                                {integer.format(item.reorder_point)}
                            </td>
                            <td className="text-right font-medium tabular-nums">
                                {currency.format(Number(item.inventory_value))}
                            </td>
                            <td className="text-muted-foreground">
                                {item.next_expiration_date
                                    ? formatDate(item.next_expiration_date)
                                    : 'No expiry'}
                            </td>
                            <td>
                                <div className="flex flex-wrap gap-1">
                                    {item.is_low_stock && (
                                        <Badge variant="destructive">
                                            Low stock
                                        </Badge>
                                    )}
                                    {item.expiration_status === 'expired' && (
                                        <Badge variant="destructive">
                                            Expired stock
                                        </Badge>
                                    )}
                                    {item.expiration_status === 'expiring' && (
                                        <Badge
                                            variant="outline"
                                            className="border-amber-500/50 text-amber-700 dark:text-amber-400"
                                        >
                                            Expiring soon
                                        </Badge>
                                    )}
                                    {!item.is_low_stock &&
                                        item.expiration_status !== 'expired' &&
                                        item.expiration_status !==
                                            'expiring' && (
                                            <Badge variant="secondary">
                                                Normal
                                            </Badge>
                                        )}
                                </div>
                            </td>
                        </tr>
                    ))}
                    {records.data.length === 0 && (
                        <tr>
                            <td
                                colSpan={7}
                                role="status"
                                className="py-12 text-center text-muted-foreground"
                            >
                                No consumable records match these filters.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function AssetTable({ records }: { records: Paginated<InventoryAsset> }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-border/70">
            <table className="w-full min-w-[1120px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                <caption className="sr-only">
                    Property and equipment valuation and depreciation report
                </caption>
                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th>Property no.</th>
                        <th>Asset</th>
                        <th>Custody</th>
                        <th>Lifecycle</th>
                        <th>Condition</th>
                        <th className="text-right">Acquisition cost</th>
                        <th className="text-right">Depreciation</th>
                        <th className="text-right">Book value</th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {records.data.map((asset) => {
                        const holder =
                            asset.active_borrowing?.borrower_reference?.name ??
                            asset.active_borrowing?.borrower_name ??
                            asset.current_custodian?.name;

                        return (
                            <tr key={asset.inventory_asset_id}>
                                <td className="font-mono text-xs">
                                    {asset.property_number ?? '—'}
                                </td>
                                <td>
                                    <div className="font-medium">
                                        {asset.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {asset.category?.name ??
                                            'Uncategorized'}{' '}
                                        · {asset.serial_number}
                                    </div>
                                </td>
                                <td>
                                    <Badge variant="outline">
                                        {titleCase(asset.custody_status)}
                                    </Badge>
                                    {holder && (
                                        <div className="mt-1 max-w-44 truncate text-xs text-muted-foreground">
                                            {holder}
                                        </div>
                                    )}
                                </td>
                                <td>{titleCase(asset.lifecycle_status)}</td>
                                <td>{titleCase(asset.condition_status)}</td>
                                <td className="text-right tabular-nums">
                                    {currency.format(
                                        Number(asset.acquisition_cost ?? 0),
                                    )}
                                </td>
                                <td className="text-right text-muted-foreground tabular-nums">
                                    {currency.format(asset.depreciation_amount)}
                                </td>
                                <td className="text-right font-medium tabular-nums">
                                    {currency.format(asset.book_value)}
                                </td>
                            </tr>
                        );
                    })}
                    {records.data.length === 0 && (
                        <tr>
                            <td
                                colSpan={8}
                                role="status"
                                className="py-12 text-center text-muted-foreground"
                            >
                                No asset records match these filters.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export default function ReportsIndex({
    summary,
    records,
    filters,
    options,
    documents,
}: {
    summary: InventoryReportSummary;
    records: Paginated<InventoryItem> | Paginated<InventoryAsset>;
    filters: InventoryReportFilters;
    options: InventoryReportOptions;
    documents: Array<{
        key: string;
        code: string;
        title: string;
        group: string;
        description: string;
    }>;
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();
    const showingAssets = filters.report === 'assets';
    const exportUrl = exportReport.url({ query: filters });
    const currentDate = new Date().toISOString().slice(0, 10);
    const [printFilters, setPrintFilters] = useState({
        as_of: currentDate,
        period: 'current_year',
        year: currentDate.slice(0, 4),
        from: '',
        to: '',
        fund_cluster: '',
    });
    const [documentSearch, setDocumentSearch] = useState('');
    const [activeDocumentGroup, setActiveDocumentGroup] =
        useState('Physical audit');
    const summaryCards = [
        {
            label: 'Consumable stock value',
            value: currency.format(summary.consumable_value),
            detail: `${integer.format(summary.stock_on_hand)} units across ${integer.format(summary.consumable_types)} item types`,
            icon: Boxes,
        },
        {
            label: 'Stock requiring attention',
            value: integer.format(summary.low_stock),
            detail: `${currency.format(summary.expiring_value)} expiring · ${currency.format(summary.expired_value)} expired`,
            icon: TrendingDown,
        },
        {
            label: 'Asset acquisition cost',
            value: currency.format(summary.acquisition_cost),
            detail: `${integer.format(summary.asset_count)} property and equipment records`,
            icon: Landmark,
        },
        {
            label: 'Current asset book value',
            value: currency.format(summary.book_value),
            detail: `${currency.format(summary.depreciation)} accumulated depreciation`,
            icon: PackageCheck,
        },
    ];
    const documentGroups = Array.from(
        new Set(documents.map((document) => document.group)),
    );
    const normalizedDocumentSearch = documentSearch.trim().toLowerCase();
    const visibleDocuments = documents.filter((document) => {
        const matchesSearch =
            normalizedDocumentSearch === '' ||
            `${document.code} ${document.title} ${document.description}`
                .toLowerCase()
                .includes(normalizedDocumentSearch);
        const matchesGroup =
            normalizedDocumentSearch !== '' ||
            activeDocumentGroup === 'All reports' ||
            document.group === activeDocumentGroup;

        return matchesSearch && matchesGroup;
    });

    return (
        <>
            <Head title="Inventory Reports" />
            <div className="mx-auto flex w-full max-w-[1700px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Inventory intelligence
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Reports and valuation
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Review live stock exposure, asset depreciation, and
                            export the exact filtered register for
                            reconciliation.
                        </p>
                    </div>
                    <Button
                        nativeButton={false}
                        render={
                            <a
                                href={exportUrl}
                                aria-label={`Export filtered ${showingAssets ? 'asset' : 'consumable'} report as CSV`}
                            />
                        }
                    >
                        <Download /> Export filtered CSV
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                    {summaryCards.map(
                        ({ label, value, detail, icon: Icon }) => (
                            <Card key={label}>
                                <CardHeader className="flex-row items-start justify-between gap-3 pb-1">
                                    <div>
                                        <CardDescription>
                                            {label}
                                        </CardDescription>
                                        <CardTitle className="mt-1 text-2xl tabular-nums">
                                            {value}
                                        </CardTitle>
                                    </div>
                                    <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                        <Icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="text-xs text-muted-foreground">
                                    {detail}
                                </CardContent>
                            </Card>
                        ),
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle>COA audit document suite</CardTitle>
                                <CardDescription className="mt-1 max-w-3xl">
                                    Open fixed-layout working documents for
                                    physical count, accountability,
                                    reconciliation, receipts, issues, transfer,
                                    loss, and disposal. Complete the signature
                                    blocks before official submission.
                                </CardDescription>
                            </div>
                            <div className="rounded-lg bg-primary/10 p-2 text-primary">
                                <Printer
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2 xl:grid-cols-5">
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="coa-as-of"
                                    className="text-xs font-medium"
                                >
                                    Physical count as of
                                </label>
                                <Input
                                    id="coa-as-of"
                                    type="date"
                                    value={printFilters.as_of}
                                    onChange={(event) =>
                                        setPrintFilters((current) => ({
                                            ...current,
                                            as_of: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="coa-from"
                                    className="text-xs font-medium"
                                >
                                    Report period
                                </label>
                                <select
                                    id="coa-from"
                                    value={printFilters.period}
                                    className={selectClass}
                                    onChange={(event) =>
                                        setPrintFilters((current) => ({
                                            ...current,
                                            period: event.target.value,
                                        }))
                                    }
                                >
                                    <option value="all">All time</option>
                                    <option value="current_year">
                                        Full calendar year
                                    </option>
                                    <option value="q1">1st quarter</option>
                                    <option value="q2">2nd quarter</option>
                                    <option value="q3">3rd quarter</option>
                                    <option value="q4">4th quarter</option>
                                    <option value="current_month">
                                        Current month
                                    </option>
                                    <option value="custom">
                                        Custom date range
                                    </option>
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="coa-to"
                                    className="text-xs font-medium"
                                >
                                    Calendar year
                                </label>
                                <Input
                                    id="coa-to"
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    value={printFilters.year}
                                    disabled={
                                        printFilters.period === 'all' ||
                                        printFilters.period === 'current_month'
                                    }
                                    onChange={(event) =>
                                        setPrintFilters((current) => ({
                                            ...current,
                                            year: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <label
                                    htmlFor="coa-fund-cluster"
                                    className="text-xs font-medium"
                                >
                                    Fund cluster
                                </label>
                                <Input
                                    id="coa-fund-cluster"
                                    value={printFilters.fund_cluster}
                                    placeholder="All fund clusters"
                                    onChange={(event) =>
                                        setPrintFilters((current) => ({
                                            ...current,
                                            fund_cluster: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            {printFilters.period === 'custom' && (
                                <>
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor="coa-custom-from"
                                            className="text-xs font-medium"
                                        >
                                            Custom from
                                        </label>
                                        <Input
                                            id="coa-custom-from"
                                            type="date"
                                            value={printFilters.from}
                                            onChange={(event) =>
                                                setPrintFilters((current) => ({
                                                    ...current,
                                                    from: event.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor="coa-custom-to"
                                            className="text-xs font-medium"
                                        >
                                            Custom to
                                        </label>
                                        <Input
                                            id="coa-custom-to"
                                            type="date"
                                            value={printFilters.to}
                                            onChange={(event) =>
                                                setPrintFilters((current) => ({
                                                    ...current,
                                                    to: event.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                </>
                            )}
                        </div>
                        <div className="grid gap-3">
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex flex-wrap gap-2">
                                    {['All reports', ...documentGroups].map(
                                        (group) => (
                                            <Button
                                                key={group}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    activeDocumentGroup ===
                                                    group
                                                        ? 'secondary'
                                                        : 'ghost'
                                                }
                                                onClick={() => {
                                                    setActiveDocumentGroup(
                                                        group,
                                                    );
                                                    setDocumentSearch('');
                                                }}
                                            >
                                                {group}
                                            </Button>
                                        ),
                                    )}
                                </div>
                                <div className="relative w-full lg:w-80">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={documentSearch}
                                        placeholder="Search code or report name"
                                        className="pl-9"
                                        onChange={(event) =>
                                            setDocumentSearch(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                                <span>
                                    {normalizedDocumentSearch
                                        ? `Search results across all groups`
                                        : activeDocumentGroup}
                                </span>
                                <span>
                                    {visibleDocuments.length}{' '}
                                    {visibleDocuments.length === 1
                                        ? 'document'
                                        : 'documents'}
                                </span>
                            </div>
                            <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                {visibleDocuments.length > 0 ? (
                                    visibleDocuments.map((document) => (
                                        <a
                                            key={document.key}
                                            href={printReport.url(
                                                document.key,
                                                { query: printFilters },
                                            )}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="group flex items-start gap-3 rounded-lg border p-3 transition-colors hover:border-primary/50 hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <div className="min-w-16 rounded-md bg-muted px-2 py-1 text-center text-xs font-semibold text-foreground group-hover:bg-primary/10 group-hover:text-primary">
                                                {document.code}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="text-sm font-medium text-foreground">
                                                    {document.title}
                                                </div>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {document.description}
                                                </p>
                                            </div>
                                        </a>
                                    ))
                                ) : (
                                    <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground md:col-span-2 xl:col-span-3">
                                        No report matches “{documentSearch}”.
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="gap-4 border-b sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <CardTitle>Report register</CardTitle>
                            <CardDescription aria-live="polite">
                                {records.total} filtered{' '}
                                {showingAssets ? 'asset' : 'consumable'}{' '}
                                records.
                            </CardDescription>
                        </div>
                        <div
                            className="flex w-full rounded-lg bg-muted p-1 sm:w-auto"
                            role="group"
                            aria-label="Report register"
                        >
                            <Button
                                className="flex-1 sm:flex-none"
                                variant={showingAssets ? 'ghost' : 'secondary'}
                                nativeButton={false}
                                render={
                                    <Link
                                        href={index({
                                            query: { report: 'consumables' },
                                        })}
                                        preserveScroll
                                        aria-current={
                                            showingAssets ? undefined : 'page'
                                        }
                                    />
                                }
                            >
                                <Boxes /> Consumables
                            </Button>
                            <Button
                                className="flex-1 sm:flex-none"
                                variant={showingAssets ? 'secondary' : 'ghost'}
                                nativeButton={false}
                                render={
                                    <Link
                                        href={index({
                                            query: { report: 'assets' },
                                        })}
                                        preserveScroll
                                        aria-current={
                                            showingAssets ? 'page' : undefined
                                        }
                                    />
                                }
                            >
                                <PackageCheck /> Assets
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <Form
                            action={index()}
                            options={{
                                preserveState: true,
                                preserveScroll: true,
                            }}
                            className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                        >
                            {({ errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="report"
                                        value={filters.report}
                                    />
                                    <div className="grid w-full gap-1.5 sm:w-72">
                                        <label
                                            htmlFor="report-search"
                                            className="text-sm font-medium"
                                        >
                                            Search register
                                        </label>
                                        <Input
                                            id="report-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder={
                                                showingAssets
                                                    ? 'Name, serial, or property no.'
                                                    : 'Name or stock number'
                                            }
                                            maxLength={100}
                                            onChange={submitAfterDelay}
                                        />
                                        <InputError message={errors.search} />
                                    </div>

                                    {showingAssets ? (
                                        <>
                                            <FilterSelect
                                                id="report-lifecycle"
                                                name="lifecycle_status"
                                                label="Lifecycle"
                                                value={filters.lifecycle_status}
                                                emptyLabel="All lifecycles"
                                                options={options.lifecycles}
                                                onChange={submitImmediately}
                                                error={errors.lifecycle_status}
                                            />
                                            <FilterSelect
                                                id="report-condition"
                                                name="condition_status"
                                                label="Condition"
                                                value={filters.condition_status}
                                                emptyLabel="All conditions"
                                                options={options.conditions}
                                                onChange={submitImmediately}
                                                error={errors.condition_status}
                                            />
                                            <FilterSelect
                                                id="report-custody"
                                                name="custody_status"
                                                label="Custody"
                                                value={filters.custody_status}
                                                emptyLabel="All custody states"
                                                options={options.custody}
                                                onChange={submitImmediately}
                                                error={errors.custody_status}
                                            />
                                        </>
                                    ) : (
                                        <>
                                            <div className="grid w-full gap-1.5 sm:w-40">
                                                <label
                                                    htmlFor="report-item-status"
                                                    className="text-sm font-medium"
                                                >
                                                    Status
                                                </label>
                                                <select
                                                    id="report-item-status"
                                                    name="item_status"
                                                    defaultValue={
                                                        filters.item_status
                                                    }
                                                    className={selectClass}
                                                    onChange={submitImmediately}
                                                >
                                                    <option value="">
                                                        All statuses
                                                    </option>
                                                    <option value="active">
                                                        Active
                                                    </option>
                                                    <option value="inactive">
                                                        Inactive
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.item_status}
                                                />
                                            </div>
                                            <div className="grid w-full gap-1.5 sm:w-52">
                                                <label
                                                    htmlFor="report-attention"
                                                    className="text-sm font-medium"
                                                >
                                                    Attention
                                                </label>
                                                <select
                                                    id="report-attention"
                                                    name="attention"
                                                    defaultValue={
                                                        filters.attention
                                                    }
                                                    className={selectClass}
                                                    onChange={submitImmediately}
                                                >
                                                    <option value="">
                                                        All items
                                                    </option>
                                                    <option value="low_stock">
                                                        Low stock
                                                    </option>
                                                    <option value="expiring">
                                                        Expiring within 30 days
                                                    </option>
                                                    <option value="expired">
                                                        Expired stock
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.attention}
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div className="grid w-full gap-1.5 sm:w-44">
                                        <label
                                            htmlFor="report-records"
                                            className="text-sm font-medium"
                                        >
                                            Records
                                        </label>
                                        <select
                                            id="report-records"
                                            name="records"
                                            defaultValue={filters.records}
                                            className={selectClass}
                                            onChange={submitImmediately}
                                        >
                                            <option value="active">
                                                Active records
                                            </option>
                                            <option value="archived">
                                                Archived records
                                            </option>
                                        </select>
                                        <InputError message={errors.records} />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        nativeButton={false}
                                        render={
                                            <Link
                                                href={index({
                                                    query: {
                                                        report: filters.report,
                                                    },
                                                })}
                                            />
                                        }
                                    >
                                        Clear filters
                                    </Button>
                                </>
                            )}
                        </Form>

                        {showingAssets ? (
                            <AssetTable
                                records={records as Paginated<InventoryAsset>}
                            />
                        ) : (
                            <ConsumableTable
                                records={records as Paginated<InventoryItem>}
                            />
                        )}

                        <div className="flex flex-col gap-2 border-t pt-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <Archive
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Valuation cards cover live, non-archived
                                records; table filters do not alter the headline
                                snapshot.
                            </div>
                            <DataPagination links={records.links} />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Reports', href: index() },
    ],
};
