import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ReturnDocumentSummary = {
    document_no: string;
    status: string;
    return_date: string | null;
    lines: {
        item_description: string;
        quantity: number;
        unit_of_measure: string;
    }[];
};

export function ReturnSlipSummary({
    documents,
    reference,
    legacyNumber,
    legacyDate,
    onClose,
}: {
    documents: ReturnDocumentSummary[];
    reference: string;
    legacyNumber: string | null;
    legacyDate: string | null;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Return Slip</DialogTitle>
                    <DialogDescription>
                        Return records for {reference}.
                    </DialogDescription>
                </DialogHeader>
                {legacyNumber && (
                    <p className="text-sm">
                        Recorded return slip: {legacyNumber}
                        {legacyDate ? ` (${legacyDate.slice(0, 10)})` : ''}
                    </p>
                )}
                {documents.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No return documents have been recorded for this request.
                    </p>
                )}
                {documents.map((document) => (
                    <section key={document.document_no} className="grid gap-2">
                        <h3 className="font-medium">{document.document_no}</h3>
                        <p className="text-sm text-muted-foreground">
                            {document.return_date} ·{' '}
                            {document.status.replaceAll('_', ' ')}
                        </p>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Quantity returned</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {document.lines.map((line, index) => (
                                    <TableRow key={index}>
                                        <TableCell>
                                            {line.item_description}
                                        </TableCell>
                                        <TableCell>
                                            {line.quantity}{' '}
                                            {line.unit_of_measure}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </section>
                ))}
                <Button variant="outline" onClick={onClose}>
                    Close
                </Button>
            </DialogContent>
        </Dialog>
    );
}
