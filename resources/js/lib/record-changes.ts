export type RecordChange = 'New' | 'Updated';
type Snapshot = Record<string, unknown>;
type Marker = { label: RecordChange; expires: number };

const primaryKeys = [
    'id',
    'inventory_item_stock_out_id',
    'inventory_item_batch_id',
    'inventory_asset_id',
    'inventory_item_id',
    'inventory_asset_subcategory_id',
    'inv_asset_cat_id',
    'inv_series_cat_id',
    'inv_class_cat_id',
    'inv_mjr_cat_id',
];

export function recordIdentity(record: unknown): string | null {
    if (!record || typeof record !== 'object') {
        return null;
    }

    for (const key of primaryKeys) {
        const value = (record as Snapshot)[key];

        if (typeof value === 'string' || typeof value === 'number') {
            return `${key}:${value}`;
        }
    }

    return null;
}

function records(value: unknown): unknown[] {
    if (Array.isArray(value)) {
        return value.flatMap((record) => [record, ...categoryChildren(record)]);
    }

    if (value && typeof value === 'object') {
        const data = (value as Snapshot).data;

        if (Array.isArray(data)) {
            return data;
        }

        if (recordIdentity(value)) {
            return [value];
        }
    }

    return [];
}

function categoryChildren(record: unknown): unknown[] {
    if (!record || typeof record !== 'object') {
        return [];
    }

    return ['class_categories', 'series_categories', 'subcategories'].flatMap(
        (key) => records((record as Snapshot)[key]),
    );
}

function fingerprint(record: unknown): string {
    return (
        JSON.stringify(record, (_key, value: unknown) =>
            value && typeof value === 'object' && !Array.isArray(value)
                ? Object.fromEntries(
                      Object.entries(value).sort(([left], [right]) =>
                          left.localeCompare(right),
                      ),
                  )
                : value,
        ) ?? ''
    );
}

export function createRecordChanges(lifetime = 60000) {
    const markers = new Map<string, Marker>();
    const listeners = new Set<() => void>();
    let timer: ReturnType<typeof setTimeout> | undefined;
    const keyFor = (namespace: string, collection: string, record: unknown) =>
        `${namespace}:${collection}:${recordIdentity(record)}`;
    const emit = () => listeners.forEach((listener) => listener());

    function expire() {
        const now = Date.now();

        for (const [key, marker] of markers) {
            if (marker.expires <= now) {
                markers.delete(key);
            }
        }

        emit();
        scheduleExpiry();
    }

    function scheduleExpiry() {
        if (timer !== undefined) {
            clearTimeout(timer);
        }

        timer = undefined;

        if (markers.size > 0) {
            timer = setTimeout(
                expire,
                Math.max(
                    0,
                    Math.min(
                        ...[...markers.values()].map(
                            (marker) => marker.expires,
                        ),
                    ) - Date.now(),
                ),
            );
        }
    }

    return {
        compare(namespace: string, before: Snapshot, after: Snapshot) {
            for (const [collection, value] of Object.entries(after)) {
                if (!(collection in before)) {
                    continue;
                }

                const previous = new Map(
                    records(before[collection]).map((record) => [
                        recordIdentity(record),
                        fingerprint(record),
                    ]),
                );
                const current = records(value);
                const currentIds = new Set(current.map(recordIdentity));

                for (const record of records(before[collection])) {
                    if (!currentIds.has(recordIdentity(record))) {
                        markers.delete(keyFor(namespace, collection, record));
                    }
                }

                for (const record of current) {
                    const id = recordIdentity(record);

                    if (id === null) {
                        continue;
                    }

                    const old = previous.get(id);

                    if (old === fingerprint(record)) {
                        continue;
                    }

                    const key = keyFor(namespace, collection, record);
                    const existing = markers.get(key);
                    markers.set(key, {
                        label:
                            existing?.label === 'New' &&
                            existing.expires > Date.now()
                                ? 'New'
                                : old === undefined
                                  ? 'New'
                                  : 'Updated',
                        expires: Date.now() + lifetime,
                    });
                }
            }

            emit();
            scheduleExpiry();
        },
        get(
            namespace: string,
            collection: string,
            record: unknown,
        ): RecordChange | null {
            const marker = markers.get(keyFor(namespace, collection, record));

            return marker && marker.expires > Date.now() ? marker.label : null;
        },
        subscribe(listener: () => void) {
            listeners.add(listener);

            return () => {
                listeners.delete(listener);
            };
        },
        clear() {
            markers.clear();

            if (timer !== undefined) {
                clearTimeout(timer);
            }

            timer = undefined;
            emit();
        },
    };
}

export const recordChanges = createRecordChanges();
