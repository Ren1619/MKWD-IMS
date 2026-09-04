import { Form, Head, Link } from '@inertiajs/react';
import { Activity, LogIn, LogOut, Pencil, Plus, Trash2 } from 'lucide-react';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useFilterSubmit } from '@/hooks/use-filter-submit';
import { index } from '@/routes/admin/audit-logs';
import type { Paginated } from '@/types/inventory';

type AuditLog = {
    id: number;
    event: 'created' | 'updated' | 'deleted' | 'login' | 'logout';
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    changed_attributes: string[];
    ip_address: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

const eventPresentation = {
    created: { label: 'Created', icon: Plus, variant: 'secondary' as const },
    updated: { label: 'Updated', icon: Pencil, variant: 'outline' as const },
    deleted: {
        label: 'Deleted',
        icon: Trash2,
        variant: 'destructive' as const,
    },
    login: { label: 'Signed in', icon: LogIn, variant: 'secondary' as const },
    logout: { label: 'Signed out', icon: LogOut, variant: 'outline' as const },
};

export default function AuditLogsIndex({
    auditLogs,
    filters,
}: {
    auditLogs: Paginated<AuditLog>;
    filters: {
        search?: string;
        event?: string;
        date_from?: string;
        date_to?: string;
    };
}) {
    const { submitAfterDelay, submitImmediately } = useFilterSubmit();

    return (
        <>
            <Head title="Audit Logs" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <p className="text-sm font-medium text-primary">
                        Administration
                    </p>
                    <h1 className="text-2xl font-semibold">Audit logs</h1>
                    <p className="text-sm text-muted-foreground">
                        A permanent activity trail showing who changed what and
                        when.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="size-5 text-primary" />
                            System activity
                        </CardTitle>
                        <CardDescription>
                            {auditLogs.total} recorded event
                            {auditLogs.total === 1 ? '' : 's'}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <Form
                            action={index()}
                            options={{ preserveState: true }}
                            className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                        >
                            {({ errors }) => (
                                <>
                                    <div className="grid w-full gap-1.5 sm:w-80">
                                        <label
                                            htmlFor="audit-search"
                                            className="text-sm font-medium"
                                        >
                                            Search activity
                                        </label>
                                        <Input
                                            id="audit-search"
                                            name="search"
                                            defaultValue={filters.search}
                                            placeholder="e.g. Maria Santos, updated, or 192.0.2.10"
                                            maxLength={100}
                                            onChange={submitAfterDelay}
                                        />
                                        <InputError message={errors.search} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-40">
                                        <label
                                            htmlFor="audit-event"
                                            className="text-sm font-medium"
                                        >
                                            Event
                                        </label>
                                        <select
                                            id="audit-event"
                                            name="event"
                                            className={selectClass}
                                            defaultValue={filters.event ?? ''}
                                            onChange={submitImmediately}
                                        >
                                            <option value="">All events</option>
                                            {Object.entries(
                                                eventPresentation,
                                            ).map(([value, item]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {item.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.event} />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-40">
                                        <label
                                            htmlFor="audit-date-from"
                                            className="text-sm font-medium"
                                        >
                                            From date
                                        </label>
                                        <Input
                                            id="audit-date-from"
                                            name="date_from"
                                            type="date"
                                            defaultValue={filters.date_from}
                                            onChange={submitImmediately}
                                        />
                                        <InputError
                                            message={errors.date_from}
                                        />
                                    </div>
                                    <div className="grid w-full gap-1.5 sm:w-40">
                                        <label
                                            htmlFor="audit-date-to"
                                            className="text-sm font-medium"
                                        >
                                            To date
                                        </label>
                                        <Input
                                            id="audit-date-to"
                                            name="date_to"
                                            type="date"
                                            defaultValue={filters.date_to}
                                            min={filters.date_from}
                                            onChange={submitImmediately}
                                        />
                                        <InputError message={errors.date_to} />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="ghost"
                                            nativeButton={false}
                                            render={<Link href={index()} />}
                                        >
                                            Clear
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>

                        <div className="overflow-x-auto rounded-lg border border-border/70">
                            <Table className="w-full min-w-[900px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <TableHeader className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <TableRow>
                                        <TableHead className="pb-3">
                                            When
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Who
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Event
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Activity
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            Changed fields
                                        </TableHead>
                                        <TableHead className="pb-3">
                                            IP address
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody className="divide-y">
                                    {auditLogs.data.map((log) => {
                                        const presentation =
                                            eventPresentation[log.event];
                                        const Icon = presentation.icon;

                                        return (
                                            <TableRow key={log.id}>
                                                <TableCell className="py-3 whitespace-nowrap text-muted-foreground">
                                                    {new Date(
                                                        log.created_at,
                                                    ).toLocaleString()}
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <div className="font-medium">
                                                        {log.user?.name ??
                                                            'System'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {log.user?.email ??
                                                            'Background process'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <Badge
                                                        variant={
                                                            presentation.variant
                                                        }
                                                    >
                                                        <Icon />
                                                        {presentation.label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="min-w-64 py-3">
                                                    {log.description}
                                                </TableCell>
                                                <TableCell className="py-3 text-xs text-muted-foreground">
                                                    {log.changed_attributes
                                                        .map((field) =>
                                                            field.replaceAll(
                                                                '_',
                                                                ' ',
                                                            ),
                                                        )
                                                        .join(', ') || '—'}
                                                </TableCell>
                                                <TableCell className="py-3 font-mono text-xs whitespace-nowrap text-muted-foreground">
                                                    {log.ip_address ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    {auditLogs.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-10 text-center text-muted-foreground"
                                            >
                                                No activity matches these
                                                filters.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <DataPagination links={auditLogs.links} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Administration', href: index() },
        { title: 'Audit Logs', href: index() },
    ],
};
