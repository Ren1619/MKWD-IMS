import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types/auth';

type AppPageProps = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
};

export function useAppPage() {
    return usePage<AppPageProps>();
}
