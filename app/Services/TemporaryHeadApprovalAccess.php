<?php

namespace App\Services;

use App\Models\HrisReference;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TemporaryHeadApprovalAccess
{
    /** @return Builder<HrisReference> */
    public function employees(User $user): Builder
    {
        $query = HrisReference::query()->where('type', HrisReference::TYPE_EMPLOYEE)->where('is_active', true);
        $jurisdiction = $this->jurisdiction($user);
        if ($jurisdiction === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('metadata->position->department->id', $jurisdiction['department_id'])
            ->when(filled($jurisdiction['division_id']), fn (Builder $query) => $query->where('metadata->position->division->id', $jurisdiction['division_id']));
    }

    /** @return array{department_id: int|string, division_id: int|string|null}|null */
    public function jurisdiction(User $user): ?array
    {
        $position = data_get($user->employee?->metadata, 'position', []);
        $department = data_get($position, 'department.id');
        $division = data_get($position, 'division.id');

        if (! $user->is_active || ! $user->employee?->is_active
            || ! preg_match('/\A[a-z0-9][a-z0-9_-]*\.head@obx\.gov\.ph\z/i', $user->email)
            || blank($department) || filled(data_get($position, 'unit')) || filled(data_get($position, 'section'))) {
            return null;
        }

        if (filled(data_get($position, 'division')) && blank($division)) {
            return null;
        }

        return ['department_id' => $department, 'division_id' => $division];
    }

    public function canApprove(User $user, SupplyRequest $request): bool
    {
        return in_array($request->status, ['submitted', 'division_approved'], true)
            && $this->requiredStage($request) === ($this->pendingStatus($user) === 'submitted' ? 'division' : 'department')
            && $request->requester_reference_id !== null
            && $request->requester_reference_id !== $user->hris_reference_id
            && ($request->status !== 'division_approved' || $request->actions->contains(
                fn ($action): bool => $action->action === 'approve'
                    && $action->to_status === 'division_approved'
                    && $action->actor_user_id !== $user->id
                    && filled(data_get($action->metadata, 'hris_reference_id'))
                    && (string) data_get($action->metadata, 'hris_reference_id') !== (string) $user->hris_reference_id,
            ))
            && $this->employees($user)->whereKey($request->requester_reference_id)->exists();
    }

    public function pendingStatus(User $user): string
    {
        return filled(data_get($user->employee?->metadata, 'position.division.id'))
            ? 'submitted' : 'division_approved';
    }

    public function requiredStage(SupplyRequest $request): string
    {
        if ($request->status === 'division_approved') {
            return 'department';
        }

        return blank(data_get($request->requestedFor?->metadata, 'position.division'))
            && filled(data_get($request->requestedFor?->metadata, 'position.department.id'))
                ? 'department' : 'division';
    }
}
