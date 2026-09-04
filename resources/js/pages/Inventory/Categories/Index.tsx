import { Form, Head, router } from '@inertiajs/react';
import {
    Archive as ArchiveIcon,
    ArchiveRestore,
    Boxes,
    ChevronRight,
    EllipsisVertical,
    FolderTree,
    GitBranch,
    Layers3,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/shared/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useAppPage } from '@/hooks/use-app-page';
import {
    destroy,
    index,
    status,
    store,
    update,
} from '@/routes/inventory/categories';
import type {
    AssetCategory,
    ClassCategory,
    MajorCategory,
} from '@/types/inventory';

type CategoryType =
    'major' | 'class' | 'series' | 'asset' | 'asset_subcategory';
type Workspace = 'items' | 'assets';
type StatusFilter = 'all' | 'active' | 'archived';

type EditorState = {
    mode: 'create' | 'edit';
    type: CategoryType;
    id?: number;
    parentId?: number;
    code?: string;
    name?: string;
    description?: string | null;
};

type ConfirmationState = {
    action: 'status' | 'delete';
    type: CategoryType;
    id: number;
    name: string;
    nextActive?: boolean;
};

type CommonCategory = {
    code: string;
    name: string;
    description?: string | null;
    is_active: boolean;
};

type CategoryTableRowData = {
    key: string;
    type: CategoryType;
    id: number;
    parentId?: number;
    childType?: CategoryType;
    parentKeys: string[];
    isExpandable: boolean;
    category: CommonCategory;
    level: string;
    depth: number;
    usage: string;
    dependencyCount: number;
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

const typeLabels: Record<CategoryType, string> = {
    major: 'major category',
    class: 'class category',
    series: 'series category',
    asset: 'asset category',
    asset_subcategory: 'asset subcategory',
};

function matchesStatus(category: CommonCategory, filter: StatusFilter) {
    return (
        filter === 'all' ||
        (filter === 'active' && category.is_active) ||
        (filter === 'archived' && !category.is_active)
    );
}

function matchesSearch(category: CommonCategory, search: string) {
    const query = search.trim().toLocaleLowerCase();

    return (
        query === '' ||
        [category.code, category.name, category.description]
            .filter(Boolean)
            .some((value) => value?.toLocaleLowerCase().includes(query))
    );
}

function CategoryStatus({ active }: { active: boolean }) {
    return active ? (
        <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300">
            Active
        </Badge>
    ) : (
        <Badge variant="secondary">Archived</Badge>
    );
}

function CategoryActions({
    type,
    id,
    category,
    dependencyCount,
    onAddChild,
    onEdit,
    onConfirm,
}: {
    type: CategoryType;
    id: number;
    category: CommonCategory;
    dependencyCount: number;
    onAddChild?: () => void;
    onEdit: () => void;
    onConfirm: (confirmation: ConfirmationState) => void;
}) {
    const { auth } = useAppPage().props;

    if (!auth.permissions.manage_inventory) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={`Open actions for ${category.name}`}
                    />
                }
            >
                <EllipsisVertical className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {onAddChild && category.is_active && (
                    <DropdownMenuItem onClick={onAddChild}>
                        <Plus /> Add child category
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onClick={onEdit}>
                    <Pencil /> Edit
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() =>
                        onConfirm({
                            action: 'status',
                            type,
                            id,
                            name: category.name,
                            nextActive: !category.is_active,
                        })
                    }
                >
                    {category.is_active ? <ArchiveIcon /> : <ArchiveRestore />}
                    {category.is_active ? 'Archive' : 'Activate'}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    disabled={dependencyCount > 0}
                    onClick={() =>
                        onConfirm({
                            action: 'delete',
                            type,
                            id,
                            name: category.name,
                        })
                    }
                >
                    <Trash2 />
                    {dependencyCount > 0
                        ? 'In use — archive instead'
                        : 'Delete permanently'}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function CategoryDataTable({
    rows,
    emptyMessage,
    canManageInventory,
    onCreateChild,
    onEdit,
    onConfirm,
}: {
    rows: CategoryTableRowData[];
    emptyMessage: string;
    canManageInventory: boolean;
    onCreateChild: (type: CategoryType, parentId: number) => void;
    onEdit: (
        type: CategoryType,
        id: number,
        category: CommonCategory,
        parentId?: number,
    ) => void;
    onConfirm: (confirmation: ConfirmationState) => void;
}) {
    const indentationClasses = ['', 'pl-7', 'pl-14'];
    const [expandedRows, setExpandedRows] = useState<Set<string>>(
        () =>
            new Set(
                rows.filter((row) => row.isExpandable).map((row) => row.key),
            ),
    );
    const visibleRows = rows.filter((row) =>
        row.parentKeys.every((parentKey) => expandedRows.has(parentKey)),
    );

    const toggleRow = (key: string): void => {
        setExpandedRows((currentRows) => {
            const nextRows = new Set(currentRows);

            if (nextRows.has(key)) {
                nextRows.delete(key);
            } else {
                nextRows.add(key);
            }

            return nextRows;
        });
    };

    return (
        <div className="overflow-x-auto">
            <Table className="min-w-[760px]">
                <TableHeader className="bg-muted/50">
                    <TableRow>
                        <TableHead>Classification</TableHead>
                        <TableHead>Level</TableHead>
                        <TableHead>Usage</TableHead>
                        <TableHead>Status</TableHead>
                        {canManageInventory && (
                            <TableHead className="w-16 text-right">
                                Actions
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {visibleRows.map((row) => (
                        <TableRow
                            key={row.key}
                            className={row.depth === 0 ? 'bg-muted/20' : ''}
                        >
                            <TableCell className="whitespace-normal">
                                <div
                                    className={`flex items-start gap-3 ${indentationClasses[row.depth] ?? ''}`}
                                >
                                    {row.isExpandable ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="-mt-1 size-7 shrink-0"
                                            aria-label={`${expandedRows.has(row.key) ? 'Collapse' : 'Expand'} ${row.category.name}`}
                                            aria-expanded={expandedRows.has(
                                                row.key,
                                            )}
                                            onClick={() => toggleRow(row.key)}
                                        >
                                            <ChevronRight
                                                className={`size-4 transition-transform ${expandedRows.has(row.key) ? 'rotate-90' : ''}`}
                                            />
                                        </Button>
                                    ) : (
                                        <div className="w-7 shrink-0" />
                                    )}
                                    {row.depth === 0 ? (
                                        <Layers3 className="mt-0.5 size-4 shrink-0 text-primary" />
                                    ) : row.depth === 1 ? (
                                        <GitBranch className="mt-0.5 size-4 shrink-0 text-sky-600" />
                                    ) : (
                                        <div className="mt-1.5 size-2 shrink-0 rounded-full bg-primary/50" />
                                    )}
                                    <div className="min-w-0">
                                        <div
                                            className={`flex flex-wrap items-center gap-2 ${row.depth < 2 ? 'font-medium' : ''}`}
                                        >
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {row.category.code}
                                            </span>
                                            <span>{row.category.name}</span>
                                        </div>
                                        <p className="max-w-xl truncate text-xs text-muted-foreground">
                                            {row.category.description ||
                                                'No description'}
                                        </p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell>{row.level}</TableCell>
                            <TableCell>{row.usage}</TableCell>
                            <TableCell>
                                <CategoryStatus
                                    active={row.category.is_active}
                                />
                            </TableCell>
                            {canManageInventory && (
                                <TableCell className="text-right">
                                    <CategoryActions
                                        type={row.type}
                                        id={row.id}
                                        category={row.category}
                                        dependencyCount={row.dependencyCount}
                                        onAddChild={
                                            row.childType
                                                ? () =>
                                                      onCreateChild(
                                                          row.childType!,
                                                          row.id,
                                                      )
                                                : undefined
                                        }
                                        onEdit={() =>
                                            onEdit(
                                                row.type,
                                                row.id,
                                                row.category,
                                                row.parentId,
                                            )
                                        }
                                        onConfirm={onConfirm}
                                    />
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                    {rows.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={canManageInventory ? 5 : 4}
                                className="h-32 text-center text-muted-foreground"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

export default function CategoriesIndex({
    majorCategories,
    assetCategories,
}: {
    majorCategories: MajorCategory[];
    assetCategories: AssetCategory[];
}) {
    const { auth } = useAppPage().props;
    const canManageInventory = auth.permissions.manage_inventory;
    const [workspace, setWorkspace] = useState<Workspace>('items');
    const [itemSearch, setItemSearch] = useState('');
    const [assetSearch, setAssetSearch] = useState('');
    const [itemStatusFilter, setItemStatusFilter] =
        useState<StatusFilter>('all');
    const [assetStatusFilter, setAssetStatusFilter] =
        useState<StatusFilter>('all');
    const [editor, setEditor] = useState<EditorState | null>(null);
    const [confirmation, setConfirmation] = useState<ConfirmationState | null>(
        null,
    );

    const classCategories = useMemo(
        () => majorCategories.flatMap((major) => major.class_categories ?? []),
        [majorCategories],
    );

    const visibleMajors = useMemo(
        () =>
            majorCategories.filter((major) => {
                const descendants = (major.class_categories ?? []).some(
                    (classCategory) =>
                        matchesSearch(classCategory, itemSearch) ||
                        (classCategory.series_categories ?? []).some((series) =>
                            matchesSearch(series, itemSearch),
                        ),
                );

                return (
                    matchesStatus(major, itemStatusFilter) &&
                    (matchesSearch(major, itemSearch) || descendants)
                );
            }),
        [itemSearch, itemStatusFilter, majorCategories],
    );

    const visibleAssets = useMemo(
        () =>
            assetCategories.filter((category) => {
                const matchingSubcategory = (category.subcategories ?? []).some(
                    (subcategory) => matchesSearch(subcategory, assetSearch),
                );

                return (
                    matchesStatus(category, assetStatusFilter) &&
                    (matchesSearch(category, assetSearch) ||
                        matchingSubcategory)
                );
            }),
        [assetCategories, assetSearch, assetStatusFilter],
    );

    const parentOptions =
        editor?.type === 'class'
            ? majorCategories
                  .filter(
                      (major) =>
                          major.is_active ||
                          major.inv_mjr_cat_id === editor.parentId,
                  )
                  .map((major) => ({
                      id: major.inv_mjr_cat_id,
                      label: `${major.code} · ${major.name}`,
                  }))
            : editor?.type === 'asset_subcategory'
              ? assetCategories
                    .filter(
                        (category) =>
                            category.is_active ||
                            category.inv_asset_cat_id === editor.parentId,
                    )
                    .map((category) => ({
                        id: category.inv_asset_cat_id,
                        label: `${category.code} · ${category.name}`,
                    }))
              : classCategories
                    .filter(
                        (category) =>
                            category.is_active ||
                            category.inv_class_cat_id === editor?.parentId,
                    )
                    .map((category) => ({
                        id: category.inv_class_cat_id,
                        label: `${category.code} · ${category.name}`,
                    }));

    const openCreate = (type: CategoryType, parentId?: number) =>
        setEditor({ mode: 'create', type, parentId });

    const openEdit = (
        type: CategoryType,
        id: number,
        category: CommonCategory,
        parentId?: number,
    ) =>
        setEditor({
            mode: 'edit',
            type,
            id,
            parentId,
            code: category.code,
            name: category.name,
            description: category.description,
        });

    const runConfirmation = () => {
        if (!confirmation) {
            return;
        }

        const destination =
            confirmation.action === 'delete'
                ? destroy({
                      type: confirmation.type,
                      category: confirmation.id,
                  })
                : status({
                      type: confirmation.type,
                      category: confirmation.id,
                  });

        router.visit(destination, {
            data:
                confirmation.action === 'status'
                    ? { is_active: confirmation.nextActive }
                    : undefined,
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
        });
    };

    const filteredClassCategories = (major: MajorCategory) =>
        (major.class_categories ?? []).filter(
            (classCategory) =>
                matchesStatus(classCategory, itemStatusFilter) &&
                (matchesSearch(classCategory, itemSearch) ||
                    (classCategory.series_categories ?? []).some((series) =>
                        matchesSearch(series, itemSearch),
                    )),
        );

    const filteredSeriesCategories = (classCategory: ClassCategory) =>
        (classCategory.series_categories ?? []).filter(
            (series) =>
                matchesStatus(series, itemStatusFilter) &&
                matchesSearch(series, itemSearch),
        );

    const itemCategoryRows: CategoryTableRowData[] = visibleMajors.flatMap(
        (major) => {
            const classes = filteredClassCategories(major);
            const totalSeries = (major.class_categories ?? []).reduce(
                (total, category) =>
                    total + (category.series_categories?.length ?? 0),
                0,
            );
            const totalItems = (major.class_categories ?? []).reduce(
                (majorTotal, category) =>
                    majorTotal +
                    (category.series_categories ?? []).reduce(
                        (classTotal, series) =>
                            classTotal + (series.items_count ?? 0),
                        0,
                    ),
                0,
            );
            const rows: CategoryTableRowData[] = [
                {
                    key: `major-${major.inv_mjr_cat_id}`,
                    type: 'major',
                    id: major.inv_mjr_cat_id,
                    childType: 'class',
                    category: major,
                    level: 'Major category',
                    depth: 0,
                    parentKeys: [],
                    isExpandable: classes.length > 0,
                    usage: `${major.class_categories_count ?? 0} classes · ${totalSeries} series · ${totalItems} items`,
                    dependencyCount: major.class_categories_count ?? 0,
                },
            ];

            classes.forEach((classCategory) => {
                const itemCount = (
                    classCategory.series_categories ?? []
                ).reduce(
                    (total, series) => total + (series.items_count ?? 0),
                    0,
                );
                rows.push({
                    key: `class-${classCategory.inv_class_cat_id}`,
                    type: 'class',
                    id: classCategory.inv_class_cat_id,
                    parentId: major.inv_mjr_cat_id,
                    childType: 'series',
                    category: classCategory,
                    level: 'Class category',
                    depth: 1,
                    parentKeys: [`major-${major.inv_mjr_cat_id}`],
                    isExpandable:
                        filteredSeriesCategories(classCategory).length > 0,
                    usage: `${classCategory.series_categories_count ?? 0} series · ${itemCount} items`,
                    dependencyCount: classCategory.series_categories_count ?? 0,
                });

                filteredSeriesCategories(classCategory).forEach((series) => {
                    rows.push({
                        key: `series-${series.inv_series_cat_id}`,
                        type: 'series',
                        id: series.inv_series_cat_id,
                        parentId: classCategory.inv_class_cat_id,
                        category: series,
                        level: 'Series category',
                        depth: 2,
                        parentKeys: [
                            `major-${major.inv_mjr_cat_id}`,
                            `class-${classCategory.inv_class_cat_id}`,
                        ],
                        isExpandable: false,
                        usage: `${series.items_count ?? 0} items`,
                        dependencyCount: series.items_count ?? 0,
                    });
                });
            });

            return rows;
        },
    );

    const assetCategoryRows: CategoryTableRowData[] = visibleAssets.flatMap(
        (category) => [
            {
                key: `asset-${category.inv_asset_cat_id}`,
                type: 'asset',
                id: category.inv_asset_cat_id,
                childType: 'asset_subcategory',
                category,
                level: 'Asset category',
                depth: 0,
                parentKeys: [],
                isExpandable: (category.subcategories ?? []).length > 0,
                usage: `${category.subcategories_count ?? 0} subcategories · ${category.assets_count ?? 0} assets`,
                dependencyCount:
                    (category.assets_count ?? 0) +
                    (category.subcategories_count ?? 0),
            },
            ...(category.subcategories ?? []).map((subcategory) => ({
                key: `asset-subcategory-${subcategory.inventory_asset_subcategory_id}`,
                type: 'asset_subcategory' as const,
                id: subcategory.inventory_asset_subcategory_id,
                parentId: category.inv_asset_cat_id,
                category: subcategory,
                level: 'Asset subcategory',
                depth: 1,
                parentKeys: [`asset-${category.inv_asset_cat_id}`],
                isExpandable: false,
                usage: `${subcategory.assets_count ?? 0} assets`,
                dependencyCount: subcategory.assets_count ?? 0,
            })),
        ],
    );

    return (
        <>
            <Head title="Inventory Categories" />
            <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Inventory categories
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Maintain the item classification hierarchy and asset
                        groupings from one workspace.
                    </p>
                </div>

                <div className="w-fit rounded-xl border bg-card p-1">
                    <div className="flex rounded-lg bg-muted p-1">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                workspace === 'items' ? 'default' : 'ghost'
                            }
                            onClick={() => setWorkspace('items')}
                        >
                            <FolderTree className="size-4" />
                            Item hierarchy
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                workspace === 'assets' ? 'default' : 'ghost'
                            }
                            onClick={() => setWorkspace('assets')}
                        >
                            <Boxes className="size-4" />
                            Asset categories
                        </Button>
                    </div>
                </div>

                {workspace === 'items' ? (
                    <Card>
                        <CardHeader className="gap-4">
                            <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                                <div className="grid gap-1.5">
                                    <CardTitle className="flex items-center gap-2">
                                        <FolderTree className="size-5 text-primary" />
                                        Item classification tree
                                    </CardTitle>
                                    <CardDescription>
                                        Major categories contain classes;
                                        classes contain the series selected on
                                        inventory items.
                                    </CardDescription>
                                </div>
                                {canManageInventory && (
                                    <Button onClick={() => openCreate('major')}>
                                        <Plus className="size-4" />
                                        Add major category
                                    </Button>
                                )}
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <div className="grid w-full gap-1.5 sm:w-72">
                                    <label
                                        htmlFor="item-category-search"
                                        className="text-sm font-medium"
                                    >
                                        Search item categories
                                    </label>
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="item-category-search"
                                            value={itemSearch}
                                            onChange={(event) =>
                                                setItemSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="e.g. Office Supplies or ICT"
                                            maxLength={100}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>
                                <div className="grid w-full gap-1.5 sm:w-36">
                                    <label
                                        htmlFor="item-category-status"
                                        className="text-sm font-medium"
                                    >
                                        Status
                                    </label>
                                    <select
                                        id="item-category-status"
                                        value={itemStatusFilter}
                                        onChange={(event) =>
                                            setItemStatusFilter(
                                                event.target
                                                    .value as StatusFilter,
                                            )
                                        }
                                        className={selectClass}
                                    >
                                        <option value="all">
                                            All statuses
                                        </option>
                                        <option value="active">Active</option>
                                        <option value="archived">
                                            Archived
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <CategoryDataTable
                                rows={itemCategoryRows}
                                emptyMessage="No item categories match the current filters."
                                canManageInventory={canManageInventory}
                                onCreateChild={openCreate}
                                onEdit={openEdit}
                                onConfirm={setConfirmation}
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader className="gap-4">
                            <div className="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                                <div className="grid gap-1.5">
                                    <CardTitle className="flex items-center gap-2">
                                        <Boxes className="size-5 text-primary" />
                                        Asset categories
                                    </CardTitle>
                                    <CardDescription>
                                        Classify property and equipment while
                                        retaining categories used by historical
                                        asset records.
                                    </CardDescription>
                                </div>
                                {canManageInventory && (
                                    <Button onClick={() => openCreate('asset')}>
                                        <Plus className="size-4" />
                                        Add asset category
                                    </Button>
                                )}
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <div className="grid w-full gap-1.5 sm:w-72">
                                    <label
                                        htmlFor="asset-category-search"
                                        className="text-sm font-medium"
                                    >
                                        Search asset categories
                                    </label>
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="asset-category-search"
                                            value={assetSearch}
                                            onChange={(event) =>
                                                setAssetSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="e.g. ICT Equipment or LAP"
                                            maxLength={100}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>
                                <div className="grid w-full gap-1.5 sm:w-36">
                                    <label
                                        htmlFor="asset-category-status"
                                        className="text-sm font-medium"
                                    >
                                        Status
                                    </label>
                                    <select
                                        id="asset-category-status"
                                        value={assetStatusFilter}
                                        onChange={(event) =>
                                            setAssetStatusFilter(
                                                event.target
                                                    .value as StatusFilter,
                                            )
                                        }
                                        className={selectClass}
                                    >
                                        <option value="all">
                                            All statuses
                                        </option>
                                        <option value="active">Active</option>
                                        <option value="archived">
                                            Archived
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <CategoryDataTable
                                rows={assetCategoryRows}
                                emptyMessage="No asset categories match the current filters."
                                canManageInventory={canManageInventory}
                                onCreateChild={openCreate}
                                onEdit={openEdit}
                                onConfirm={setConfirmation}
                            />
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog
                open={editor !== null}
                onOpenChange={(open) => !open && setEditor(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editor?.mode === 'edit' ? 'Edit' : 'Add'}{' '}
                            {editor ? typeLabels[editor.type] : 'category'}
                        </DialogTitle>
                        <DialogDescription>
                            Codes are kept short and unique. Descriptions help
                            staff distinguish similar classifications.
                        </DialogDescription>
                    </DialogHeader>
                    {editor && (
                        <Form
                            key={`${editor.mode}-${editor.type}-${editor.id ?? editor.parentId ?? 'new'}`}
                            action={
                                editor.mode === 'edit' && editor.id
                                    ? update({
                                          type: editor.type,
                                          category: editor.id,
                                      })
                                    : store()
                            }
                            resetOnSuccess
                            onSuccess={() => setEditor(null)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {editor.mode === 'create' && (
                                        <input
                                            type="hidden"
                                            name="type"
                                            value={editor.type}
                                        />
                                    )}
                                    {(editor.type === 'class' ||
                                        editor.type === 'series' ||
                                        editor.type ===
                                            'asset_subcategory') && (
                                        <div className="grid gap-1.5">
                                            <label
                                                htmlFor="category-parent"
                                                className="text-sm font-medium"
                                            >
                                                Parent category
                                            </label>
                                            <select
                                                id="category-parent"
                                                name="parent_id"
                                                className={selectClass}
                                                defaultValue={
                                                    editor.parentId ?? ''
                                                }
                                                required
                                            >
                                                <option value="" disabled>
                                                    Select parent
                                                </option>
                                                {parentOptions.map((option) => (
                                                    <option
                                                        key={option.id}
                                                        value={option.id}
                                                    >
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.parent_id}
                                            />
                                        </div>
                                    )}
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor="category-code"
                                            className="text-sm font-medium"
                                        >
                                            Code
                                        </label>
                                        <Input
                                            id="category-code"
                                            name="code"
                                            defaultValue={editor.code ?? ''}
                                            placeholder="e.g. ICT"
                                            maxLength={20}
                                            required
                                            autoFocus
                                        />
                                        <InputError message={errors.code} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor="category-name"
                                            className="text-sm font-medium"
                                        >
                                            Name
                                        </label>
                                        <Input
                                            id="category-name"
                                            name="name"
                                            defaultValue={editor.name ?? ''}
                                            placeholder="e.g. Information and Communication Technology"
                                            maxLength={255}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <label
                                            htmlFor="category-description"
                                            className="text-sm font-medium"
                                        >
                                            Description
                                        </label>
                                        <textarea
                                            id="category-description"
                                            name="description"
                                            defaultValue={
                                                editor.description ?? ''
                                            }
                                            rows={3}
                                            placeholder="e.g. Equipment used for data processing and communications"
                                            maxLength={2000}
                                            className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setEditor(null)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button disabled={processing}>
                                            {processing
                                                ? 'Saving…'
                                                : editor.mode === 'edit'
                                                  ? 'Save changes'
                                                  : 'Create category'}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={confirmation !== null}
                onOpenChange={(open) => !open && setConfirmation(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {confirmation?.action === 'delete'
                                ? 'Permanently delete category?'
                                : confirmation?.nextActive
                                  ? 'Activate category?'
                                  : 'Archive category?'}
                        </DialogTitle>
                        <DialogDescription>
                            {confirmation?.action === 'delete'
                                ? `${confirmation.name} will be permanently removed. This is only allowed when nothing depends on it.`
                                : confirmation?.nextActive
                                  ? `${confirmation.name} will become available for new inventory records. Its parent categories will also be activated when needed.`
                                  : `${confirmation?.name} will no longer be available for new inventory records. Existing records will keep their category.`}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmation(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant={
                                confirmation?.action === 'delete'
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={runConfirmation}
                        >
                            {confirmation?.action === 'delete'
                                ? 'Delete permanently'
                                : confirmation?.nextActive
                                  ? 'Activate'
                                  : 'Archive'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Inventory categories', href: index() }],
};
