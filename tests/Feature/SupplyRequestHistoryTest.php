<?php

use Illuminate\Support\Facades\Process;

test('supply history describes each action without redundant status transitions', function () {
    $cases = [
        ['submit', 'submitted', null, 'submitted the request.'],
        ['approve', 'division_approved', 'division', 'approved the request as division head.'],
        ['approve', 'approved', 'department', 'approved the request as department head.'],
        ['approve', 'approved', null, 'approved the request.'],
        ['review', 'ready_for_release', null, 'reviewed stock and marked the request ready for release.'],
        ['review', 'awaiting_replenishment', null, 'reviewed stock and marked the request awaiting replenishment.'],
        ['release', 'partially_released', null, 'partially released the requested supplies.'],
        ['release', 'released', null, 'released all requested supplies.'],
        ['reject', 'rejected', null, 'rejected the request.'],
        ['cancel', 'cancelled', null, 'cancelled the request.'],
        ['reassess_after_procurement', 'ready_for_release', null, 'reassessed stock after procurement; the request is ready for release.'],
        ['reassess_after_procurement', 'awaiting_replenishment', null, 'reassessed stock after procurement; the request awaits replenishment.'],
        ['count_adjustment_requires_review', 'approved', null, 'marked the request for stock review after a count adjustment.'],
        ['record_return_slip', 'released', null, 'recorded the return slip reference.'],
        ['record_return_slip', 'partially_released', null, 'recorded the return slip reference.'],
    ];
    $entries = array_map(fn (array $case): array => ['action' => $case[0], 'to_status' => $case[1], 'metadata' => ['approval_stage' => $case[2]]], $cases);
    $result = Process::path(base_path())->input(json_encode($entries, JSON_THROW_ON_ERROR))
        ->timeout(60)->run(['node', base_path('tests/SupplyRequestHistory.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput())
        ->and(json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR))->toBe(array_column($cases, 3));
    $page = file_get_contents(resource_path('js/pages/Inventory/Requests/Index.tsx'));
    expect(substr_count($page, "label: 'Approve'"))->toBe(2)
        ->and($page)->not->toContain('statusLabel(record.approval_stage)', 'statusLabel(selectedRequest.approval_stage)');
    expect(preg_match_all('/supplyRequestHistoryDescription\(\s*action,?\s*\)/', $page))->toBe(2)
        ->and(substr_count($page, '<b>{action.actor.name}</b>'))->toBe(2);
});
