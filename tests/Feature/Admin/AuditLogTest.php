<?php

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated inventory changes record the actor and changed values', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin);

    $item = InventoryItem::factory()->create(['name' => 'Audit Paper']);
    $item->update(['quantity' => 42]);

    $created = AuditLog::query()
        ->where('auditable_type', InventoryItem::class)
        ->where('auditable_id', $item->inventory_item_id)
        ->where('event', 'created')
        ->firstOrFail();
    $updated = AuditLog::query()
        ->where('auditable_type', InventoryItem::class)
        ->where('auditable_id', $item->inventory_item_id)
        ->where('event', 'updated')
        ->firstOrFail();

    expect($created->user_id)->toBe($admin->id)
        ->and($created->description)->toContain('Audit Paper')
        ->and($updated->old_values)->toMatchArray(['quantity' => 0])
        ->and($updated->new_values)->toMatchArray(['quantity' => 42]);
});

test('only super admins can view the audit log module', function () {
    $admin = User::factory()->superAdmin()->create();
    $inventoryUser = User::factory()->create();
    AuditLog::factory()->for($admin)->create(['event' => 'login']);

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index', ['event' => 'login']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('auditLogs.data', 1)
            ->where('auditLogs.data.0.event', 'login')
        );

    $this->actingAs($inventoryUser)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();
});
