export type ValidationSummaryEntry = {
    id: string;
    label: string;
    message: string;
    target?: HTMLElement;
};

export default function ValidationSummary({
    entries,
    onSelect,
}: {
    entries: ValidationSummaryEntry[];
    onSelect: (entry: ValidationSummaryEntry) => void;
}) {
    if (import.meta.env.PROD || entries.length === 0) {
        return null;
    }

    return (
        <aside
            data-validation-summary
            aria-label="Validation errors"
            className="rounded-lg border border-red-300 bg-red-50 p-3 text-red-950 shadow-sm dark:border-red-800 dark:bg-red-950 dark:text-red-100"
        >
            <p role="status" aria-live="polite" className="font-semibold">
                {entries.length}{' '}
                {entries.length === 1 ? 'error needs' : 'errors need'} your
                attention
            </p>
            <p className="mt-1 text-sm">
                Review every item below. Select an error to find it in the form.
            </p>
            <ol className="mt-2 max-h-[22dvh] list-decimal space-y-1 overflow-y-auto pl-5 text-sm">
                {entries.map((entry) => (
                    <li key={entry.id}>
                        {entry.target ? (
                            <button
                                type="button"
                                onClick={() => onSelect(entry)}
                                className="rounded-sm text-left whitespace-normal underline underline-offset-2 outline-none focus-visible:ring-2 focus-visible:ring-red-600"
                            >
                                <span className="font-medium">
                                    {entry.label}:
                                </span>{' '}
                                {entry.message}
                            </button>
                        ) : (
                            <>
                                <span className="font-medium">
                                    {entry.label}:
                                </span>{' '}
                                {entry.message}
                            </>
                        )}
                    </li>
                ))}
            </ol>
        </aside>
    );
}
