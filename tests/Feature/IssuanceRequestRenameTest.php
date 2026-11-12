<?php

use App\Models\DepartmentLiquidation;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('IRF migration preserves request values slip snapshots and historical liquidation references in both directions', function () {
    $request = SupplyRequest::factory()->create(['irf_no' => '11-12-007', 'irf_date' => '2026-11-12', 'irf_sequence' => 7]);
    $slip = $request->issuanceSlips()->create([
        'slip_no' => '11-12-012', 'issued_at' => '2026-11-12', 'issued_by_user_id' => $request->requester_user_id,
        'snapshot' => ['irf_no' => '11-12-007', 'department' => 'Operations'], 'lines' => [], 'history' => [],
    ]);
    $historical = DepartmentLiquidation::query()->create([
        'document_no' => 'OLD-001', 'liquidation_date' => '2026-11-12', 'department' => 'Operations',
        'supply_request_id' => $request->id, 'irf_no' => $request->irf_no, 'requested_by_user_id' => $request->requester_user_id,
        'requested_by_name' => $request->requester_name, 'lines' => [], 'history' => [],
    ]);
    DB::table('issuance_request_sequences')->insert(['date' => '2026-11-12', 'last_number' => 7]);
    $migration = require database_path('migrations/2026_11_12_031736_rename_isf_fields_to_irf.php');
    $migration->down();
    expect(Schema::hasColumn('supply_requests', 'isf_no'))->toBeTrue()
        ->and(DB::table('supply_requests')->where('id', $request->id)->value('isf_no'))->toBe('11-12-007')
        ->and(json_decode(DB::table('issuance_slips')->where('id', $slip->id)->value('snapshot'), true)['isf_no'])->toBe('11-12-007');
    $migration->up();
    $request->refresh();
    expect($request->irf_no)->toBe('11-12-007')->and($request->irf_date->toDateString())->toBe('2026-11-12')
        ->and($request->irf_sequence)->toBe(7)->and($historical->fresh()->irf_no)->toBe('11-12-007')
        ->and($slip->fresh()->snapshot)->toBe(['department' => 'Operations', 'irf_no' => '11-12-007'])
        ->and($slip->fresh()->slip_no)->toBe('11-12-012')->and($slip->fresh()->supply_request_id)->toBe($request->id)
        ->and(Schema::hasColumn('supply_requests', 'isf_no'))->toBeFalse()
        ->and(Schema::hasColumn('department_liquidations', 'isf_no'))->toBeFalse()
        ->and(Schema::hasIndex('supply_requests', 'supply_requests_irf_no_index'))->toBeTrue()
        ->and(Schema::hasIndex('supply_requests', 'supply_requests_irf_date_irf_sequence_unique'))->toBeTrue()
        ->and(DB::table('issuance_request_sequences')->where('date', '2026-11-12')->value('last_number'))->toBe(7);
});

test('request responses and search use the canonical IRF field names', function () {
    $user = User::factory()->employee()->create();
    $request = SupplyRequest::factory()->create(['requester_user_id' => $user->id, 'irf_no' => '11-12-777']);
    $this->actingAs($user)->get(route('inventory.requests.index', ['search' => '11-12-777']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.irf_no', $request->irf_no)
            ->missing('requests.data.0.isf_no')->has('issuanceContext.irf_no')->missing('issuanceContext.isf_no')
            ->has('requests.data.0.issuance_slips', 0));
});

test('request table places IS after office and renders N A until an issuance slip exists', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/SupplyRequestColumns.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});
