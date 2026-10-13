import { Head, Link, router, useForm } from '@inertiajs/react';
import { HrisReferenceCombobox } from '@/components/hris-reference-combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, create, store } from '@/routes/inventory/monthly_reports';
import type { HrisReference } from '@/types/inventory';
import { Readiness } from './Readiness';
import { areaClass, lastCompletedMonth } from './types';
import type { Readiness as ReadinessData } from './types';
type Selection = {
    period_month: string;
    fund_cluster: string;
    series_category_id: number | null;
    include_zero: boolean;
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
        reconciliation_reference: '',
        correction_reason: '',
        attested: false,
        idempotency_key: crypto.randomUUID(),
    });
    const changed =
        form.data.period_month !== selection.period_month ||
        form.data.fund_cluster !== selection.fund_cluster ||
        form.data.series_category_id !==
            String(selection.series_category_id ?? '') ||
        form.data.include_zero !== selection.include_zero;

    return (
        <>
            <Head title="Prepare monthly package" />
            <div className="space-y-6 p-4 sm:p-6">
                <Link href={index()} className="text-sm underline">
                    Monthly report packages
                </Link>
                <h1 className="text-2xl font-semibold">
                    Prepare monthly report package
                </h1>
                <p className="text-sm text-muted-foreground">
                    Select a completed month, validate source records, and
                    assign the WI-012 signatories. Preparation saves report
                    snapshots and does not change stock.
                </p>
                <form
                    className="space-y-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            series_category_id: data.series_category_id || null,
                        }));
                        form.post(store.url());
                    }}
                >
                    <section className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
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
                                    form.setData('period_month', e.target.value)
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
                                    form.setData('fund_cluster', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="package-classification">
                                Stock master classification
                            </Label>
                            <FormSelect
                                id="package-classification"
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
                                        value={category.inv_series_cat_id}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </FormSelect>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.include_zero}
                                onChange={(e) =>
                                    form.setData(
                                        'include_zero',
                                        e.target.checked,
                                    )
                                }
                            />
                            Include zero closing balances in stock master
                        </label>
                        <p className="text-xs text-muted-foreground sm:col-span-2">
                            The classification and zero-balance options apply to
                            the stock master. Registers cover the selected month
                            and retain source fund references.
                        </p>
                        <Button
                            className="sm:col-span-2 sm:justify-self-start"
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.get(create.url(), {
                                    period_month: form.data.period_month,
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
                    </section>
                    {changed && (
                        <p className="rounded-lg border p-3 text-sm">
                            Validate the updated selection before preparing the
                            package.
                        </p>
                    )}
                    <Readiness data={readiness} />
                    <section className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-semibold">
                            Reports included ({Object.keys(catalog).length})
                        </h2>
                        <ul className="grid list-inside list-disc gap-2 text-sm sm:grid-cols-2">
                            {Object.entries(catalog).map(([key, title]) => (
                                <li key={key}>{title}</li>
                            ))}
                        </ul>
                        <p className="text-xs text-muted-foreground">
                            Journal registers support Accounting's JEV
                            preparation. Approved adjustments are included from
                            their source records.
                        </p>
                    </section>
                    <section className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                        <div className="space-y-2 sm:col-span-2">
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
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="reconciliation-reference">
                                Data validation and reconciliation evidence
                            </Label>
                            <textarea
                                id="reconciliation-reference"
                                className={areaClass}
                                required
                                maxLength={3000}
                                value={form.data.reconciliation_reference}
                                onChange={(e) =>
                                    form.setData(
                                        'reconciliation_reference',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="correction-reason">
                                Correction reason (required when revising an
                                approved package)
                            </Label>
                            <textarea
                                id="correction-reason"
                                className={areaClass}
                                maxLength={3000}
                                value={form.data.correction_reason}
                                onChange={(e) =>
                                    form.setData(
                                        'correction_reason',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </section>
                    <label className="flex items-start gap-2 text-sm">
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
                        disabled={
                            form.processing ||
                            changed ||
                            readiness.blockers.length > 0 ||
                            !form.data.attested
                        }
                    >
                        Prepare saved report package
                    </Button>
                </form>
            </div>
        </>
    );
}
MonthlyCreate.layout = {
    breadcrumbs: [{ title: 'Monthly Reports', href: index.url() }],
};
