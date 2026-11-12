<?php

use App\Models\HrisReference;
use App\Models\SupplyRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('past actors retain read access without receiving further workflow permissions', function (string $action, string $status) {
    $actor = User::factory()->employee()->create();
    $record = SupplyRequest::factory()->create(['status' => $status, 'purpose' => 'History visibility target']);
    $record->actions()->create(['actor_user_id' => $actor->id, 'action' => $action, 'to_status' => $status, 'attestation' => 'Recorded action.']);
    SupplyRequest::factory()->create(['status' => $status]);

    $this->actingAs($actor)->get(route('inventory.requests.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)
            ->where('requests.data.0.id', $record->id)
            ->where('requests.data.0.can_approve_as_head', false)->where('canManage', false));
    $this->get(route('inventory.requests.index', ['search' => 'does not match']))->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
    $this->get(route('inventory.requests.index', ['status' => $status === 'released' ? 'submitted' : 'released']))
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
    foreach (['approve', 'review', 'release', 'reject', 'cancel'] as $attempt) {
        $this->patch(route('inventory.requests.transition', $record), ['action' => $attempt, 'attested' => true])->assertForbidden();
    }
})->with([
    'division approver' => ['approve', 'division_approved'],
    'department approver' => ['approve', 'approved'],
    'reviewer' => ['review', 'ready_for_release'],
    'issuer' => ['release', 'released'],
    'rejector' => ['reject', 'rejected'],
    'canceller' => ['cancel', 'cancelled'],
    'return slip recorder' => ['record_return_slip', 'released'],
]);

test('requested-for employees see requests submitted for them without granting unrelated employees access', function () {
    $reference = HrisReference::factory()->create();
    $recipient = User::factory()->employee()->create(['hris_reference_id' => $reference->id]);
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $reference->id, 'status' => 'released']);
    $this->actingAs($recipient)->get(route('inventory.requests.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('requests.data.0.id', $record->id));
    $this->actingAs(User::factory()->employee()->create())->get(route('inventory.requests.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
});

test('division approval precedes a separate department decision and stock review', function () {
    $reference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1], 'division' => ['id' => 2]]]]);
    $divisionReference = HrisReference::factory()->create(['metadata' => $reference->metadata]);
    $departmentReference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $divisionHead = User::factory()->employee()->create(['email' => 'div.head@obx.gov.ph', 'hris_reference_id' => $divisionReference->id]);
    $departmentHead = User::factory()->employee()->create(['email' => 'dept.head@obx.gov.ph', 'hris_reference_id' => $departmentReference->id]);
    $manager = User::factory()->inventoryManager()->create();
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $reference->id, 'status' => 'submitted']);
    $route = route('inventory.requests.transition', $record);

    $this->actingAs($departmentHead)->patch($route, ['action' => 'approve', 'attested' => true])->assertForbidden();
    $this->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
    $this->actingAs($manager)->patch($route, ['action' => 'approve', 'attested' => true])->assertForbidden();
    $this->actingAs($divisionHead)->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('division_approved')
        ->and($record->actions()->sole()->metadata['approval_stage'])->toBe('division');
    $this->patch($route, ['action' => 'approve', 'attested' => true])->assertForbidden();
    $this->actingAs($manager)->patch($route, ['action' => 'review', 'attested' => true])->assertSessionHasErrors('action');
    $this->patch($route, ['action' => 'release', 'attested' => true])->assertSessionHasErrors('action');
    $this->actingAs($departmentHead)->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page
        ->has('requests.data', 1)->where('requests.data.0.can_approve_as_head', true)
        ->where('requests.data.0.approval_stage', 'department'));
    $this->patch($route, ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('approved')
        ->and($record->actions()->where('action', 'approve')->count())->toBe(2)
        ->and($record->actions()->where('to_status', 'approved')->sole()->metadata['approval_stage'])->toBe('department');
    $this->actingAs($manager)->patch($route, ['action' => 'review', 'attested' => true])->assertSessionHasNoErrors();
});

test('department-only requests go directly to the department head', function (?array $division) {
    $reference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1], 'division' => $division]]]);
    $headReference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $head = User::factory()->employee()->create(['email' => 'dept.head@obx.gov.ph', 'hris_reference_id' => $headReference->id]);
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $reference->id, 'status' => 'submitted']);
    $this->actingAs($head)->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page
        ->has('requests.data', 1)->where('requests.data.0.can_approve_as_head', true));
    $this->patch(route('inventory.requests.transition', $record), ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('approved')
        ->and($record->actions()->sole()->metadata['approval_stage'])->toBe('department');
})->with(['null division' => [null]]);

test('department approval requires evidence from a different division approver', function (string $evidence) {
    $reference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1], 'division' => ['id' => 2]]]]);
    $headReference = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $head = User::factory()->employee()->create(['email' => 'dept.head@obx.gov.ph', 'hris_reference_id' => $headReference->id]);
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $reference->id, 'status' => 'division_approved']);
    if ($evidence !== 'missing') {
        $priorActor = $evidence === 'same user' ? $head : User::factory()->employee()->create();
        $record->actions()->create(['actor_user_id' => $priorActor->id, 'action' => 'approve', 'from_status' => 'submitted', 'to_status' => 'division_approved', 'attestation' => 'Division decision.', 'metadata' => ['hris_reference_id' => $headReference->id]]);
    }
    $this->actingAs($head)->patch(route('inventory.requests.transition', $record), ['action' => 'approve', 'attested' => true])->assertForbidden();
    expect($record->fresh()->status)->toBe('division_approved');
})->with(['missing', 'same user', 'same employee']);

test('heads see and approve only requests at their stage within their jurisdiction', function (?int $division, int $visible) {
    $headReference = HrisReference::factory()->create(['metadata' => ['position' => [
        'department' => ['id' => 1], 'division' => $division ? ['id' => $division] : null,
    ]]]);
    $head = User::factory()->employee()->create(['email' => 'ops.head@obx.gov.ph', 'hris_reference_id' => $headReference->id]);
    $requests = [];
    foreach ([[1, 2], [1, 3], [4, 2]] as [$departmentId, $divisionId]) {
        $employee = HrisReference::factory()->create(['metadata' => ['position' => [
            'department' => ['id' => $departmentId], 'division' => ['id' => $divisionId], 'unit' => ['id' => 9],
        ]]]);
        $record = SupplyRequest::factory()->create(['requester_reference_id' => $employee->id, 'status' => $division === null ? 'division_approved' : 'submitted']);
        if ($division === null) {
            $priorHead = User::factory()->employee()->create(['hris_reference_id' => HrisReference::factory()->create()->id]);
            $record->actions()->create(['actor_user_id' => $priorHead->id, 'action' => 'approve', 'from_status' => 'submitted', 'to_status' => 'division_approved', 'attestation' => 'Division approved.', 'metadata' => ['hris_reference_id' => $priorHead->hris_reference_id]]);
        }
        $requests[] = $record;
    }
    $this->actingAs($head)->get(route('inventory.requests.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', $visible)
            ->where('canManage', false)->where('requests.data.0.can_approve_as_head', true));

    $this->patch(route('inventory.requests.transition', $requests[2]), ['action' => 'approve', 'attested' => true])->assertForbidden();
    $this->patch(route('inventory.requests.transition', $requests[0]), ['action' => 'review', 'attested' => true])->assertForbidden();
    $this->patch(route('inventory.requests.transition', $requests[0]), ['action' => 'release', 'attested' => true])->assertForbidden();
    $this->patch(route('inventory.requests.transition', $requests[0]), ['action' => 'approve', 'attested' => true])->assertSessionHasNoErrors();
    expect($requests[0]->fresh()->status)->toBe($division === null ? 'approved' : 'division_approved');
    if ($division !== null) {
        $this->patch(route('inventory.requests.transition', $requests[1]), ['action' => 'reject', 'attested' => true])->assertForbidden();
    }
})->with(['department head' => [null, 2], 'division head' => [2, 1]]);

test('head detection fails closed for invalid accounts and assignments', function (string $email, array $position, bool $active) {
    $employee = HrisReference::factory()->create(['metadata' => ['position' => $position], 'is_active' => $active]);
    $head = User::factory()->employee()->create(['email' => $email, 'hris_reference_id' => $employee->id]);
    $other = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $other->id, 'status' => 'submitted']);
    $this->actingAs($head)->patch(route('inventory.requests.transition', $record), ['action' => 'approve', 'attested' => true])->assertForbidden();
})->with([
    'ordinary email' => ['ops@obx.gov.ph', ['department' => ['id' => 1]], true],
    'wrong domain' => ['ops.head@example.com', ['department' => ['id' => 1]], true],
    'unit assigned' => ['ops.head@obx.gov.ph', ['department' => ['id' => 1], 'unit' => ['id' => 2]], true],
    'section assigned' => ['ops.head@obx.gov.ph', ['department' => ['id' => 1], 'section' => ['id' => 2]], true],
    'missing department' => ['ops.head@obx.gov.ph', [], true],
    'inactive reference' => ['ops.head@obx.gov.ph', ['department' => ['id' => 1]], false],
]);

test('heads cannot approve requests made for themselves', function () {
    $employee = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $head = User::factory()->employee()->create(['email' => 'ops.head@obx.gov.ph', 'hris_reference_id' => $employee->id]);
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $employee->id, 'status' => 'submitted']);
    $this->actingAs($head)->patch(route('inventory.requests.transition', $record), ['action' => 'approve', 'attested' => true])->assertForbidden();
});

test('head decisions require a pending linked request and record the decision', function () {
    $headEmployee = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $recipient = HrisReference::factory()->create(['metadata' => ['position' => ['department' => ['id' => 1]]]]);
    $head = User::factory()->employee()->create(['email' => 'ops.head@obx.gov.ph', 'hris_reference_id' => $headEmployee->id]);
    $this->actingAs($head);
    foreach ([['requester_reference_id' => null, 'status' => 'submitted'], ['requester_reference_id' => $recipient->id, 'status' => 'approved']] as $attributes) {
        $record = SupplyRequest::factory()->create($attributes);
        $this->patch(route('inventory.requests.transition', $record), ['action' => 'reject', 'attested' => true])->assertForbidden();
    }
    $record = SupplyRequest::factory()->create(['requester_reference_id' => $recipient->id, 'status' => 'submitted']);
    $this->patch(route('inventory.requests.transition', $record), ['action' => 'cancel', 'attested' => true])->assertForbidden();
    $this->patch(route('inventory.requests.transition', $record), ['action' => 'reject', 'attested' => true, 'remarks' => 'Not required.'])->assertSessionHasNoErrors();
    expect($record->fresh()->status)->toBe('rejected')
        ->and($record->actions()->sole()->actor_user_id)->toBe($head->id);
});
