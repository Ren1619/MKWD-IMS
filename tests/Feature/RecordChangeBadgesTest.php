<?php

use Illuminate\Support\Facades\Process;

test('live record badges distinguish arrivals and changes and expire without leaking between screens', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/RecordChangeBadges.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('live record badges are present across record lists detail views and mobile cards', function () {
    foreach (['Requests/Index', 'Procurement/Index', 'Assets/Index', 'Items/Index', 'Returns/Index', 'Deliveries/Index',
        'Counts/Index', 'MonthlyReports/Index', 'Disposals/Index', 'Transfers/Index', 'Accountability/Index', 'Categories/Index',
        'Warehouse/Index', 'Warehouse/Registry', 'Reports/Index', 'Dashboard', 'Assets/Show', 'Returns/Show', 'Deliveries/Show',
        'Counts/Show', 'MonthlyReports/Show', 'Disposals/Show', 'Transfers/Show', 'Warehouse/Show'] as $page) {
        expect(file_get_contents(resource_path('js/pages/Inventory/'.$page.'.tsx')))->toContain('<RecordChangeBadge');
    }
    foreach (['Users', 'AuditLogs'] as $page) {
        expect(file_get_contents(resource_path('js/pages/Admin/'.$page.'/Index.tsx')))->toContain('<RecordChangeBadge');
    }
    foreach (['Requests', 'Procurement', 'Returns', 'Deliveries', 'MonthlyReports'] as $page) {
        expect(substr_count(file_get_contents(resource_path('js/pages/Inventory/'.$page.'/Index.tsx')), '<RecordChangeBadge'))->toBe(2);
    }
    $hook = file_get_contents(resource_path('js/hooks/use-realtime-updates.ts'));
    expect($hook)->toContain('onSuccess:', 'recordChanges.compare(', 'after.url === before.url');
});
