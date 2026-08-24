import { Link } from '@inertiajs/react';
import { Cable, Palette, ShieldCheck, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useAppPage } from '@/hooks/use-app-page';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editHrisIntegration } from '@/routes/hris-integration';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = useAppPage().props;
    const sidebarNavItems: NavItem[] = [
        {
            title: 'Profile',
            href: edit(),
            icon: UserRound,
        },
        {
            title: 'Security',
            href: editSecurity(),
            icon: ShieldCheck,
        },
        ...(auth.permissions.manage_integrations
            ? [
                  {
                      title: 'Employee data API',
                      href: editHrisIntegration(),
                      icon: Cable,
                  },
              ]
            : []),
        {
            title: 'Appearance',
            href: editAppearance(),
            icon: Palette,
        },
    ];

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your account, security, integrations, and appearance"
            />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <aside className="w-full max-w-2xl lg:w-56 lg:shrink-0">
                    <nav
                        className="flex flex-col gap-1 rounded-xl border bg-card p-2 shadow-sm"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                nativeButton={false}
                                render={<Link href={item.href} />}
                                className={cn(
                                    'w-full justify-start gap-2.5 px-3',
                                    {
                                        'bg-muted font-medium':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                {item.icon && <item.icon className="h-4 w-4" />}
                                {item.title}
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="lg:hidden" />

                <div className="min-w-0 flex-1 md:max-w-2xl">
                    <section className="max-w-2xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
