import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show, store } from '@/routes/inventory/counts';
import type { Paginated } from '@/types/inventory';
import { dateInManila, statusLabel } from './types';
import type { CountSession } from './types';

export default function CountsIndex({
    sessions,
    activeSessionId,
}: {
    sessions: Paginated<CountSession>;
    activeSessionId: number | null;
}) {
    const [planning, setPlanning] = useState(false);
    const form = useForm({
        title: '',
        scheduled_date: dateInManila(),
        memorandum_reference: '',
        team_details: '',
        attested: false,
    });

    return (
        <>
            <Head title="Physical counts" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Physical counts
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Plan, count, reconcile, and approve consumable
                            inventory.
                        </p>
                    </div>
                    <Button onClick={() => setPlanning(true)}>
                        Plan a count
                    </Button>
                </div>
                {activeSessionId && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                        Stock movements are frozen for an active count.{' '}
                        <Link
                            className="font-medium underline"
                            href={show(activeSessionId)}
                        >
                            Open the active session
                        </Link>{' '}
                        to record authorized emergency issues or segregated
                        receipts.
                    </div>
                )}
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Reference / count</TableHead>
                                <TableHead>Scheduled</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Items</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sessions.data.map((session) => (
                                <TableRow key={session.id}>
                                    <TableCell>
                                        <Link
                                            className="font-medium underline underline-offset-4"
                                            href={show(session.id)}
                                        >
                                            {session.document_no}
                                        </Link>
                                        <p className="mt-1 whitespace-normal text-muted-foreground">
                                            {session.title}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {session.scheduled_date.slice(0, 10)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {statusLabel(session.status)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{session.lines_count}</TableCell>
                                </TableRow>
                            ))}
                            {sessions.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-12 text-center text-muted-foreground"
                                    >
                                        No counts planned. Plan a session using
                                        the approved inventory memorandum.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <DataPagination links={sessions.links} />
            </div>
            <Dialog
                open={planning}
                onOpenChange={(open) => {
                    setPlanning(open);

                    if (!open) {
                        form.reset();
                        form.clearErrors();
                    }
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Plan a physical count</DialogTitle>
                        <DialogDescription>
                            Starting the session snapshots all active
                            consumables and other items with stock, including
                            zero balances. Normal receipts and issues pause
                            until approval or cancellation.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url(), {
                                onSuccess: () => setPlanning(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="count-title">Count title</Label>
                            <Input
                                id="count-title"
                                required
                                maxLength={255}
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="count-scheduled">
                                Scheduled date
                            </Label>
                            <DatePicker
                                id="count-scheduled"
                                required
                                value={form.data.scheduled_date}
                                onChange={(value) =>
                                    form.setData('scheduled_date', value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="count-memo">
                                Approved memorandum reference
                            </Label>
                            <Input
                                id="count-memo"
                                required
                                maxLength={120}
                                value={form.data.memorandum_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'memorandum_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="count-team">
                                Team and counting arrangements
                            </Label>
                            <textarea
                                rows={3}
                                className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                id="count-team"
                                required
                                maxLength={3000}
                                value={form.data.team_details}
                                onChange={(event) =>
                                    form.setData(
                                        'team_details',
                                        event.target.value,
                                    )
                                }
                                placeholder="Counters, checkers, validators, witnesses, locations, and schedule"
                            />
                        </div>
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.attested}
                                onChange={(event) =>
                                    form.setData(
                                        'attested',
                                        event.target.checked,
                                    )
                                }
                            />
                            I confirm this plan follows the approved inventory
                            memorandum.
                        </label>
                        {Object.entries(form.errors).map(([key, error]) => (
                            <InputError key={key} message={error} />
                        ))}
                        <Button
                            disabled={form.processing || !form.data.attested}
                        >
                            Create count session
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
CountsIndex.layout = {
    breadcrumbs: [{ title: 'Physical counts', href: index() }],
};
