import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import type { ReturnDocument } from './types';

export type RmsFormData = Pick<
    ReturnDocument,
    | 'document_no'
    | 'return_date'
    | 'returned_by_name'
    | 'department_name'
    | 'reason'
> & {
    document_date?: string;
    lines: {
        id: number;
        quantity_declared: number | string;
        unit_of_measure: string;
        item_name: string;
        source_issue_reference: string | null;
        condition: string | null;
        inspection_remarks: string | null;
    }[];
    actions?: ReturnDocument['actions'];
};

const date = (value?: string) => (value ? value.slice(0, 10) : 'Pending');

export default function RmsFormView({ document }: { document: RmsFormData }) {
    const noted = document.actions?.find((entry) => entry.action === 'note');
    const received = document.actions?.find(
        (entry) => entry.action === 'receive',
    );
    const references = [
        ...new Set(
            document.lines
                .map((line) => line.source_issue_reference)
                .filter(Boolean),
        ),
    ];

    return (
        <section
            aria-label="Return Materials Slip"
            className="space-y-4 rounded-lg border border-slate-200 bg-white p-4 text-slate-900 sm:p-6"
        >
            <h2 className="text-center text-lg font-semibold">
                RETURN MATERIALS SLIP
            </h2>
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-slate-600">RMS Number</dt>
                    <dd>{document.document_no}</dd>
                </div>
                <div>
                    <dt className="text-slate-600">Date</dt>
                    <dd>
                        {date(document.document_date ?? document.return_date)}
                    </dd>
                </div>
                <div>
                    <dt className="text-slate-600">
                        Reference (IS / ICS / ARE No.)
                    </dt>
                    <dd>{references.join(', ') || 'Select original issue'}</dd>
                </div>
                <div>
                    <dt className="text-slate-600">Department</dt>
                    <dd>{document.department_name || 'Pending'}</dd>
                </div>
            </dl>
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-white hover:bg-white data-[state=selected]:bg-white">
                            {[
                                'No.',
                                'Qty',
                                'UOM',
                                'Description of Item',
                                'Condition',
                                'Remarks',
                            ].map((heading) => (
                                <TableHead
                                    key={heading}
                                    className="text-slate-900"
                                >
                                    {heading}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {document.lines.map((line, position) => (
                            <TableRow
                                key={line.id}
                                className="bg-white hover:bg-white data-[state=selected]:bg-white"
                            >
                                <TableCell>{position + 1}</TableCell>
                                <TableCell>{line.quantity_declared}</TableCell>
                                <TableCell>{line.unit_of_measure}</TableCell>
                                <TableCell>{line.item_name}</TableCell>
                                <TableCell>
                                    {line.condition === 'good'
                                        ? 'Serviceable'
                                        : (line.condition ??
                                          'Awaiting inspection')}
                                </TableCell>
                                <TableCell>
                                    {line.inspection_remarks ??
                                        'Pending inspection'}
                                </TableCell>
                            </TableRow>
                        ))}
                        {document.lines.length === 0 && (
                            <TableRow className="bg-white hover:bg-white data-[state=selected]:bg-white">
                                <TableCell colSpan={6}>
                                    Select materials to prepare this RMS.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="text-sm">
                <p className="font-medium">Purpose / Reason for return</p>
                <p className="whitespace-pre-wrap">
                    {document.reason || 'Pending'}
                </p>
            </div>
            <div className="grid gap-6 pt-3 text-sm sm:grid-cols-3">
                <div>
                    <p className="font-medium">Prepared by</p>
                    <p>{document.returned_by_name || 'Select end-user'}</p>
                    <p className="text-slate-600">End-user</p>
                </div>
                <div>
                    <p className="font-medium">Noted by</p>
                    <p>
                        {noted
                            ? String(
                                  noted.metadata?.manager_name ??
                                      'Not recorded',
                              )
                            : 'Pending acknowledgment'}
                    </p>
                    <p className="text-slate-600">
                        Department Manager
                        {noted ? ` / ${date(noted.created_at)}` : ''}
                    </p>
                </div>
                <div>
                    <p className="font-medium">Received by</p>
                    <p>{received?.actor_name ?? 'Pending receipt'}</p>
                    <p className="text-slate-600">
                        PMMD Office /{' '}
                        {received
                            ? String(
                                  received.metadata?.received_date ??
                                      date(received.created_at),
                              )
                            : 'Date pending'}
                    </p>
                </div>
            </div>
        </section>
    );
}
