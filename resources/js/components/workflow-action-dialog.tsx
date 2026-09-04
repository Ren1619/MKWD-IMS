import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type WorkflowActionDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    processing: boolean;
    remarks: string;
    onRemarksChange: (remarks: string) => void;
    onConfirm: () => void;
    remarksRequired?: boolean;
    destructive?: boolean;
    error?: string;
    children?: ReactNode;
};

export function WorkflowActionDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    processing,
    remarks,
    onRemarksChange,
    onConfirm,
    remarksRequired = false,
    destructive = false,
    error,
    children,
}: WorkflowActionDialogProps) {
    const [attested, setAttested] = useState(false);

    function changeOpen(nextOpen: boolean) {
        if (!nextOpen) {
            setAttested(false);
        }

        onOpenChange(nextOpen);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (attested && (!remarksRequired || remarks.trim() !== '')) {
            onConfirm();
        }
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>

                    {children}

                    <div className="grid gap-2">
                        <Label htmlFor="workflow-remarks">
                            Remarks{' '}
                            {remarksRequired ? '(required)' : '(optional)'}
                        </Label>
                        <textarea
                            id="workflow-remarks"
                            rows={4}
                            required={remarksRequired}
                            value={remarks}
                            onChange={(event) =>
                                onRemarksChange(event.target.value)
                            }
                            className="min-h-24 w-full rounded-lg border border-input bg-background px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"
                        />
                    </div>

                    <label className="flex min-h-11 items-start gap-3 rounded-lg border p-3 text-sm">
                        <input
                            type="checkbox"
                            required
                            checked={attested}
                            onChange={(event) =>
                                setAttested(event.target.checked)
                            }
                            className="mt-0.5 size-5"
                        />
                        <span>
                            I confirm that I am authorized to perform this
                            action and that the supporting record is accurate.
                        </span>
                    </label>

                    <InputError message={error} />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            disabled={processing}
                            onClick={() => changeOpen(false)}
                        >
                            Go back
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            className="min-h-11"
                            disabled={
                                processing ||
                                !attested ||
                                (remarksRequired && remarks.trim() === '')
                            }
                        >
                            {processing ? 'Saving…' : confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
