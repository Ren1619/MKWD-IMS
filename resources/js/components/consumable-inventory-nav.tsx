import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useAppPage } from '@/hooks/use-app-page';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as storageIndex } from '@/routes/inventory/warehouse';

export function ConsumableInventoryNav({
    active,
}: {
    active: 'items' | 'storage';
}) {
    const { auth } = useAppPage().props;
    const tabs = [
        { key: 'items', title: 'Items', href: itemsIndex() },
        ...(auth.permissions.manage_inventory
            ? [
                  {
                      key: 'storage',
                      title: 'Storage & Batches',
                      href: storageIndex(),
                  },
              ]
            : []),
    ];

    return (
        <nav
            aria-label="Consumable inventory views"
            className="flex gap-2 overflow-x-auto border-b pb-3"
        >
            {tabs.map((tab) => (
                <Button
                    key={tab.key}
                    variant={active === tab.key ? 'secondary' : 'ghost'}
                    render={
                        <Link
                            href={tab.href}
                            aria-current={
                                active === tab.key ? 'page' : undefined
                            }
                            prefetch
                        />
                    }
                >
                    {tab.title}
                </Button>
            ))}
        </nav>
    );
}
