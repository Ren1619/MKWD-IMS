import { Form, Head, router } from '@inertiajs/react';
import {
    Archive as ArchiveIcon,
    ArchiveRestore,
    Boxes,
    ChevronRight,
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
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type CategoryType = 'major' | 'class' | 'series' | 'asset';
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

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

const typeLabels: Record<CategoryType, string> = {
    major: 'major category',
    class: 'class category',
    series: 'series category',
    asset: 'asset category',
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
        <div className="flex shrink-0 items-center gap-1">
            {onAddChild && category.is_active && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Add a child to ${category.name}`}
                    title="Add child category"
                    onClick={onAddChild}
                >
                    <Plus className="size-4" />
                </Button>
            )}
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={`Edit ${category.name}`}
                title="Edit category"
                onClick={onEdit}
            >
                <Pencil className="size-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={`${category.is_active ? 'Archive' : 'Activate'} ${category.name}`}
                title={
                    category.is_active
                        ? 'Archive category'
                        : 'Activate category'
                }
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
                {category.is_active ? (
                    <ArchiveIcon className="size-4" />
                ) : (
                    <ArchiveRestore className="size-4" />
                )}
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={dependencyCount > 0}
                aria-label={`Delete ${category.name}`}
                title={
                    dependencyCount > 0
                        ? 'Archive this category because it is still in use.'
                        : 'Permanently delete category'
                }
                onClick={() =>
                    onConfirm({
                        action: 'delete',
                        type,
                        id,
                        name: category.name,
                    })
                }
            >
                <Trash2 className="size-4 text-destructive" />
            </Button>
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
            assetCategories.filter(
                (category) =>
                    matchesStatus(category, assetStatusFilter) &&
                    matchesSearch(category, assetSearch),
            ),
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
                                workspace === 'items' ? 'secondary' : 'ghost'
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
                                workspace === 'assets' ? 'secondary' : 'ghost'
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
                        <CardContent className="space-y-3">
                            {visibleMajors.map((major) => {
                                const classes = filteredClassCategories(major);
                                const totalSeries = (
                                    major.class_categories ?? []
                                ).reduce(
                                    (total, category) =>
                                        total +
                                        (category.series_categories?.length ??
                                            0),
                                    0,
                                );
                                const totalItems = (
                                    major.class_categories ?? []
                                ).reduce(
                                    (majorTotal, category) =>
                                        majorTotal +
                                        (
                                            category.series_categories ?? []
                                        ).reduce(
                                            (classTotal, series) =>
                                                classTotal +
                                                (series.items_count ?? 0),
                                            0,
                                        ),
                                    0,
                                );

                                return (
                                    <Collapsible
                                        key={major.inv_mjr_cat_id}
                                        defaultOpen
                                        className="overflow-hidden rounded-xl border"
                                    >
                                        <div className="flex items-center gap-2 bg-muted/35 p-3">
                                            <CollapsibleTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="group size-8"
                                                        aria-label={`Toggle ${major.name}`}
                                                    />
                                                }
                                            >
                                                <ChevronRight className="size-4 transition-transform group-data-panel-open:rotate-90" />
                                            </CollapsibleTrigger>
                                            <Layers3 className="size-5 shrink-0 text-primary" />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {major.code}
                                                    </span>
                                                    <span className="font-semibold">
                                                        {major.name}
                                                    </span>
                                                    <CategoryStatus
                                                        active={major.is_active}
                                                    />
                                                </div>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {major.description ||
                                                        'No description'}{' '}
                                                    ·{' '}
                                                    {major.class_categories_count ??
                                                        0}{' '}
                                                    classes · {totalSeries}{' '}
                                                    series · {totalItems} items
                                                </p>
                                            </div>
                                            <CategoryActions
                                                type="major"
                                                id={major.inv_mjr_cat_id}
                                                category={major}
                                                dependencyCount={
                                                    major.class_categories_count ??
                                                    0
                                                }
                                                onAddChild={() =>
                                                    openCreate(
                                                        'class',
                                                        major.inv_mjr_cat_id,
                                                    )
                                                }
                                                onEdit={() =>
                                                    openEdit(
                                                        'major',
                                                        major.inv_mjr_cat_id,
                                                        major,
                                                    )
                                                }
                                                onConfirm={setConfirmation}
                                            />
                                        </div>
                                        <CollapsibleContent>
                                            <div className="space-y-2 border-t p-3 pl-8 md:pl-12">
                                                {classes.map(
                                                    (classCategory) => {
                                                        const seriesCategories =
                                                            filteredSeriesCategories(
                                                                classCategory,
                                                            );
                                                        const itemCount = (
                                                            classCategory.series_categories ??
                                                            []
                                                        ).reduce(
                                                            (total, series) =>
                                                                total +
                                                                (series.items_count ??
                                                                    0),
                                                            0,
                                                        );

                                                        return (
                                                            <Collapsible
                                                                key={
                                                                    classCategory.inv_class_cat_id
                                                                }
                                                                defaultOpen
                                                                className="rounded-lg border bg-background"
                                                            >
                                                                <div className="flex items-center gap-2 p-2.5">
                                                                    <CollapsibleTrigger
                                                                        render={
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="group size-7"
                                                                                aria-label={`Toggle ${classCategory.name}`}
                                                                            />
                                                                        }
                                                                    >
                                                                        <ChevronRight className="size-3.5 transition-transform group-data-panel-open:rotate-90" />
                                                                    </CollapsibleTrigger>
                                                                    <GitBranch className="size-4 shrink-0 text-sky-600" />
                                                                    <div className="min-w-0 flex-1">
                                                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                                {
                                                                                    classCategory.code
                                                                                }
                                                                            </span>
                                                                            <span className="font-medium">
                                                                                {
                                                                                    classCategory.name
                                                                                }
                                                                            </span>
                                                                            <CategoryStatus
                                                                                active={
                                                                                    classCategory.is_active
                                                                                }
                                                                            />
                                                                        </div>
                                                                        <p className="truncate text-xs text-muted-foreground">
                                                                            {classCategory.description ||
                                                                                'No description'}{' '}
                                                                            ·{' '}
                                                                            {classCategory.series_categories_count ??
                                                                                0}{' '}
                                                                            series
                                                                            ·{' '}
                                                                            {
                                                                                itemCount
                                                                            }{' '}
                                                                            items
                                                                        </p>
                                                                    </div>
                                                                    <CategoryActions
                                                                        type="class"
                                                                        id={
                                                                            classCategory.inv_class_cat_id
                                                                        }
                                                                        category={
                                                                            classCategory
                                                                        }
                                                                        dependencyCount={
                                                                            classCategory.series_categories_count ??
                                                                            0
                                                                        }
                                                                        onAddChild={() =>
                                                                            openCreate(
                                                                                'series',
                                                                                classCategory.inv_class_cat_id,
                                                                            )
                                                                        }
                                                                        onEdit={() =>
                                                                            openEdit(
                                                                                'class',
                                                                                classCategory.inv_class_cat_id,
                                                                                classCategory,
                                                                                major.inv_mjr_cat_id,
                                                                            )
                                                                        }
                                                                        onConfirm={
                                                                            setConfirmation
                                                                        }
                                                                    />
                                                                </div>
                                                                <CollapsibleContent>
                                                                    <div className="divide-y border-t pl-8">
                                                                        {seriesCategories.map(
                                                                            (
                                                                                series,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        series.inv_series_cat_id
                                                                                    }
                                                                                    className="flex items-center gap-2 p-2.5"
                                                                                >
                                                                                    <div className="size-2 rounded-full bg-primary/50" />
                                                                                    <div className="min-w-0 flex-1">
                                                                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                                                                            <span className="font-mono text-xs text-muted-foreground">
                                                                                                {
                                                                                                    series.code
                                                                                                }
                                                                                            </span>
                                                                                            <span>
                                                                                                {
                                                                                                    series.name
                                                                                                }
                                                                                            </span>
                                                                                            <CategoryStatus
                                                                                                active={
                                                                                                    series.is_active
                                                                                                }
                                                                                            />
                                                                                        </div>
                                                                                        <p className="truncate text-xs text-muted-foreground">
                                                                                            {series.description ||
                                                                                                'No description'}{' '}
                                                                                            ·{' '}
                                                                                            {series.items_count ??
                                                                                                0}{' '}
                                                                                            items
                                                                                        </p>
                                                                                    </div>
                                                                                    <CategoryActions
                                                                                        type="series"
                                                                                        id={
                                                                                            series.inv_series_cat_id
                                                                                        }
                                                                                        category={
                                                                                            series
                                                                                        }
                                                                                        dependencyCount={
                                                                                            series.items_count ??
                                                                                            0
                                                                                        }
                                                                                        onEdit={() =>
                                                                                            openEdit(
                                                                                                'series',
                                                                                                series.inv_series_cat_id,
                                                                                                series,
                                                                                                classCategory.inv_class_cat_id,
                                                                                            )
                                                                                        }
                                                                                        onConfirm={
                                                                                            setConfirmation
                                                                                        }
                                                                                    />
                                                                                </div>
                                                                            ),
                                                                        )}
                                                                        {seriesCategories.length ===
                                                                            0 && (
                                                                            <p className="p-4 text-sm text-muted-foreground">
                                                                                No
                                                                                matching
                                                                                series.
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                </CollapsibleContent>
                                                            </Collapsible>
                                                        );
                                                    },
                                                )}
                                                {classes.length === 0 && (
                                                    <div className="rounded-lg border border-dashed p-5 text-center text-sm text-muted-foreground">
                                                        No matching class
                                                        categories.
                                                    </div>
                                                )}
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                );
                            })}
                            {visibleMajors.length === 0 && (
                                <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                                    No item categories match the current
                                    filters.
                                </div>
                            )}
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
                        <CardContent className="grid gap-3 lg:grid-cols-2">
                            {visibleAssets.map((category) => (
                                <div
                                    key={category.inv_asset_cat_id}
                                    className="flex items-center gap-3 rounded-xl border p-4"
                                >
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Boxes className="size-5" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {category.code}
                                            </span>
                                            <span className="font-medium">
                                                {category.name}
                                            </span>
                                            <CategoryStatus
                                                active={category.is_active}
                                            />
                                        </div>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {category.description ||
                                                'No description'}{' '}
                                            · {category.assets_count ?? 0}{' '}
                                            assets
                                        </p>
                                    </div>
                                    <CategoryActions
                                        type="asset"
                                        id={category.inv_asset_cat_id}
                                        category={category}
                                        dependencyCount={
                                            category.assets_count ?? 0
                                        }
                                        onEdit={() =>
                                            openEdit(
                                                'asset',
                                                category.inv_asset_cat_id,
                                                category,
                                            )
                                        }
                                        onConfirm={setConfirmation}
                                    />
                                </div>
                            ))}
                            {visibleAssets.length === 0 && (
                                <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground lg:col-span-2">
                                    No asset categories match the current
                                    filters.
                                </div>
                            )}
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
                                        editor.type === 'series') && (
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
