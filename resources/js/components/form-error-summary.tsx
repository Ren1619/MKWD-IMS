import { CircleAlert } from 'lucide-react';

type FormErrorSummaryProps = {
    errors: Record<string, string>;
    title?: string;
};

export function FormErrorSummary({
    errors,
    title = 'Please correct the highlighted fields.',
}: FormErrorSummaryProps) {
    const messages = [...new Set(Object.values(errors))];

    if (messages.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            tabIndex={-1}
            className="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive"
        >
            <p className="flex items-center gap-2 font-medium">
                <CircleAlert className="size-4" />
                {title}
            </p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        </div>
    );
}
