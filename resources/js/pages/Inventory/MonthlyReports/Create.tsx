import { Combobox } from '@base-ui/react/combobox';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, create, store } from '@/routes/inventory/monthly_reports';
import type { HrisReference } from '@/types/inventory';
import { Readiness } from './Readiness';
import { lastCompletedMonth } from './types';
import type { Readiness as ReadinessData } from './types';
type Selection = {
    period_month: string;
    fund_cluster: string;
    series_category_id: number | null;
    include_zero: boolean;
    selected_report?: string | null;
};
export default function MonthlyCreate({
    selection,
    readiness,
    catalog,
    categories,
    signatories,
    preparer,
}: {
    selection: Selection;
    readiness: ReadinessData;
    catalog: Record<string, string>;
    categories: { inv_series_cat_id: number; name: string }[];
    signatories: HrisReference[];
    preparer: { id: number; name: string };
}) {
    const form = useForm({
        period_month: selection.period_month,
        fund_cluster: selection.fund_cluster,
        series_category_id: selection.series_category_id
            ? String(selection.series_category_id)
            : '',
        include_zero: selection.include_zero,
        checked_by_user_id: '',
        noted_by_user_id: '',
        approved_by_user_id: '',
        selected_report: selection.selected_report ?? '',
        attested: false,
        idempotency_key: crypto.randomUUID(),
    });
    const reportOptions = [
        { key: '', title: 'All reports' },
        ...Object.entries(catalog).map(([key, title]) => ({ key, title })),
    ];
    const changed =
        form.data.selected_report !== (selection.selected_report ?? '') ||
        form.data.period_month !== selection.period_month ||
        form.data.fund_cluster !== selection.fund_cluster ||
        form.data.series_category_id !==
            String(selection.series_category_id ?? '') ||
        form.data.include_zero !== selection.include_zero;

    return (
        <>
            <Head title="Prepare monthly package" />
            <PageContainer>
                <Link href={index()} className="text-sm underline">
                    Monthly report packages
                </Link>
                <header className="space-y-4">
                    <div>
                        <p className="text-xs font-semibold tracking-widest text-primary uppercase">
                            Monthly reporting
                        </p>
                        <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                            Prepare report package
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                            Choose your reports, review the monthly records, and
                            assign the officers who will sign the package.
                        </p>
                    </div>
                    <ol className="grid gap-3 rounded-xl border bg-muted/25 p-4 text-sm sm:grid-cols-3">
                        {[
                            'Choose report & period',
                            'Review source records',
                            'Assign signatories & prepare',
                        ].map((step, index) => (
                            <li key={step} className="flex items-center gap-3">
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full border bg-background text-xs font-semibold text-primary">
                                    {index + 1}
                                </span>
                                <span className="font-medium">{step}</span>
                            </li>
                        ))}
                    </ol>
                </header>
                <form
                    className="space-y-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            selected_report: data.selected_report || null,
                            series_category_id: data.series_category_id || null,
                        }));
                        form.post(store.url());
                    }}
                >
                    <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
                        <div className="border-b px-5 py-4 sm:px-6">
                            <h2 className="font-semibold">Report settings</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Choose the report and reporting period for your
                                package.
                            </p>
                        </div>
                        <div className="grid items-start gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
                            <div className="min-w-0 space-y-2 sm:col-span-2">
                                <Label htmlFor="package-report">
                                    Report to generate
                                </Label>
                                <Combobox.Root
                                    items={reportOptions}
                                    value={
                                        reportOptions.find(
                                            (option) =>
                                                option.key ===
                                                form.data.selected_report,
                                        ) ?? reportOptions[0]
                                    }
                                    onValueChange={(option) => {
                                        if (option) {
                                            form.setData(
                                                'selected_report',
                                                option.key,
                                            );
                                        }
                                    }}
                                    itemToStringLabel={(option) => option.title}
                                    isItemEqualToValue={(option, selected) =>
                                        option.key === selected.key
                                    }
                                    autoHighlight
                                >
                                    <Combobox.InputGroup className="relative flex h-9 w-full items-center rounded-md border border-input bg-background shadow-xs focus-within:ring-2 focus-within:ring-ring/50 dark:bg-input/30">
                                        <Combobox.Input
                                            id="package-report"
                                            placeholder="Search reports..."
                                            aria-invalid={Boolean(
                                                form.errors.selected_report,
                                            )}
                                            className="h-full min-w-0 flex-1 bg-transparent px-3 pr-10 text-sm outline-none"
                                        />
                                        <Combobox.Trigger
                                            aria-label="Show report options"
                                            className="absolute right-1 flex size-7 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <ChevronsUpDown className="size-4" />
                                        </Combobox.Trigger>
                                    </Combobox.InputGroup>
                                    <Combobox.Portal>
                                        <Combobox.Positioner
                                            sideOffset={4}
                                            align="start"
                                            className="isolate z-[60]"
                                        >
                                            <Combobox.Popup className="max-h-(--available-height) w-max max-w-(--available-width) min-w-(--anchor-width) overflow-hidden rounded-lg bg-popover text-popover-foreground shadow-md ring-1 ring-foreground/10">
                                                <Combobox.Empty className="px-3 py-6 text-center text-sm text-muted-foreground">
                                                    No matching reports.
                                                </Combobox.Empty>
                                                <Combobox.List className="max-h-72 overflow-y-auto p-1">
                                                    {(option: {
                                                        key: string;
                                                        title: string;
                                                    }) => (
                                                        <Combobox.Item
                                                            key={option.key}
                                                            value={option}
                                                            className="relative cursor-default rounded-md py-2 pr-9 pl-2 text-sm wrap-break-word whitespace-normal outline-none select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                                                        >
                                                            {option.title}
                                                            <Combobox.ItemIndicator className="absolute top-2 right-2">
                                                                <Check className="size-4" />
                                                            </Combobox.ItemIndicator>
                                                        </Combobox.Item>
                                                    )}
                                                </Combobox.List>
                                            </Combobox.Popup>
                                        </Combobox.Positioner>
                                    </Combobox.Portal>
                                </Combobox.Root>
                                <InputError
                                    message={form.errors.selected_report}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="package-month">
                                    Completed reporting month
                                </Label>
                                <Input
                                    id="package-month"
                                    type="month"
                                    required
                                    max={lastCompletedMonth()}
                                    value={form.data.period_month}
                                    onChange={(e) =>
                                        form.setData(
                                            'period_month',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="package-fund">
                                    Reporting fund label
                                </Label>
                                <Input
                                    id="package-fund"
                                    required
                                    maxLength={50}
                                    value={form.data.fund_cluster}
                                    onChange={(e) =>
                                        form.setData(
                                            'fund_cluster',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        {(!form.data.selected_report ||
                            form.data.selected_report === 'stock-master') && (
                            <div className="grid items-start gap-5 border-t bg-muted/20 p-5 sm:grid-cols-2 sm:p-6">
                                <div className="min-w-0 space-y-2">
                                    <Label htmlFor="package-classification">
                                        Stock master classification
                                    </Label>
                                    <FormSelect
                                        id="package-classification"
                                        className="w-full"
                                        value={form.data.series_category_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'series_category_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            All inventory classifications
                                        </option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.inv_series_cat_id}
                                                value={
                                                    category.inv_series_cat_id
                                                }
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </FormSelect>
                                </div>
                                <fieldset className="space-y-2">
                                    <legend className="text-sm font-medium">
                                        Stock master items
                                    </legend>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {[
                                            {
                                                value: true,
                                                label: 'With zero',
                                            },
                                            {
                                                value: false,
                                                label: 'Without zero',
                                            },
                                        ].map((option) => (
                                            <label
                                                key={option.label}
                                                className="flex cursor-pointer items-start gap-3 rounded-lg border bg-background p-3 has-checked:border-primary has-checked:bg-primary/5"
                                            >
                                                <input
                                                    type="radio"
                                                    name="stock_master_items"
                                                    value={String(option.value)}
                                                    checked={
                                                        form.data
                                                            .include_zero ===
                                                        option.value
                                                    }
                                                    onChange={() =>
                                                        form.setData(
                                                            'include_zero',
                                                            option.value,
                                                        )
                                                    }
                                                    className="mt-0.5 size-4 shrink-0 accent-primary"
                                                />
                                                <span>
                                                    <span className="block text-sm font-medium">
                                                        {option.label}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>
                            </div>
                        )}
                        <div className="flex flex-col gap-4 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <p className="max-w-2xl text-xs leading-relaxed text-muted-foreground">
                                The classification and item inclusion options
                                apply to the stock master. Registers cover the
                                selected month and retain source fund
                                references.
                            </p>
                            <Button
                                className="w-full shrink-0 sm:w-auto"
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.get(create.url(), {
                                        period_month: form.data.period_month,
                                        selected_report:
                                            form.data.selected_report ||
                                            undefined,
                                        fund_cluster: form.data.fund_cluster,
                                        series_category_id:
                                            form.data.series_category_id ||
                                            undefined,
                                        include_zero: form.data.include_zero
                                            ? 1
                                            : 0,
                                    })
                                }
                            >
                                Validate selected month
                            </Button>
                        </div>
                    </section>
                    {changed && (
                        <p className="rounded-lg border p-3 text-sm">
                            Validate the updated selection before preparing the
                            package.
                        </p>
                    )}
                    <Readiness data={readiness} />
                    <section className="grid gap-5 rounded-xl border bg-card p-5 shadow-sm sm:grid-cols-3 sm:p-6">
                        <div className="space-y-2 sm:col-span-3">
                            <h2 className="font-semibold">
                                Assigned signatories
                            </h2>
                            <p className="text-sm">
                                Prepared by: {preparer.name} / Senior Supply
                                Officer or PPE Custodian
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Select the officers serving each role. Their
                                names and assignments are preserved with this
                                package.
                            </p>
                        </div>
                        {(
                            [
                                [
                                    'checked_by_user_id',
                                    'Checked by / PMMD Division Manager',
                                ],
                                [
                                    'noted_by_user_id',
                                    'Noted by / AHRD Department Manager',
                                ],
                                [
                                    'approved_by_user_id',
                                    'Approved by / AGM for Admin and Finance',
                                ],
                            ] as const
                        ).map(([key, title]) => (
                            <div className="space-y-2" key={key}>
                                <Label htmlFor={key}>{title}</Label>
                                <HrisReferenceCombobox
                                    id={key}
                                    name={key}
                                    references={signatories.filter(
                                        (person) => person.id !== preparer.id,
                                    )}
                                    value={form.data[key]}
                                    onValueChange={(value) =>
                                        form.setData(key, value)
                                    }
                                />
                            </div>
                        ))}
                    </section>
                    <label className="flex cursor-pointer items-start gap-3 rounded-xl border bg-muted/20 p-5 text-sm leading-relaxed">
                        <input
                            type="checkbox"
                            checked={form.data.attested}
                            onChange={(e) =>
                                form.setData('attested', e.target.checked)
                            }
                        />
                        I confirm the month is complete and its source records
                        have been validated and reconciled.
                    </label>
                    {Object.entries(form.errors).map(([key, error]) => (
                        <InputError key={key} message={error} />
                    ))}
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            changed ||
                            readiness.blockers.length > 0 ||
                            !form.data.attested
                        }
                    >
                        {form.processing
                            ? 'Preparing package...'
                            : 'Prepare saved report package'}
                    </Button>
                </form>
            </PageContainer>
        </>
    );
}
MonthlyCreate.layout = {
    breadcrumbs: [{ title: 'Monthly Reports', href: index.url() }],
};
