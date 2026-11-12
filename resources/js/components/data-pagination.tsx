import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { PaginationLink as PaginationLinkData } from '@/types/inventory';

type DataPaginationProps = {
    links: PaginationLinkData[];
    onNavigate?: (url: string) => void;
};

function DisabledPaginationLink({
    direction,
}: {
    direction: 'previous' | 'next';
}) {
    const Component =
        direction === 'previous' ? PaginationPrevious : PaginationNext;

    return (
        <Component
            href="#"
            aria-disabled="true"
            className="pointer-events-none opacity-50"
            onClick={(event) => event.preventDefault()}
        />
    );
}

function visitPage(
    event: MouseEvent<HTMLAnchorElement>,
    url: string,
    onNavigate?: (url: string) => void,
) {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    event.preventDefault();

    if (onNavigate) {
        onNavigate(url);
    } else {
        router.visit(url);
    }
}

export function DataPagination({ links, onNavigate }: DataPaginationProps) {
    if (links.length < 3) {
        return null;
    }

    const previous = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);

    return (
        <Pagination className="justify-end pt-4">
            <PaginationContent className="flex-wrap justify-end">
                <PaginationItem>
                    {previous.url ? (
                        <PaginationPrevious
                            href={previous.url}
                            onClick={(event) =>
                                visitPage(
                                    event,
                                    previous.url as string,
                                    onNavigate,
                                )
                            }
                        />
                    ) : (
                        <DisabledPaginationLink direction="previous" />
                    )}
                </PaginationItem>

                {pages.map((page, index) => (
                    <PaginationItem key={`${page.label}-${index}`}>
                        {page.url ? (
                            <PaginationLink
                                href={page.url}
                                isActive={page.active}
                                aria-label={`Go to page ${page.label}`}
                                onClick={(event) =>
                                    visitPage(
                                        event,
                                        page.url as string,
                                        onNavigate,
                                    )
                                }
                            >
                                {page.label}
                            </PaginationLink>
                        ) : (
                            <PaginationEllipsis />
                        )}
                    </PaginationItem>
                ))}

                <PaginationItem>
                    {next.url ? (
                        <PaginationNext
                            href={next.url}
                            onClick={(event) =>
                                visitPage(event, next.url as string, onNavigate)
                            }
                        />
                    ) : (
                        <DisabledPaginationLink direction="next" />
                    )}
                </PaginationItem>
            </PaginationContent>
        </Pagination>
    );
}
