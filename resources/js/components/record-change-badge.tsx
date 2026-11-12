import { usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { Badge } from '@/components/ui/badge';
import { recordChanges } from '@/lib/record-changes';

export function RecordChangeBadge({
    collection,
    record,
}: {
    collection: string;
    record: unknown;
}) {
    const page = usePage();
    const namespace = `${page.props.auth?.user?.id}:${page.component}`;
    const label = useSyncExternalStore(
        recordChanges.subscribe,
        () => recordChanges.get(namespace, collection, record),
        () => null,
    );

    if (!label) {
        return null;
    }

    return (
        <Badge
            variant="outline"
            className={
                label === 'New'
                    ? 'ml-2 border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950 dark:text-emerald-200'
                    : 'ml-2 border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-200'
            }
            aria-label={`${label} record`}
            title={`${label === 'New' ? 'Newly arrived in this view' : 'Changed since the last refresh'}. Clears after one minute.`}
        >
            {label}
        </Badge>
    );
}
