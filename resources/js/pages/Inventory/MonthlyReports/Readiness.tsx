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
        <section className="space-y-3 rounded-lg border p-4">
            <h2 className="font-semibold">Validation and reconciliation</h2>
            <p className="text-sm">
                {data.totals.stock_master_rows} stock master rows / FIFO closing
                value: {money(data.totals.closing_stock_value)} /{' '}
                {data.totals.issues} issue allocations /{' '}
                {data.totals.adjustments} adjustments.
            </p>
            {data.blockers.length === 0 ? (
                <p className="text-sm text-emerald-700 dark:text-emerald-400">
                    No outstanding approval or reconciliation issues were found.
                </p>
            ) : (
                <>
                    <p className="text-sm font-medium text-destructive">
                        {data.blockers.length} issue(s) must be resolved before
                        preparation or sign-off.
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
                                    <TableHead>Reference / status</TableHead>
                                    <TableHead>Required follow-up</TableHead>
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
        </section>
    );
}
