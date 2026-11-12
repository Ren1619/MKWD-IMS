<?php

use Illuminate\Support\Facades\File;

test('stockroom labels retain the existing warehouse route and field contracts', function () {
    $registry = file_get_contents(resource_path('js/pages/Inventory/Warehouse/Registry.tsx'));
    $storage = file_get_contents(resource_path('js/pages/Inventory/Warehouse/Index.tsx'));
    $placement = file_get_contents(resource_path('js/pages/Inventory/Warehouse/Show.tsx'));

    expect($registry)->toContain('Stockroom Management', 'Add stockroom', 'Save stockroom', '@/routes/inventory/warehouse/registry')
        ->not->toContain('Warehouse Management', 'Add warehouse');
    expect($storage)->toContain('All stockrooms', 'name="warehouse_id"');
    expect($placement)->toContain('Manage stockrooms', 'Select a registered stockroom', 'warehouse_id');
    expect(file_get_contents(resource_path('js/components/app-sidebar.tsx')))->not->toContain("title: 'Stockroom Management'");
    expect($storage)->toContain('<ManageStockroomsDialog />', '<CardAction>');
    expect($registry)->toContain('Manage Stockrooms', 'disablePointerDismissal', "details.reason !== 'close-press'", 'details.cancel()', 'showCloseButton={false}', '<DialogClose', '</DialogClose>');
});

test('operational views share consistent page gutters and section spacing', function () {
    $container = file_get_contents(resource_path('js/components/page-container.tsx'));

    expect($container)->toContain('mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8');

    $pages = collect(File::allFiles(resource_path('js/pages/Inventory')))
        ->merge(File::allFiles(resource_path('js/pages/Admin')))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getContents(), '<Head '));

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        expect($page->getContents())->toContain('<PageContainer>')->not->toMatch('/className="[^"]*max-w-\[(1500|1700)px\][^"]*p-4/');
    }

    expect(file_get_contents(resource_path('js/layouts/settings/layout.tsx')))->toContain('<PageContainer');
    expect(file_get_contents(resource_path('js/components/shared/data-table.tsx')))->toContain('px-4 py-3 align-middle', 'h-10 px-4 py-3');
});

test('light theme uses white card surfaces including custom panels without changing dark mode', function () {
    $source = file_get_contents(resource_path('css/app.css'));

    expect($source)->toContain('--card: oklch(1 0 0);', ":root:not(.dark) [data-slot='card']", ":root:not(.dark) [data-slot='card-footer']", ':is(div, section, article, form):is(.rounded-lg, .rounded-xl).border:not(', 'background-color: var(--card);', 'background-image: none;', '--card: oklch(0.21 0.03 252);');
});

test('consumable inventory shares items and permission gated storage navigation', function () {
    $navigation = file_get_contents(resource_path('js/components/consumable-inventory-nav.tsx'));

    expect($navigation)->toContain('auth.permissions.manage_inventory', "title: 'Items'", "title: 'Storage & Batches'", 'itemsIndex()', 'storageIndex()', 'aria-current=');

    foreach (['Items/Index' => 'items', 'Warehouse/Index' => 'storage', 'Warehouse/Show' => 'storage'] as $page => $active) {
        expect(file_get_contents(resource_path("js/pages/Inventory/{$page}.tsx")))
            ->toContain('<ConsumableInventoryNav active="'.$active.'" />');
    }

    expect(file_get_contents(resource_path('js/components/nav-main.tsx')))->toContain('item.isActive ?? isCurrentUrl(item.href)');
});

test('warehouse storage exposes accessible filters alongside stockroom management', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Warehouse/Index.tsx'));

    expect($source)->not->toContain('Manage warehouses', 'registryIndex')
        ->toContain('aria-label="Storage filters"', 'aria-busy={filtering}', 'type="search"', 'htmlFor="include-depleted"', 'Stock balance', 'Clear filters', 'disabled={filtering}', 'key={JSON.stringify(filters)}');

    expect($source)->toContain('<Card>', 'sm:flex-wrap sm:items-center', 'sm:w-80', 'sm:w-44', 'warehouse.name', 'border-border/70', 'bg-muted/50', '<TableBody className="divide-y">', 'role="link"', 'onChange={submitAfterDelay}', 'onChange={submitImmediately}')
        ->not->toContain('border-t pt-4', 'sm:grid-cols-2');
});

test('the frontend mounts one application root and deduplicates flash toasts', function () {
    $appSource = file_get_contents(resource_path('js/app.tsx'));
    $flashToastSource = file_get_contents(resource_path('js/hooks/use-flash-toast.ts'));

    expect(substr_count($appSource, '<Toaster />'))->toBe(1)
        ->and($appSource)
        ->toContain('!window.__mkwdInertiaStarted')
        ->toContain('window.__mkwdInertiaStarted = true')
        ->and($flashToastSource)
        ->toContain('id: `inertia-flash:${data.type}:${data.message}`');
});

test('page dropdowns use the shared shadcn select while employee search remains a combobox', function () {
    $pageSource = collect(File::allFiles(resource_path('js/pages')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'tsx')
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");
    $employeeComboboxSource = file_get_contents(
        resource_path('js/components/hris-reference-combobox.tsx'),
    );
    $formSelectSource = file_get_contents(
        resource_path('js/components/ui/form-select.tsx'),
    );

    expect($pageSource)
        ->not->toContain('<select')
        ->toContain('<FormSelect')
        ->and($employeeComboboxSource)
        ->toContain('<Combobox.Root')
        ->and($formSelectSource)
        ->toContain('items={selectItems}')
        ->toContain("'label' in selectedValue")
        ->toContain('return selectedValue.label')
        ->toContain("return optionLabels.get(String(selectedValue ?? ''))")
        ->toContain('alignItemWithTrigger={false}');
});

test('all page date controls use the shared long format date picker', function () {
    $pageSource = collect(File::allFiles(resource_path('js/pages')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'tsx')
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");
    $datePickerSource = file_get_contents(
        resource_path('js/components/ui/date-picker.tsx'),
    );

    expect($pageSource)
        ->not->toMatch('/type=["\'](?:date|datetime-local)["\']/')
        ->toContain('<DatePicker')
        ->and($datePickerSource)
        ->toContain("month: 'long'")
        ->toContain("type={includeTime ? 'datetime-local' : 'date'}");
});

test('shared popup controls keep long labels readable within the viewport', function () {
    $selectSource = file_get_contents(resource_path('js/components/ui/select.tsx'));
    $dropdownMenuSource = file_get_contents(
        resource_path('js/components/ui/dropdown-menu.tsx'),
    );
    $employeeComboboxSource = file_get_contents(
        resource_path('js/components/hris-reference-combobox.tsx'),
    );

    expect($selectSource)
        ->toContain('min-w-0 flex-1 truncate text-left')
        ->toContain('w-max')
        ->toContain('min-w-(--anchor-width)')
        ->toContain('max-w-[min(24rem,var(--available-width))]')
        ->toContain('whitespace-normal')
        ->toContain('wrap-break-word')
        ->and($dropdownMenuSource)
        ->toContain('w-max')
        ->toContain('min-w-(--anchor-width)')
        ->toContain('max-w-[min(24rem,var(--available-width))]')
        ->toContain('whitespace-normal')
        ->toContain('wrap-break-word')
        ->and($employeeComboboxSource)
        ->toContain('w-max')
        ->toContain('min-w-(--anchor-width)')
        ->toContain('max-w-[min(24rem,var(--available-width))]')
        ->toContain('whitespace-normal')
        ->toContain('wrap-break-word');
});

test('inventory category filters require their parent category', function () {
    $inventoryItemsSource = file_get_contents(
        resource_path('js/pages/Inventory/Items/Index.tsx'),
    );

    expect($inventoryItemsSource)
        ->toMatch('/name="major_category_id"\s+value=\{selectedMajorCategoryId\}/')
        ->toMatch('/name="class_category_id"\s+value=\{selectedClassCategoryId\}/')
        ->toMatch('/name="series_category_id"\s+value=\{selectedSeriesCategoryId\}/')
        ->toMatch("/disabled=\{\s*selectedMajorCategoryId === ''\s*\}/")
        ->toMatch("/disabled=\{\s*selectedClassCategoryId === ''\s*\}/")
        ->toContain('Select a major category first')
        ->toContain('Select a class category first')
        ->not->toContain('` — ${category.major_category.name}`')
        ->not->toContain('` — ${category.class_category.name}`');
});

test('the property registry defaults its sort filter to newest record', function () {
    $assetIndexSource = file_get_contents(
        resource_path('js/pages/Inventory/Assets/Index.tsx'),
    );

    expect($assetIndexSource)
        ->toMatch("/name=\"sort\"\\s+value=\\{\\s*filters\\.sort \\|\\|\\s*'inventory_asset_id'\\s*\\}/")
        ->toContain('<option value="inventory_asset_id">')
        ->toContain('Newest record');
});

test('every reached workflow status uses the completed check presentation', function () {
    $workflowStatusSource = file_get_contents(
        resource_path('js/components/workflow-status.tsx'),
    );

    expect($workflowStatusSource)
        ->toContain('const isComplete = !isTerminal && index <= currentIndex;')
        ->not->toContain('isCompletedCurrent')
        ->toContain('border-emerald-500/40 bg-emerald-500/10');
});

test('RMS footer submission is associated with its form and cancel does not submit', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Returns/PrepareRmsDialog.tsx'));
    preg_match('/<form\s+id="([^"]+)"/', $source, $form);
    expect($form)->toHaveCount(2);
    $formId = preg_quote($form[1], '/');
    expect($source)
        ->toMatch('/<Button\s+type="submit"\s+form="'.$formId.'"/')
        ->toMatch('/<Button\s+type="button"[^>]+onClick=\{\(\) => setConfirmCancel\(true\)\}/')
        ->toContain('disablePointerDismissal')
        ->toContain('onOpenChange={(_open, details) => details.cancel()}');
});

test('request RMS preparation uses one dialog during initial loading and retries', function () {
    $requestDialog = file_get_contents(resource_path('js/pages/Inventory/Requests/ReturnSlipDialog.tsx'));
    $preparationDialog = file_get_contents(resource_path('js/pages/Inventory/Returns/PrepareRmsDialog.tsx'));
    expect($requestDialog)
        ->toContain('<PrepareRmsDialog')
        ->not->toContain('<Dialog')
        ->toContain('supply_request_id: request.id')
        ->toContain('return cancel;')
        ->and($preparationDialog)
        ->toContain('aria-label="Loading issued batches"')
        ->toContain('motion-reduce:animate-none')
        ->toContain('onClick={() => browse()}');
});

test('suggestion fields use themed comboboxes instead of native datalists', function () {
    $sources = collect(File::allFiles(resource_path('js')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'tsx')
        ->map(fn (SplFileInfo $file): string => $file->getContents())
        ->implode("\n");

    expect($sources)->not->toContain('<datalist');

    foreach (['inventory-item-combobox', 'responsibility-center-combobox'] as $component) {
        $source = file_get_contents(resource_path("js/components/{$component}.tsx"));

        expect($source)
            ->toContain('<Combobox.Root')
            ->toContain('<Combobox.Portal>')
            ->toContain('bg-popover text-popover-foreground')
            ->toContain('min-w-(--anchor-width)')
            ->toContain('whitespace-normal');
    }
});

test('the count planning action explicitly submits its form', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Counts/Index.tsx'));

    expect($source)->toMatch('/<Button\s+type="submit"[^>]*>\s*Create count session\s*<\/Button>/');
});

test('count session navigation connects to accessible page sections', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Counts/Show.tsx'));

    expect($source)
        ->toContain('Back to physical counts')
        ->toContain('aria-label="Count session sections"')
        ->toContain('href={`#count-${section}`}')
        ->toContain('id="count-overview"')
        ->toContain('id="count-worksheet"')
        ->toContain('id="count-activity"')
        ->toContain('setActivityOpen(true)')
        ->toContain('open={activityOpen}')
        ->toContain('aria-label="Count workflow"')
        ->toContain("? 'step'");
});

test('the count plan breadcrumb displays the plan title', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Counts/Show.tsx'));

    expect($source)
        ->toContain('CountShow.layout = ({ session }: { session: CountSession }) => ({')
        ->toContain('{ title: session.title }')
        ->not->toContain("{ title: 'Count session' }");
});

test('count action and observation dialogs submit their confirmation forms', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Counts/Show.tsx'));

    expect($source)
        ->toMatch('/<Button\s+type="submit"[^>]*>\s*Save observation\s*<\/Button>/')
        ->toMatch('/<Button\s+type="submit"[^>]*>\s*Confirm \{labels\[action\]/');
});

test('supply request filters submit explicitly and display capitalized status labels', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Requests/Index.tsx'));

    expect($source)->toMatch('/<Button\s+type="submit"\s+className="min-h-11 flex-1 md:flex-none"/')
        ->toContain('word.charAt(0).toUpperCase() + word.slice(1)', 'onSubmit={applyFilters}', 'status: event.target.value');
});

test('storage batch rows open a protected dialog with cancellation confirmation', function () {
    $index = file_get_contents(resource_path('js/pages/Inventory/Warehouse/Index.tsx'));
    $dialog = file_get_contents(resource_path('js/pages/Inventory/Warehouse/BatchStorageDialog.tsx'));

    expect($index)->toContain('setSelectedBatch(batch)', '<BatchStorageDialog')
        ->not->toContain('router.visit(', 'href={show(');
    expect($dialog)->toContain('disablePointerDismissal', 'event.cancel()', 'showCloseButton={false}',
        'setConfirmCancel(true)', 'Keep editing', 'Yes, close', 'onSaved={load}');
});

test('storage placement fields rely on server validation', function () {
    $source = file_get_contents(resource_path('js/pages/Inventory/Warehouse/Show.tsx'));

    expect($source)->toMatch('/<form\s+noValidate/')
        ->toContain('message={form.errors.attested}', 'message={form.errors.source_evidence_reference}', 'disabled={form.processing}')
        ->not->toContain('!form.data.attested', 'setCustomValidity(', 'reportValidity(');
});
