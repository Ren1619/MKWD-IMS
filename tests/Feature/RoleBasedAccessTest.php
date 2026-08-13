<?php

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

test('role abilities follow the access matrix', function (
    UserRole $role,
    bool $managesUsers,
    bool $viewsAuditLogs,
    bool $managesInventory,
) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->can('manage-users'))->toBe($managesUsers)
        ->and($user->can('view-audit-logs'))->toBe($viewsAuditLogs)
        ->and($user->can('manage-inventory'))->toBe($managesInventory);
})->with([
    'super admin' => [UserRole::SuperAdmin, true, true, true],
    'inventory manager' => [UserRole::InventoryManager, false, false, true],
    'employee' => [UserRole::Employee, false, false, false],
]);

test('employees have read-only inventory access', function () {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee)->get(route('dashboard'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.categories.index'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.items.index'))->assertOk();
    $this->actingAs($employee)->get(route('inventory.assets.index'))->assertOk();

    $this->actingAs($employee)
        ->post(route('inventory.categories.store'), [
            'type' => 'major',
            'code' => 'SUP',
            'name' => 'Supplies',
        ])
        ->assertForbidden();

    expect(InventoryMajorCategory::query()->count())->toBe(0);
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
