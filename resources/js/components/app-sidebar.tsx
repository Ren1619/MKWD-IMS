import { Link } from '@inertiajs/react';
import {
    Boxes,
    LayoutGrid,
    PackageCheck,
    ScrollText,
    ShieldCheck,
    Tags,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppPage } from '@/hooks/use-app-page';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/admin/audit-logs';
import { index as usersIndex } from '@/routes/admin/users';
import { index as assetsIndex } from '@/routes/inventory/assets';
import { index as categoriesIndex } from '@/routes/inventory/categories';
import { index as itemsIndex } from '@/routes/inventory/items';
import type { NavItem } from '@/types';

const inventoryNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Consumable Inventory',
        href: itemsIndex(),
        icon: Boxes,
    },
    {
        title: 'Property & Equipment',
        href: assetsIndex(),
        icon: PackageCheck,
    },
    {
        title: 'Categories',
        href: categoriesIndex(),
        icon: Tags,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = useAppPage().props;
    const mainNavItems: NavItem[] =
        auth.user?.role === 'super_admin'
            ? [
                  ...inventoryNavItems,
                  {
                      title: 'User Management',
                      href: usersIndex(),
                      icon: ShieldCheck,
                  },
                  {
                      title: 'Audit Logs',
                      href: auditLogsIndex(),
                      icon: ScrollText,
                  },
              ]
            : inventoryNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            render={<Link href={dashboard()} prefetch />}
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
