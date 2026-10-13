<?php

use App\Models\HrisReference;
use App\Models\InventoryAsset;
use App\Models\InventoryItem;
use App\Models\InventoryMajorCategory;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;

test('the system exposes exactly the supported roles', function () {
    expect(UserRole::cases())->toEqual([
        UserRole::SuperAdmin,
        UserRole::InventoryManager,
        UserRole::Employee,
    ]);
});

test('role abilities follow the access matrix', function (UserRole $role, array $abilities) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('manage-users'))->toBe($abilities['manage_users'])
        ->and($user->can('view-audit-logs'))->toBe($abilities['view_audit_logs'])
        ->and($user->can('manage-integrations'))->toBe($abilities['manage_integrations'])
        ->and($user->can('manage-inventory'))->toBe($abilities['manage_inventory']);
})->with([
    'super admin' => [UserRole::SuperAdmin, [
        'manage_users' => true,
        'view_audit_logs' => true,
        'manage_integrations' => true,
        'manage_inventory' => true,
    ]],
    'inventory manager' => [UserRole::InventoryManager, [
        'manage_users' => false,
        'view_audit_logs' => false,
        'manage_integrations' => false,
        'manage_inventory' => true,
    ]],
    'employee' => [UserRole::Employee, [
        'manage_users' => false,
        'view_audit_logs' => false,
        'manage_integrations' => false,
        'manage_inventory' => false,
    ]],
]);

test('employees have read-only inventory access', function () {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee)->get(route('dashboard'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.categories.index'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.items.index'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.assets.index'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.semi_expendable.index'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.accountability.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('inventory.reports.index'))->assertForbidden();

    $this->actingAs($employee)
        ->post(route('inventory.semi_expendable.store'))
        ->assertForbidden();

    $this->actingAs($employee)
        ->post(route('inventory.categories.store'), [
            'type' => 'major',
            'code' => 'SUP',
            'name' => 'Supplies',
        ])
        ->assertForbidden();

    expect(InventoryMajorCategory::query()->count())->toBe(0);

    $archivedItem = InventoryItem::factory()->create();
    $archivedItem->delete();

    $this->actingAs($employee)
        ->patch(route('inventory.items.restore', $archivedItem->inventory_item_id))
        ->assertForbidden();

    $asset = InventoryAsset::factory()->create();

    $this->actingAs($employee)
        ->patch(route('inventory.assets.update_state', $asset), [
            'lifecycle_status' => 'retired',
            'condition_status' => 'fair',
        ])
        ->assertForbidden();
});

test('employees see only PPE and semi-expendable property assigned to them', function () {
    $employeeReference = HrisReference::factory()->create();
    $otherReference = HrisReference::factory()->create();
    $employee = User::factory()->employee()->create([
        'hris_reference_id' => $employeeReference->id,
    ]);
    $assignedPpe = InventoryAsset::factory()->create([
        'acquisition_cost' => 50000,
        'current_custodian_reference_id' => $employeeReference->id,
    ]);
    $otherPpe = InventoryAsset::factory()->create([
        'acquisition_cost' => 50000,
        'current_custodian_reference_id' => $otherReference->id,
    ]);
    $assignedSemiExpendable = InventoryAsset::factory()->create([
        'acquisition_cost' => 49999.99,
        'current_custodian_reference_id' => $employeeReference->id,
    ]);
    $otherSemiExpendable = InventoryAsset::factory()->create([
        'acquisition_cost' => 49999.99,
        'current_custodian_reference_id' => $otherReference->id,
    ]);

    $this->actingAs($employee)->get(route('inventory.assets.index'))->assertInertia(fn ($page) => $page
        ->has('assets.data', 1)
        ->where('assets.data.0.inventory_asset_id', $assignedPpe->inventory_asset_id));
    $this->get(route('inventory.semi_expendable.index'))->assertInertia(fn ($page) => $page
        ->has('assets.data', 1)
        ->where('assets.data.0.inventory_asset_id', $assignedSemiExpendable->inventory_asset_id)
        ->where('registrySummary.total', 1)
        ->where('registrySummary.acquisition_cost', 49999.99));

    expect([$otherPpe->inventory_asset_id, $otherSemiExpendable->inventory_asset_id])
        ->not->toContain($assignedPpe->inventory_asset_id, $assignedSemiExpendable->inventory_asset_id);
});

test('property governance access is limited to designated HRIS positions and inventory leadership', function (string $position, bool $allowed) {
    $reference = HrisReference::factory()->create([
        'metadata' => ['position' => ['name' => $position]],
    ]);
    $user = User::factory()->employee()->create(['hris_reference_id' => $reference->id]);

    $this->actingAs($user)->get(route('inventory.accountability.index'))
        ->when($allowed, fn ($response) => $response->assertOk(), fn ($response) => $response->assertForbidden());
    $this->get(route('inventory.reports.index'))
        ->when($allowed, fn ($response) => $response->assertOk(), fn ($response) => $response->assertForbidden());
})->with([
    'PPE chairman' => ['PPE Chairman', true],
    'supply division manager' => ['Supply Division Manager', true],
    'HR department manager' => ['HR Department Manager', true],
    'existing HR manager title' => ['HR Manager', true],
    'unrelated employee' => ['Systems Analyst', false],
]);

test('inventory leaders retain property governance access', function () {
    foreach ([User::factory()->inventoryManager()->create(), User::factory()->superAdmin()->create()] as $user) {
        $this->actingAs($user)->get(route('inventory.accountability.index'))->assertOk();
        $this->get(route('inventory.reports.index'))->assertOk();
    }
});

test('inventory managers can modify inventory but cannot administer users', function () {
    $inventoryManager = User::factory()->inventoryManager()->create();

    $this->actingAs($inventoryManager)
        ->post(route('inventory.categories.store'), [
            'type' => 'major',
            'code' => 'SUP',
            'name' => 'Supplies',
        ])
        ->assertRedirectToRoute('inventory.categories.index');

    $this->actingAs($inventoryManager)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    expect(InventoryMajorCategory::query()->sole()->code)->toBe('SUP');
});

test('new users default to the employee role', function () {
    $user = User::query()->create([
        'name' => 'Default Employee',
        'email' => 'default-employee@example.test',
        'password' => 'password',
    ])->refresh();

    expect($user->role)->toBe(UserRole::Employee);
});

test('the legacy inventory role is migrated to inventory manager', function () {
    $userId = DB::table('users')->insertGetId([
        'name' => 'Legacy Inventory User',
        'email' => 'legacy-inventory@example.test',
        'password' => 'password',
        'role' => 'inventory_user',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path(
        'migrations/2026_08_12_005012_migrate_inventory_user_role_to_inventory_manager.php',
    );
    $migration->up();

    expect(DB::table('users')->where('id', $userId)->value('role'))
        ->toBe(UserRole::InventoryManager->value);
});
