<?php

use Illuminate\Support\Facades\File;

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
