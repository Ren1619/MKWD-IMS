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

const overviewNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const inventoryOperationsNavItems: NavItem[] = [
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
];

const propertyManagementNavItems: NavItem[] = [
    {
        title: 'Asset Registry',
        href: assetsIndex(),
        icon: PackageCheck,
    },
    {
        title: 'Property Accountability',
        href: accountabilityIndex(),
        icon: ClipboardSignature,
    },
];

const reportingNavItems: NavItem[] = [
    {
        title: 'Reports',
        href: reportsIndex(),
        icon: ChartNoAxesCombined,
    },
];

const configurationNavItems: NavItem[] = [
    {
        title: 'Categories',
        href: categoriesIndex(),
        icon: Tags,
    },
];

const administrationNavItems: NavItem[] = [
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
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = useAppPage().props;
    const operationsItems: NavItem[] = auth.permissions.manage_inventory
        ? [
              ...inventoryOperationsNavItems,
              {
                  title: 'Procurement Controls',
                  href: procurementIndex(),
                  icon: ShoppingCart,
              },
          ]
        : inventoryOperationsNavItems;
    const reportsAndConfigurationItems: NavItem[] = auth.permissions
        .manage_inventory
        ? [...reportingNavItems, ...configurationNavItems]
        : reportingNavItems;
    const administrationItems =
        auth.user?.role === 'super_admin' ? administrationNavItems : [];

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
                <NavMain label="Overview" items={overviewNavItems} />
                <NavMain label="Inventory Operations" items={operationsItems} />
                <NavMain
                    label="Property Management"
                    items={propertyManagementNavItems}
                />
                <NavMain
                    label="Reports & Configuration"
                    items={reportsAndConfigurationItems}
                />
                <NavMain label="Administration" items={administrationItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
