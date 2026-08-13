<?php

use App\Models\AuditLog;
use App\Models\HrisReference;
use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('only super admins can view user management', function () {
    $admin = User::factory()->superAdmin()->create();
    $inventoryUser = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 2)
            ->has('roles', 3)
        );

    $this->actingAs($admin)
        ->get(route('admin.users.index', [
            'role' => UserRole::SuperAdmin->value,
            'status' => 'active',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $admin->id)
            ->where('filters.role', UserRole::SuperAdmin->value)
            ->where('filters.status', 'active')
        );

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => $inventoryUser->email]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $inventoryUser->id)
            ->where('filters.search', $inventoryUser->email)
        );

    $this->actingAs($inventoryUser)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('a super admin can create an employee-linked account', function () {
    $admin = User::factory()->superAdmin()->create();
    $employee = HrisReference::factory()->create([
        'type' => HrisReference::TYPE_EMPLOYEE,
        'email' => 'employee@example.test',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'hris_reference_id' => $employee->id,
            'name' => $employee->name,
            'email' => 'employee@example.test',
            'role' => UserRole::Employee->value,
            'is_active' => true,
            'password' => 'Secure-password-123',
            'password_confirmation' => 'Secure-password-123',
        ])
        ->assertRedirectToRoute('admin.users.index');

    $account = User::query()->where('email', 'employee@example.test')->firstOrFail();

    expect($account->hris_reference_id)->toBe($employee->id)
        ->and($account->email_verified_at)->not->toBeNull()
        ->and($account->is_active)->toBeTrue()
        ->and(AuditLog::query()
            ->where('user_id', $admin->id)
            ->where('event', 'created')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $account->id)
            ->exists())->toBeTrue();

    $auditLog = AuditLog::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $account->id)
        ->firstOrFail();

    expect($auditLog->new_values)->not->toHaveKeys([
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ]);
});

test('a super admin can update account access but cannot remove their own access', function () {
    $admin = User::factory()->superAdmin()->create();
    $managedUser = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $managedUser), [
            'hris_reference_id' => null,
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => UserRole::InventoryManager->value,
            'is_active' => false,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirectToRoute('admin.users.index');

    expect($managedUser->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), [
            'hris_reference_id' => null,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Employee->value,
            'is_active' => false,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionHasErrors('role');

    expect($admin->refresh()->isSuperAdmin())->toBeTrue()
        ->and($admin->is_active)->toBeTrue();
});
