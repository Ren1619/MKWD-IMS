<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\HrisReference;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->with('employee:id,code,name,email')
                ->when($filters['search'] ?? null, function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('employee', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                    });
                })
                ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'employees' => HrisReference::query()
                ->where('type', HrisReference::TYPE_EMPLOYEE)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'type', 'code', 'name', 'email', 'metadata'])
                ->map(fn (HrisReference $employee): array => $employee->toSelectionOption()),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
            'filters' => $filters,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['password_confirmation']);

        User::query()->create([
            ...$data,
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User account created.',
        ]);

        return to_route('admin.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['password_confirmation']);

        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User account updated.',
        ]);

        return to_route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        try {
            DB::transaction(function () use ($user): void {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->delete();
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'user' => 'This account is required by existing inventory transactions. Deactivate it instead.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'IMS user account deleted.',
        ]);

        return to_route('admin.users.index');
    }
}
