import { router, usePage } from '@inertiajs/react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { authenticate } from '@/actions/Illuminate/Broadcasting/BroadcastController';
import { createRealtimeRefresh } from '@/lib/realtime-refresh';
import type { RecordScope } from '@/lib/realtime-refresh';
import { recordChanges } from '@/lib/record-changes';
import { dashboard } from '@/routes';

export type RealtimeSettings = {
    enabled: boolean;
    key: string | null;
    host: string;
    port: number;
    scheme: string;
};

export function useRealtimeUpdates(): void {
    const page = usePage<{ realtime?: RealtimeSettings | null }>();
    const latestPage = useRef(page);
    useEffect(() => {
        latestPage.current = page;
    }, [page]);
    const userId = page.props.auth?.user?.id;
    const active = page.props.auth?.user?.is_active;
    const admin = page.props.auth?.permissions?.view_audit_logs ?? false;
    const { enabled, key, host, port, scheme } = page.props.realtime ?? {};

    useEffect(() => {
        if (!userId || !active || !enabled || !key || !host || !port) {
            return;
        }

        const echo = new Echo({
            broadcaster: 'reverb',
            Pusher,
            key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: authenticate.url(),
        });
        const dirtyForms = new Set<HTMLFormElement>();
        let visiting = 0;
        let connected = false;
        const refresh = createRealtimeRefresh({
            component: () => latestPage.current.component,
            blocked: () => {
                for (const form of dirtyForms) {
                    if (!form.isConnected) {
                        dirtyForms.delete(form);
                    }
                }

                return (
                    visiting > 0 ||
                    document.hidden ||
                    !navigator.onLine ||
                    dirtyForms.size > 0 ||
                    document.querySelector(
                        '[role="dialog"], [role="alertdialog"], [data-realtime-pause]',
                    ) !== null
                );
            },
            reload: (finish) => {
                let retry = false;
                const before = latestPage.current;
                router.reload({
                    except: ['errors', 'flash'],
                    async: true,
                    showProgress: false,
                    headers: { 'X-Realtime-Refresh': '1' },
                    onSuccess: (after) => {
                        if (
                            after.component === before.component &&
                            after.url === before.url
                        ) {
                            recordChanges.compare(
                                `${userId}:${after.component}`,
                                before.props,
                                after.props,
                            );
                        }
                    },
                    onNetworkError: () => {
                        retry = true;

                        return false;
                    },
                    onHttpException: (response) => {
                        if ([403, 404, 410].includes(response.status)) {
                            toast.info('This record is no longer available.');
                            router.visit(dashboard(), { replace: true });
                        } else {
                            retry = true;
                        }

                        return false;
                    },
                    onFinish: () => finish(retry),
                });
            },
        });

        const channels: [string, RecordScope][] = [
            ['records.inventory', 'inventory'],
            [`records.account.${userId}`, 'account'],
        ];

        if (admin) {
            channels.push(['records.administration', 'administration']);
        }

        for (const [channel, scope] of channels) {
            echo.private(channel)
                .listen('.records.changed', () => refresh.notify(scope))
                .subscribed(() => refresh.notify(scope));
        }

        const reconnect = () => {
            if (connected) {
                refresh.notify('account');
            }

            connected = true;
        };
        echo.connector.pusher.connection.bind('connected', reconnect);
        const resume = () => {
            if (!document.hidden) {
                refresh.notify('account');
            }
        };
        const markDirty = (event: Event) => {
            const element = event.target;

            if (element instanceof HTMLElement) {
                const form = element.closest('form');

                if (form) {
                    dirtyForms.add(form);
                }
            }
        };
        const reset = (event: Event) => {
            if (event.target instanceof HTMLFormElement) {
                dirtyForms.delete(event.target);
            }

            refresh.resume();
        };
        const removers = [
            router.on('start', () => {
                visiting++;
            }),
            router.on('finish', () => {
                visiting = Math.max(0, visiting - 1);
                refresh.resume();
            }),
            router.on('navigate', (event) => {
                latestPage.current = event.detail.page as typeof page;

                if (
                    Object.keys(event.detail.page.props.errors ?? {}).length ===
                    0
                ) {
                    dirtyForms.clear();
                }

                refresh.resume();
            }),
        ];
        document.addEventListener('input', markDirty, true);
        document.addEventListener('change', markDirty, true);
        document.addEventListener('reset', reset, true);
        document.addEventListener('visibilitychange', resume);
        window.addEventListener('online', resume);
        window.addEventListener('focus', resume);
        const observer = new MutationObserver(() => refresh.resume());
        observer.observe(document.body, { childList: true, subtree: true });

        return () => {
            refresh.dispose();
            observer.disconnect();
            removers.forEach((remove) => remove());
            document.removeEventListener('input', markDirty, true);
            document.removeEventListener('change', markDirty, true);
            document.removeEventListener('reset', reset, true);
            document.removeEventListener('visibilitychange', resume);
            window.removeEventListener('online', resume);
            window.removeEventListener('focus', resume);
            echo.disconnect();
        };
    }, [userId, active, admin, enabled, key, host, port, scheme]);
}
