import { Link } from '@inertiajs/react';
import {
    Boxes,
    ChartNoAxesCombined,
    ClipboardSignature,
    LayoutGrid,
    PackageCheck,
    PackageOpen,
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
import { index as countsIndex } from '@/routes/inventory/counts';
import { index as deliveriesIndex } from '@/routes/inventory/deliveries';
import { index as itemsIndex } from '@/routes/inventory/items';
import { index as monthlyReportsIndex } from '@/routes/inventory/monthly_reports';
import { index as procurementIndex } from '@/routes/inventory/procurement';
import { index as reportsIndex } from '@/routes/inventory/reports';
import { index as requestsIndex } from '@/routes/inventory/requests';
import { index as returnsIndex } from '@/routes/inventory/returns';
import { index as semiExpendableIndex } from '@/routes/inventory/semi_expendable';
import { index as warehouseIndex } from '@/routes/inventory/warehouse';
import { index as transfersIndex } from '@/routes/inventory/transfers';
import { index as disposalsIndex } from '@/routes/inventory/disposals';
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
        title: 'PPE',
        href: assetsIndex(),
        icon: PackageCheck,
    },
    {
        title: 'Semi-Expendable Property',
        href: semiExpendableIndex(),
        icon: PackageOpen,
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
        title: 'Disposal / Waste Materials',
        href: disposalsIndex(),
        icon: ClipboardSignature,
    },
    {
        title: 'Property Transfers',
        href: transfersIndex(),
        icon: ClipboardSignature,
    },
    {
        title: 'Warehouse Storage',
        href: warehouseIndex(),
        icon: Boxes,
    },
    {
        title: 'Monthly Report Packages',
        href: monthlyReportsIndex(),
        icon: ScrollText,
    },
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
const procurementNavItems: NavItem[] = [
    {
        title: 'Delivery Inspection',
        href: deliveriesIndex(),
        icon: PackageCheck,
    },
    {
        title: 'Requests',
        href: procurementIndex(),
        icon: ShoppingCart,
    },
];

export function AppSidebar() {
    const { auth } = useAppPage().props;
    const reportsAndConfigurationItems: NavItem[] = auth.permissions
        .manage_inventory
        ? [
              ...(auth.permissions.view_property_governance
                  ? reportingNavItems
                  : []),
              ...configurationNavItems,
          ]
        : auth.permissions.view_property_governance
          ? reportingNavItems
          : [];
    const propertyManagementItems = auth.permissions.view_property_governance
        ? [
              ...propertyManagementNavItems,
              {
                  title: 'Property Accountability',
                  href: accountabilityIndex(),
                  icon: ClipboardSignature,
              },
          ]
        : propertyManagementNavItems;
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
                <NavMain
                    label="Inventory Operations"
                    items={[
                        ...inventoryOperationsNavItems,
                        ...(auth.permissions.manage_inventory
                            ? [
                                  {
                                      title: 'Physical Counts',
                                      href: countsIndex(),
                                      icon: ClipboardList,
                                  },
                                  {
                                      title: 'Return to Stock',
                                      href: returnsIndex(),
                                      icon: PackageOpen,
                                  },
                              ]
                            : []),
                    ]}
                />
                <NavMain
                    label="Procurement"
                    items={
                        auth.permissions.manage_inventory
                            ? procurementNavItems
                            : []
                    }
                />
                <NavMain
                    label="Property Management"
                    items={propertyManagementItems}
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
