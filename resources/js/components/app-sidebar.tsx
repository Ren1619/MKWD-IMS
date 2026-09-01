import { Link } from '@inertiajs/react';
import {
    Boxes,
    ChartNoAxesCombined,
    ClipboardSignature,
    LayoutGrid,
    PackageCheck,
    ScrollText,
    ClipboardList,
    ShoppingCart,
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
import { index as accountabilityIndex } from '@/routes/inventory/accountability';
import { index as assetsIndex } from '@/routes/inventory/assets';
import { index as categoriesIndex } from '@/routes/inventory/categories';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as procurementIndex } from '@/routes/inventory/procurement';
import { index as reportsIndex } from '@/routes/inventory/reports';
import { index as requestsIndex } from '@/routes/inventory/requests';
import type { NavItem } from '@/types';

const inventoryNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Supply Requests',
        href: requestsIndex(),
        icon: ClipboardList,
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
        title: 'Property Accountability',
        href: accountabilityIndex(),
        icon: ClipboardSignature,
    },
    {
        title: 'Categories',
        href: categoriesIndex(),
        icon: Tags,
    },
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: ChartNoAxesCombined,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = useAppPage().props;
    const roleInventoryItems = auth.permissions.manage_inventory
        ? [
              ...inventoryNavItems,
              {
                  title: 'Procurement Controls',
                  href: procurementIndex(),
                  icon: ShoppingCart,
              },
          ]
        : inventoryNavItems.filter(
              (item) =>
                  item.title === 'Dashboard' ||
                  item.title === 'Supply Requests' ||
                  item.title === 'Consumable Inventory' ||
                  item.title === 'Property & Equipment' ||
                  item.title === 'Property Accountability' ||
                  item.title === 'Reports',
          );
    const mainNavItems: NavItem[] =
        auth.user?.role === 'super_admin'
            ? [
                  ...roleInventoryItems,
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
            : roleInventoryItems;

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
