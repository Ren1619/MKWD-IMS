import { useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { show } from '@/routes/inventory/warehouse';
import { BatchStorageContent } from './Show';
import type { BatchStorageProps } from './Show';

export function BatchStorageDialog({
    batch,
    warehouses,
    onClose,
}: BatchStorageProps & { onClose: () => void }) {
    const [details, setDetails] = useState<BatchStorageProps | null>(null);
    const [error, setError] = useState(false);
    const [saving, setSaving] = useState(false);
    const [confirmCancel, setConfirmCancel] = useState(false);
    const { get, cancel, processing } = useHttp<
        Record<string, never>,
        BatchStorageProps
    >({});
    const load = useCallback(() => {
        void get(show.url(batch.inventory_item_batch_id), {
            onSuccess: (data) => {
                setDetails(data);
                setError(false);
            },
            onHttpException: () => setError(true),
            onNetworkError: () => setError(true),
        }).catch(() => undefined);
    }, [get, batch.inventory_item_batch_id]);

    useEffect(() => {
        load();

        return cancel;
    }, [load, cancel]);

    return (
        <Dialog
            open
            disablePointerDismissal
            onOpenChange={(_, event) => event.cancel()}
        >
            <DialogContent
                showCloseButton={false}
                className="flex max-h-[90dvh] flex-col sm:max-w-5xl"
            >
                <DialogHeader>
                    <DialogTitle>Stockroom storage</DialogTitle>
                    <DialogDescription>
                        {batch.item.name} — Batch {batch.batch_number}
                    </DialogDescription>
                </DialogHeader>
                <div className="min-h-0 overflow-y-auto" aria-busy={processing}>
                    {error ? (
                        <div role="alert" className="grid gap-3">
                            <p>
                                Unable to load storage details. Please try
                                again.
                            </p>
                            <Button variant="outline" onClick={load}>
                                Retry
                            </Button>
                        </div>
                    ) : details ? (
                        <BatchStorageContent
                            {...details}
                            warehouses={warehouses}
                            embedded
                            onSaved={load}
                            onSaving={setSaving}
                        />
                    ) : (
                        <p
                            role="status"
                            className="animate-pulse py-8 text-center text-muted-foreground"
                        >
                            Loading storage details...
                        </p>
                    )}
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={saving}
                        onClick={() => setConfirmCancel(true)}
                    >
                        Cancel
                    </Button>
                </DialogFooter>
                <Dialog
                    open={confirmCancel}
                    disablePointerDismissal
                    onOpenChange={(open) => setConfirmCancel(open)}
                >
                    <DialogContent showCloseButton={false}>
                        <DialogHeader>
                            <DialogTitle>Close stockroom storage?</DialogTitle>
                            <DialogDescription>
                                Any unsaved placement changes will be discarded.
                                Storage confirmations already recorded will be
                                kept.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setConfirmCancel(false)}
                            >
                                Keep editing
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={onClose}
                            >
                                Yes, close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </DialogContent>
        </Dialog>
    );
}
