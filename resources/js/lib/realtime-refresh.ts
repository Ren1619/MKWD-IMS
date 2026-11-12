export type RecordScope = 'inventory' | 'administration' | 'account';

export function affectsPage(component: string, scope: RecordScope): boolean {
    if (scope === 'account') {
        return true;
    }

    if (scope === 'inventory') {
        return component.startsWith('Inventory/') || component === 'dashboard';
    }

    return (
        component.startsWith('Admin/') ||
        component === 'settings/hris-integration'
    );
}

type RefreshOptions = {
    component: () => string;
    blocked: () => boolean;
    reload: (finish: (retry?: boolean) => void) => void;
    schedule?: (
        callback: () => void,
        delay: number,
    ) => ReturnType<typeof setTimeout>;
    cancel?: (timer: ReturnType<typeof setTimeout>) => void;
};

export function createRealtimeRefresh(options: RefreshOptions) {
    const pending = new Set<RecordScope>();
    const schedule = options.schedule ?? setTimeout;
    const cancel = options.cancel ?? clearTimeout;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let refreshing = false;
    let disposed = false;

    function flush() {
        timer = undefined;

        if (disposed || refreshing || pending.size === 0) {
            return;
        }

        for (const scope of pending) {
            if (!affectsPage(options.component(), scope)) {
                pending.delete(scope);
            }
        }

        if (pending.size === 0) {
            return;
        }

        if (options.blocked()) {
            queue(1000);

            return;
        }

        const scopes = [...pending];
        pending.clear();
        refreshing = true;
        options.reload((retry = false) => {
            refreshing = false;

            if (disposed) {
                return;
            }

            if (retry) {
                scopes.forEach((scope) => pending.add(scope));
            }

            queue(retry ? 5000 : 250);
        });
    }

    function queue(delay = 250) {
        if (
            !disposed &&
            !refreshing &&
            timer === undefined &&
            pending.size > 0
        ) {
            timer = schedule(flush, delay);
        }
    }

    return {
        notify(scope: RecordScope) {
            if (!disposed && affectsPage(options.component(), scope)) {
                pending.add(scope);
                queue();
            }
        },
        resume: queue,
        dispose() {
            disposed = true;
            pending.clear();

            if (timer !== undefined) {
                cancel(timer);
            }
        },
    };
}
