import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;
                            const isCurrentPage = item.href
                                ? isCurrentUrl(item.href)
                                : isLast;

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {isCurrentPage ? (
                                            <BreadcrumbPage>
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : item.href ? (
                                            <BreadcrumbLink
                                                render={
                                                    <Link href={item.href} />
                                                }
                                            >
                                                {item.title}
                                            </BreadcrumbLink>
                                        ) : (
                                            <span>{item.title}</span>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
