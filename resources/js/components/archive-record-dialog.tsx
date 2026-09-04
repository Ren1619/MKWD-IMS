import { Form } from '@inertiajs/react';
import { Archive } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { RouteDefinition } from '@/wayfinder';

export function ArchiveRecordDialog({
    action,
    recordName,
    recordType,
    prerequisite,
    open,
    onOpenChange,
    showTrigger = true,
}: {
    action: RouteDefinition<'delete'>;
    recordName: string;
    recordType: string;
    prerequisite: string;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    showTrigger?: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {showTrigger && (
                <DialogTrigger
                    render={
                        <Button
                            size="icon"
                            variant="ghost"
                            aria-label={`Archive ${recordName}`}
                        />
                    }
                >
                    <Archive />
                </DialogTrigger>
            )}
            <DialogContent onClick={(event) => event.stopPropagation()}>
                <DialogHeader>
                    <DialogTitle>Archive {recordName}?</DialogTitle>
                    <DialogDescription>
                        The {recordType} will leave the active registry, but its
                        complete history will be preserved. You can restore it
                        later from the archived records view.
                    </DialogDescription>
                </DialogHeader>
                <p className="rounded-lg border bg-muted/50 p-3 text-sm text-muted-foreground">
                    {prerequisite}
                </p>
                <Form action={action} options={{ preserveScroll: true }}>
                    {({ processing }) => (
                        <DialogFooter>
                            <DialogClose render={<Button variant="outline" />}>
                                Cancel
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                <Archive />
                                {processing ? 'Archiving…' : 'Archive'}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
