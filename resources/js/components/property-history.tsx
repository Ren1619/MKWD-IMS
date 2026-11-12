import { Fragment, useId, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormSelect } from '@/components/ui/form-select';
import { Input } from '@/components/ui/input';

export type PropertyHistoryRecord = {
    id: number;
    search: string;
    status: string;
    cells: ReactNode[];
    details?: ReactNode;
};

export function filterHistoryRecords<
    T extends { search: string; status: string },
>(records: T[], search: string, status: string): T[] {
    const words = search
        .trim()
        .toLocaleLowerCase()
        .split(/\s+/)
        .filter(Boolean);

    return records.filter(
        (record) =>
            (!status || record.status === status) &&
            words.every((word) =>
                record.search.toLocaleLowerCase().includes(word),
            ),
    );
}

export function PropertyHistory({
    title,
    columns,
    records,
    emptyMessage,
}: {
    title: string;
    columns: string[];
    records: PropertyHistoryRecord[];
    emptyMessage: string;
}) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [expanded, setExpanded] = useState<number | null>(null);
    const historyId = useId();
    const filtered = filterHistoryRecords(records, search, status);
    const pages = Math.max(1, Math.ceil(filtered.length / 5));
    const currentPage = Math.min(page, pages);
    const statuses = [...new Set(records.map((record) => record.status))];

    return (
        <Card>
            <CardHeader className="gap-3">
                <CardTitle className="text-lg">
                    {title}{' '}
                    <span className="text-sm font-normal text-muted-foreground">
                        ({records.length})
                    </span>
                </CardTitle>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        type="search"
                        aria-label={`Search ${title.toLowerCase()}`}
                        placeholder="Search names, references or notes…"
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                        className="sm:max-w-md"
                    />
                    <FormSelect
                        aria-label={`Filter ${title.toLowerCase()}`}
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value);
                            setPage(1);
                        }}
                        className="sm:w-52"
                    >
                        <option value="">All types / statuses</option>
                        {statuses.map((value) => (
                            <option key={value} value={value}>
                                {value.replaceAll('_', ' ')}
                            </option>
                        ))}
                    </FormSelect>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {filtered.length ? (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {columns.map((column) => (
                                        <TableHead key={column}>
                                            {column}
                                        </TableHead>
                                    ))}
                                    <TableHead>Details</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered
                                    .slice(
                                        (currentPage - 1) * 5,
                                        currentPage * 5,
                                    )
                                    .map((record) => (
                                        <Fragment key={record.id}>
                                            <TableRow>
                                                {record.cells.map(
                                                    (cell, index) => (
                                                        <TableCell
                                                            key={index}
                                                            className="text-sm whitespace-normal"
                                                        >
                                                            {cell}
                                                        </TableCell>
                                                    ),
                                                )}
                                                <TableCell>
                                                    {record.details ? (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-expanded={
                                                                expanded ===
                                                                record.id
                                                            }
                                                            aria-controls={`${historyId}-${record.id}`}
                                                            onClick={() =>
                                                                setExpanded(
                                                                    expanded ===
                                                                        record.id
                                                                        ? null
                                                                        : record.id,
                                                                )
                                                            }
                                                        >
                                                            {expanded ===
                                                            record.id
                                                                ? 'Hide details'
                                                                : 'View details'}
                                                        </Button>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                            {record.details && (
                                                <TableRow
                                                    hidden={
                                                        expanded !== record.id
                                                    }
                                                    id={`${historyId}-${record.id}`}
                                                >
                                                    <TableCell
                                                        colSpan={
                                                            columns.length + 1
                                                        }
                                                        className="bg-muted/20"
                                                    >
                                                        <div className="space-y-3 p-2 text-base whitespace-normal">
                                                            {record.details}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    ))}
                            </TableBody>
                        </Table>
                    </div>
                ) : (
                    <p className="py-6 text-sm text-muted-foreground">
                        {records.length
                            ? 'No records match your search and filter.'
                            : emptyMessage}
                    </p>
                )}
                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                    <p role="status" className="text-sm text-muted-foreground">
                        {filtered.length} matching{' '}
                        {filtered.length === 1 ? 'record' : 'records'} · Page{' '}
                        {currentPage} of {pages}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === 1}
                            onClick={() => setPage(currentPage - 1)}
                        >
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={currentPage === pages}
                            onClick={() => setPage(currentPage + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
