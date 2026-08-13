import { Head, Link, usePoll } from '@inertiajs/react';
import {
    Activity,
    Boxes,
    CircleAlert,
    PackageCheck,
    PackageMinus,
    PackagePlus,
    Users,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as assetsIndex } from '@/routes/inventory/assets';
import { index as itemsIndex } from '@/routes/inventory/items';

type Metrics = {
    item_types: number;
    stock_on_hand: number;
    low_stock: number;
    assets: number;
    asset_cost: number;
    borrowed_assets: number;
    unassigned_assets: number;
    overdue_borrowings: number;
    employee_records: number;
    active_users: number;
    stock_in_today: number;
    stock_out_today: number;
    activity_today: number;
};

type DailyMovement = {
    date: string;
    label: string;
    stock_in: number;
    stock_out: number;
};

type StockOut = {
    inventory_item_stock_out_id: number;
    quantity: number;
    recipient_name: string | null;
    stocked_out_at: string;
    item: { name: string; unit_of_measure: string };
    recipient_reference: { name: string; type: string } | null;
};

const currency = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

export default function InventoryDashboard({
    metrics,
    dailyMovements,
    recentStockOuts,
    lastEmployeeSync,
}: {
    metrics: Metrics;
    dailyMovements: DailyMovement[];
    recentStockOuts: StockOut[];
    lastEmployeeSync: string | null;
}) {
    usePoll(60_000, {
        only: ['metrics', 'dailyMovements', 'recentStockOuts'],
    });

    const dailyCards = [
        {
            label: 'Received today',
            value: metrics.stock_in_today,
            detail: 'Consumable units added',
            icon: PackagePlus,
            tone: 'text-emerald-600',
        },
        {
            label: 'Released today',
            value: metrics.stock_out_today,
            detail: 'Consumable units issued',
            icon: PackageMinus,
            tone: 'text-blue-600',
        },
        {
            label: 'Overdue borrowings',
            value: metrics.overdue_borrowings,
            detail: 'Assets past their due date',
            icon: CircleAlert,
            tone: 'text-amber-600',
        },
        {
            label: 'Activity today',
            value: metrics.activity_today,
            detail: 'Recorded account and inventory events',
            icon: Activity,
            tone: 'text-violet-600',
        },
    ];
    const highestMovement = Math.max(
        1,
        ...dailyMovements.flatMap((day) => [day.stock_in, day.stock_out]),
    );

    return (
        <>
            <Head title="IMS Dashboard" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Inventory Management System
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Operational overview
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Live inventory movement, accountability, and access
                            indicators.
                        </p>
                    </div>
                    <div className="text-sm text-muted-foreground md:text-right">
                        <div>{metrics.employee_records} employee records</div>
                        <div className="text-xs">
                            {lastEmployeeSync
                                ? 'Employee data updated ' +
                                  new Date(lastEmployeeSync).toLocaleString()
                                : 'Employee data has not been synchronized'}
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {dailyCards.map(
                        ({ label, value, detail, icon: Icon, tone }) => (
                            <Card key={label}>
                                <CardHeader className="flex-row items-center justify-between gap-3 pb-2">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        {label}
                                    </CardTitle>
                                    <Icon className={'size-5 ' + tone} />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-3xl font-semibold">
                                        {value.toLocaleString()}
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {detail}
                                    </p>
                                </CardContent>
                            </Card>
                        ),
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>7-day stock movement</CardTitle>
                            <CardDescription>
                                Units received and released each day. This view
                                refreshes every minute.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2 rounded-full bg-emerald-500" />
                                    Received
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2 rounded-full bg-blue-500" />
                                    Released
                                </span>
                            </div>
                            <div className="grid h-56 grid-cols-7 items-end gap-2">
                                {dailyMovements.map((day) => (
                                    <div
                                        key={day.date}
                                        className="flex h-full flex-col justify-end gap-2"
                                    >
                                        <div className="flex flex-1 items-end justify-center gap-1">
                                            <div
                                                className="min-h-1 w-3 rounded-t bg-emerald-500/80 sm:w-5"
                                                style={{
                                                    height:
                                                        (day.stock_in /
                                                            highestMovement) *
                                                            100 +
                                                        '%',
                                                }}
                                                title={
                                                    day.stock_in +
                                                    ' units received'
                                                }
                                            />
                                            <div
                                                className="min-h-1 w-3 rounded-t bg-blue-500/80 sm:w-5"
                                                style={{
                                                    height:
                                                        (day.stock_out /
                                                            highestMovement) *
                                                            100 +
                                                        '%',
                                                }}
                                                title={
                                                    day.stock_out +
                                                    ' units released'
                                                }
                                            />
                                        </div>
                                        <div className="text-center">
                                            <div className="text-xs font-medium">
                                                {day.label}
                                            </div>
                                            <div className="hidden text-[10px] text-muted-foreground sm:block">
                                                {new Date(
                                                    day.date + 'T00:00:00',
                                                ).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        month: 'short',
                                                        day: 'numeric',
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Inventory health</CardTitle>
                            <CardDescription>
                                Current figures requiring regular attention.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div className="flex items-center gap-3">
                                    <Boxes className="size-5 text-primary" />
                                    <div>
                                        <div className="font-medium">
                                            Consumables
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {metrics.item_types} item types
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="font-semibold">
                                        {metrics.stock_on_hand.toLocaleString()}
                                    </div>
                                    <Badge variant="outline">
                                        {metrics.low_stock} low stock
                                    </Badge>
                                </div>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div className="flex items-center gap-3">
                                    <PackageCheck className="size-5 text-primary" />
                                    <div>
                                        <div className="font-medium">
                                            Assets
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {currency.format(
                                                metrics.asset_cost,
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="text-right text-xs text-muted-foreground">
                                    <div>{metrics.assets} recorded</div>
                                    <div>
                                        {metrics.unassigned_assets} unassigned
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div className="flex items-center gap-3">
                                    <Users className="size-5 text-primary" />
                                    <div>
                                        <div className="font-medium">
                                            System access
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Locally managed accounts
                                        </div>
                                    </div>
                                </div>
                                <div className="font-semibold">
                                    {metrics.active_users} active
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent releases</CardTitle>
                            <CardDescription>
                                Latest consumable inventory issued to employees.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto px-0">
                            <table className="w-full min-w-[760px] text-sm [&_tbody_tr]:transition-colors [&_tbody_tr]:hover:bg-muted/35 [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3">
                                <thead className="border-b bg-muted/50 text-left text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="pb-3">Item</th>
                                        <th className="pb-3">Recipient</th>
                                        <th className="pb-3">Date</th>
                                        <th className="pb-3 text-right">
                                            Quantity
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentStockOuts.map((release) => (
                                        <tr
                                            key={
                                                release.inventory_item_stock_out_id
                                            }
                                        >
                                            <td className="py-3 font-medium">
                                                {release.item.name}
                                            </td>
                                            <td className="py-3">
                                                {release.recipient_reference
                                                    ?.name ??
                                                    release.recipient_name ??
                                                    'Unspecified'}
                                            </td>
                                            <td className="py-3 text-muted-foreground">
                                                {new Date(
                                                    release.stocked_out_at +
                                                        'T00:00:00',
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="py-3 text-right">
                                                {release.quantity}{' '}
                                                {release.item.unit_of_measure}
                                            </td>
                                        </tr>
                                    ))}
                                    {recentStockOuts.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No stock releases recorded.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Quick access</CardTitle>
                            <CardDescription>
                                Open the main IMS work areas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <Link
                                href={itemsIndex()}
                                className="rounded-lg border p-4 transition-colors hover:bg-muted"
                            >
                                <div className="font-medium">
                                    Consumable inventory
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Receive, issue, and audit stock.
                                </p>
                            </Link>
                            <Link
                                href={assetsIndex()}
                                className="rounded-lg border p-4 transition-colors hover:bg-muted"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        Property and equipment
                                    </span>
                                    <Badge variant="secondary">
                                        {metrics.borrowed_assets} borrowed
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Custody and borrowing records.
                                </p>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

InventoryDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
