import { ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useAppPage } from '@/hooks/use-app-page';
import { useIsMobile } from '@/hooks/use-mobile';

export function NavUser() {
    const { auth } = useAppPage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);

    if (!auth.user) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu
                    open={isUserMenuOpen}
                    onOpenChange={setIsUserMenuOpen}
                >
                    <DropdownMenuTrigger
                        aria-expanded={isUserMenuOpen}
                        render={
                            <SidebarMenuButton
                                size="lg"
                                className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                                data-test="sidebar-menu-button"
                            />
                        }
                    >
                        <UserInfo user={auth.user} />
                        <ChevronsUpDown className="ml-auto size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        }
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
