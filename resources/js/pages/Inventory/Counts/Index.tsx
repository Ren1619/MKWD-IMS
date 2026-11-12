import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
import { RecordChangeBadge } from '@/components/record-change-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TableRowLink,
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
            <PageContainer>
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
                                <TableRowLink
                                    key={session.id}
                                    href={show.url(session.id)}
                                >
                                    <TableCell>
                                        <span className="font-medium">
                                            {session.document_no}
                                            <RecordChangeBadge
                                                collection="sessions"
                                                record={session}
                                            />
                                        </span>
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
                                </TableRowLink>
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
            </PageContainer>
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
                        noValidate
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
                                aria-invalid={!!form.errors.title}
                                required
                                maxLength={255}
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="count-scheduled">
                                Scheduled date
                            </Label>
                            <DatePicker
                                id="count-scheduled"
                                aria-invalid={!!form.errors.scheduled_date}
                                required
                                value={form.data.scheduled_date}
                                onChange={(value) =>
                                    form.setData('scheduled_date', value)
                                }
                            />
                            <InputError message={form.errors.scheduled_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="count-memo">
                                Approved memorandum reference
                            </Label>
                            <Input
                                id="count-memo"
                                aria-invalid={
                                    !!form.errors.memorandum_reference
                                }
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
                            <InputError
                                message={form.errors.memorandum_reference}
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
                                aria-invalid={!!form.errors.team_details}
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
                            <InputError message={form.errors.team_details} />
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
                        <InputError message={form.errors.attested} />
                        <Button type="submit" disabled={form.processing}>
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
