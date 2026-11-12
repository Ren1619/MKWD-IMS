import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/inventory/assets';
import { index as areIndex } from '@/routes/inventory/assets/ares';

export function PpeModuleNav({
    active,
}: {
    active: 'register' | 'ares' | 'noted';
}) {
    const tabs = [
        { key: 'register', title: 'PPE register', href: index() },
        { key: 'ares', title: 'AREs', href: areIndex() },
        {
            key: 'noted',
            title: 'Noted AREs',
            href: areIndex({ query: { view: 'noted' } }),
        },
    ];

    return (
        <nav
            aria-label="PPE views"
            className="flex gap-2 overflow-x-auto border-b pb-3"
        >
            {tabs.map((tab) => (
                <Button
                    key={tab.key}
                    nativeButton={false}
                    variant={active === tab.key ? 'secondary' : 'ghost'}
                    render={
                        <Link
                            href={tab.href}
                            aria-current={
                                active === tab.key ? 'page' : undefined
                            }
                        />
                    }
                >
                    {tab.title}
                </Button>
            ))}
        </nav>
    );
}
