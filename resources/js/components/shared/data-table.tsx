import * as React from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';

function Table({ className, ...props }: React.ComponentProps<'table'>) {
    return (
        <div data-slot="table-container" className="rounded-sm p-0.5">
            <table
                data-slot="table"
                className={cn('w-full caption-bottom text-sm', className)}
                {...props}
            />
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return (
        <thead
            data-slot="table-header"
            className={cn('[&_tr]:border-b', className)}
            {...props}
        />
    );
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return (
        <tbody
            data-slot="table-body"
            className={cn('[&_tr:last-child]:border-0', className)}
            {...props}
        />
    );
}

function TableFooter({ className, ...props }: React.ComponentProps<'tfoot'>) {
    return (
        <tfoot
            data-slot="table-footer"
            className={cn(
                'border-t bg-muted/50 font-medium [&>tr]:last:border-b-0',
                className,
            )}
            {...props}
        />
    );
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return (
        <tr
            data-slot="table-row"
            className={cn(
                'border-b border-gray-200 transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted',
                className,
            )}
            {...props}
        />
    );
}

function TableRowLink({
    href,
    className,
    onClick,
    onKeyDown,
    ...props
}: React.ComponentProps<'tr'> & { href: string }) {
    return (
        <TableRow
            {...props}
            role="link"
            tabIndex={0}
            className={cn(
                'cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                className,
            )}
            onClick={(event) => {
                onClick?.(event);

                if (
                    event.defaultPrevented ||
                    (event.target instanceof Element &&
                        event.target.closest(
                            'a, button, input, select, textarea, [role="button"]',
                        ))
                ) {
                    return;
                }

                router.visit(href);
            }}
            onKeyDown={(event) => {
                onKeyDown?.(event);

                if (
                    !event.defaultPrevented &&
                    event.target === event.currentTarget &&
                    event.key === 'Enter'
                ) {
                    event.preventDefault();
                    router.visit(href);
                }
            }}
        />
    );
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return (
        <th
            data-slot="table-head"
            className={cn(
                'h-10 px-4 py-3 text-left align-middle font-medium whitespace-nowrap text-foreground [&:has([role=checkbox])]:px-4 [&>[role=checkbox]]:translate-y-[2px]',
                className,
            )}
            {...props}
        />
    );
}

function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return (
        <td
            data-slot="table-cell"
            className={cn(
                'px-4 py-3 align-middle whitespace-nowrap [&:has([role=checkbox])]:px-4 [&>[role=checkbox]]:translate-y-[2px]',
                className,
            )}
            {...props}
        />
    );
}

function TableCaption({
    className,
    ...props
}: React.ComponentProps<'caption'>) {
    return (
        <caption
            data-slot="table-caption"
            className={cn('mt-4 text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
    TableRowLink,
};
