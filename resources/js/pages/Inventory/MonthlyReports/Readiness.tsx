import {
    Table,
    TableHeader,
    TableHead,
    TableRow,
    TableBody,
    TableCell,
} from '@/components/shared/data-table';
import { money } from './types';
import type { Readiness as ReadinessData } from './types';
export function Readiness({ data }: { data: ReadinessData }) {
    return (
        <section className="overflow-hidden rounded-xl border bg-card shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                <div>
                    <h2 className="font-semibold">Source record checks</h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Monthly stock movements and approval readiness.
                    </p>
                </div>
                <span
                    className={
                        data.blockers.length
                            ? 'rounded-full bg-destructive/10 px-3 py-1 text-xs font-medium text-destructive'
                            : 'rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-400'
                    }
                >
                    {data.blockers.length
                        ? `${data.blockers.length} requiring attention`
                        : 'Ready for sign-off'}
                </span>
            </div>
            <dl className="grid grid-cols-2 gap-px border-b bg-border lg:grid-cols-4">
                {[
                    [
                        'Closing stock value',
                        money(data.totals.closing_stock_value),
                    ],
                    ['Stock master items', data.totals.stock_master_rows],
                    ['Issue allocations', data.totals.issues],
                    ['Approved adjustments', data.totals.adjustments],
                ].map(([title, value]) => (
                    <div key={title} className="bg-card px-5 py-4 sm:px-6">
                        <dt className="text-xs font-medium text-muted-foreground">
                            {title}
                        </dt>
                        <dd className="mt-2 text-xl font-semibold tracking-tight tabular-nums">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <div className="space-y-4 p-5 sm:p-6">
                {data.blockers.length === 0 ? (
                    <p className="text-sm text-emerald-700 dark:text-emerald-400">
                        No outstanding approval or reconciliation issues were
                        found.
                    </p>
                ) : (
                    <>
                        <p className="text-sm font-medium text-destructive">
                            {data.blockers.length} issue(s) must be resolved
                            before preparation or sign-off.
                        </p>
                        <div className="space-y-3 md:hidden">
                            {data.blockers.map((row, i) => (
                                <article
                                    key={i}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <p className="font-medium">
                                        {row.kind} / {row.reference}
                                    </p>
                                    <p>
                                        {row.status} / {row.reason}
                                    </p>
                                </article>
                            ))}
                        </div>
                        <div className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Process</TableHead>
                                        <TableHead>
                                            Reference / status
                                        </TableHead>
                                        <TableHead>
                                            Required follow-up
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.blockers.map((row, i) => (
                                        <TableRow key={i}>
                                            <TableCell>{row.kind}</TableCell>
                                            <TableCell>
                                                {row.reference} / {row.status}
                                            </TableCell>
                                            <TableCell>{row.reason}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}
            </div>
        </section>
    );
}
