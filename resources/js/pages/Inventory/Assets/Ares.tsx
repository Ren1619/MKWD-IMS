import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { PageContainer } from '@/components/page-container';
import { PpeModuleNav } from '@/components/ppe-module-nav';
import { RecordChangeBadge } from '@/components/record-change-badge';
import {
    Table,
    TableHeader,
    TableHead,
    TableBody,
    TableRow,
    TableCell,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkflowActionDialog } from '@/components/workflow-action-dialog';
import {
    transition,
    print as printAre,
} from '@/routes/inventory/accountability';
import { index as ppeIndex } from '@/routes/inventory/assets';
import { index } from '@/routes/inventory/assets/ares';
import type { Paginated } from '@/types/inventory';

type Are = {
    id: number;
    document_no: string;
    status: string;
    asset_name: string;
    property_number: string | null;
    recipient_name: string;
    issued_by_name: string;
    acknowledged_at: string | null;
    notation_required: boolean;
    noted_at: string | null;
    noted_by_name: string | null;
    can_acknowledge: boolean;
    can_note: boolean;
    actions: {
        id: number;
        action: string;
        actor: { name: string };
        created_at: string;
        remarks: string | null;
    }[];
};
type Filters = { search?: string; status?: string; view?: 'all' | 'noted' };
const labels: Record<string, string> = {
    pending_recipient: 'Awaiting end-user acknowledgment',
    pending_note: 'Awaiting division-manager notation',
    active: 'Completed',
    superseded: 'Superseded',
    returned: 'Returned',
    cancelled: 'Cancelled',
    transferred_out: 'Transferred out',
};

export default function Ares({
    documents,
    filters,
}: {
    documents: Paginated<Are>;
    filters: Filters;
}) {
    const notedView = filters.view === 'noted';
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selected, setSelected] = useState<{
        document: Are;
        action: 'acknowledge' | 'note';
    } | null>(null);
    const form = useForm({ action: '', attested: true, remarks: '' });
    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            index.url(),
            { search, status, view: notedView ? 'noted' : 'all' },
            { preserveState: true, preserveScroll: true },
        );
    }
    function open(document: Are, action: 'acknowledge' | 'note') {
        form.clearErrors();
        form.setData({ action, attested: true, remarks: '' });
        setSelected({ document, action });
    }
    function confirm() {
        if (selected) {
            form.patch(transition.url(selected.document.id), {
                preserveScroll: true,
                onSuccess: () => setSelected(null),
            });
        }
    }

    return (
        <>
            <Head title={notedView ? 'Noted AREs' : 'AREs'} />
            <PageContainer>
                <div>
                    <h1 className="text-2xl font-semibold">
                        {notedView
                            ? 'Noted AREs'
                            : 'Acknowledgment Receipts for Equipment'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Prepared by the Senior Supply Officer, acknowledged by
                        the end user, then noted by the division manager.
                        Division notation is optional when no division is
                        assigned.
                    </p>
                </div>
                <PpeModuleNav active={notedView ? 'noted' : 'ares'} />
                <form
                    onSubmit={filter}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid flex-1 gap-1.5">
                        <Label htmlFor="are-search">Search AREs</Label>
                        <Input
                            id="are-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="ARE number, item description, or end user"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="are-status">Status</Label>
                        <FormSelect
                            id="are-status"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {Object.entries(labels).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </FormSelect>
                    </div>
                    <Button type="submit">Filter</Button>
                </form>
                {documents.data.length === 0 ? (
                    <div className="rounded-lg border p-10 text-center">
                        <h2 className="font-semibold">
                            {notedView
                                ? 'No AREs noted by you yet'
                                : 'No AREs to display'}
                        </h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {notedView
                                ? 'AREs you note will remain available here.'
                                : 'AREs assigned to you or awaiting your division notation will appear here.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        ARE / item description
                                    </TableHead>
                                    <TableHead>End user</TableHead>
                                    <TableHead>Signatures</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents.data.map((document) => (
                                    <TableRow key={document.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {document.document_no}
                                                <RecordChangeBadge
                                                    collection="documents"
                                                    record={document}
                                                />
                                            </p>
                                            <p>{document.asset_name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {document.property_number}
                                            </p>
                                            <details className="mt-2">
                                                <summary className="cursor-pointer">
                                                    History
                                                </summary>
                                                {document.actions.map(
                                                    (action) => (
                                                        <p
                                                            key={action.id}
                                                            className="mt-1 text-xs"
                                                        >
                                                            {action.actor.name}{' '}
                                                            —{' '}
                                                            {action.action.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}{' '}
                                                            ·{' '}
                                                            {new Date(
                                                                action.created_at,
                                                            ).toLocaleString()}
                                                            {action.remarks
                                                                ? ` · ${action.remarks}`
                                                                : ''}
                                                        </p>
                                                    ),
                                                )}
                                            </details>
                                        </TableCell>
                                        <TableCell>
                                            {document.recipient_name}
                                        </TableCell>
                                        <TableCell>
                                            <p>
                                                Prepared:{' '}
                                                {document.issued_by_name}
                                            </p>
                                            <p>
                                                Acknowledged:{' '}
                                                {document.acknowledged_at
                                                    ? document.recipient_name
                                                    : 'Pending'}
                                            </p>
                                            <p>
                                                Noted:{' '}
                                                {document.noted_by_name ??
                                                    (document.notation_required
                                                        ? 'Pending'
                                                        : 'Not required — no division assigned')}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {labels[document.status] ??
                                                    document.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-2">
                                                {document.can_acknowledge && (
                                                    <Button
                                                        onClick={() =>
                                                            open(
                                                                document,
                                                                'acknowledge',
                                                            )
                                                        }
                                                    >
                                                        Acknowledge
                                                    </Button>
                                                )}
                                                {document.can_note && (
                                                    <Button
                                                        onClick={() =>
                                                            open(
                                                                document,
                                                                'note',
                                                            )
                                                        }
                                                    >
                                                        Note
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="outline"
                                                    nativeButton={false}
                                                    render={
                                                        <a
                                                            href={printAre.url(
                                                                document.id,
                                                            )}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        />
                                                    }
                                                >
                                                    Print ARE
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
                <DataPagination links={documents.links} />
            </PageContainer>
            <WorkflowActionDialog
                open={selected !== null}
                onOpenChange={(open) => !open && setSelected(null)}
                title={`${selected?.action === 'note' ? 'Note' : 'Acknowledge'} ${selected?.document.document_no ?? 'ARE'}`}
                description={
                    selected?.action === 'note'
                        ? 'Confirm the end user has acknowledged receipt and note the equipment accountability within your division.'
                        : 'Confirm you received the equipment and accept accountability for its official use and safekeeping.'
                }
                confirmLabel={
                    selected?.action === 'note' ? 'Note ARE' : 'Acknowledge'
                }
                processing={form.processing}
                remarks={form.data.remarks}
                onRemarksChange={(remarks) => form.setData('remarks', remarks)}
                onConfirm={confirm}
                error={
                    form.errors.action ??
                    form.errors.remarks ??
                    form.errors.attested
                }
            />
        </>
    );
}
Ares.layout = {
    breadcrumbs: [{ title: 'PPE', href: ppeIndex() }, { title: 'AREs' }],
};
