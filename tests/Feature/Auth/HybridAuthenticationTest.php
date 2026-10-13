<?php

use App\Models\AuditLog;
use App\Models\HrisReference;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.hris.auth_enabled', true);
    config()->set('services.hris.url', 'https://hris.example.test');
    config()->set('services.hris.auth_token', 'integration-token');
    config()->set('services.hris.auth_path', '/api/v1/auth/verify');
    config()->set('services.hris.development_fallback_enabled', true);
    Http::preventStrayRequests();
});

test('an HRIS employee is provisioned as a least privilege IMS user', function () {
    $reference = HrisReference::factory()->create([
        'external_id' => 'EMP-0001',
        'name' => 'HRIS Employee',
        'email' => 'employee@example.test',
    ]);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'employee_id' => 'EMP-0001',
                'is_active' => true,
                'roles' => ['employee'],
            ],
        ]),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'employee@example.test',
        'password' => 'current-hris-password',
    ]);

    $user = User::query()->sole();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));

    expect($user->hris_reference_id)->toBe($reference->getKey())
        ->and($user->name)->toBe('HRIS Employee')
        ->and($user->role)->toBe(UserRole::Employee)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('current-hris-password', $user->password))->toBeTrue()
        ->and($user->last_hris_authenticated_at)->not->toBeNull()
        ->and(AuditLog::query()->where('user_id', $user->id)->where('event', 'login')->value('new_values'))
        ->toMatchArray(['authentication_source' => 'hris']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hris.example.test/api/v1/auth/verify'
        && $request->hasHeader('Authorization', 'Bearer integration-token')
        && $request['email'] === $user->email
        && $request['password'] === 'current-hris-password');
});

test('a successful HRIS login synchronizes the role of an existing IMS account', function () {
    $reference = HrisReference::factory()->create(['external_id' => 'EMP-0001']);
    $user = User::factory()->inventoryManager()->create([
        'hris_reference_id' => null,
        'email' => 'employee@example.test',
        'password' => 'old-ims-password',
    ]);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'employee_id' => 'EMP-0001',
                'is_active' => true,
                'roles' => ['employee'],
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'current-hris-password',
    ]);

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->hris_reference_id)->toBe($reference->getKey())
        ->and($user->role)->toBe(UserRole::Employee)
        ->and(Hash::check('current-hris-password', $user->password))->toBeTrue();
});

test('HRIS inventory manager access is synchronized into IMS authorization', function () {
    HrisReference::factory()->create(['external_id' => 'EMP-0001']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'employee_id' => 'EMP-0001',
                'is_active' => true,
                'roles' => ['employee', 'inventory_manager'],
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => 'inventory-manager@example.test',
        'password' => 'current-hris-password',
    ]);

    $user = User::query()->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe(UserRole::InventoryManager)
        ->and(Gate::forUser($user)->allows('manage-inventory'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage-users'))->toBeFalse();
});

test('an HRIS super administrator without an employee record can authenticate', function () {
    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'account_type' => 'super_admin',
                'employee_id' => null,
                'first_name' => 'System',
                'middle_name' => null,
                'last_name' => 'Administrator',
                'name_extension' => null,
                'is_active' => true,
                'roles' => ['super_admin'],
            ],
        ]),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'superadmin@example.test',
        'password' => 'current-hris-password',
    ]);

    $user = User::query()->sole();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));

    expect($user->hris_reference_id)->toBeNull()
        ->and($user->name)->toBe('System Administrator')
        ->and($user->role)->toBe(UserRole::SuperAdmin)
        ->and(Gate::forUser($user)->allows('manage-users'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('manage-inventory'))->toBeTrue()
        ->and($user->last_hris_authenticated_at)->not->toBeNull();
});

test('HRIS-only and unknown roles receive least privilege IMS access', function (array $roles) {
    HrisReference::factory()->create(['external_id' => 'EMP-0001']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'employee_id' => 'EMP-0001',
                'is_active' => true,
                'roles' => $roles,
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => 'employee@example.test',
        'password' => 'current-hris-password',
    ]);

    $user = User::query()->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe(UserRole::Employee)
        ->and(Gate::forUser($user)->allows('manage-inventory'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manage-users'))->toBeFalse();
})->with([
    'HR administrator' => [['employee', 'hr_admin']],
    'department head' => [['employee', 'department_head']],
    'unknown role' => [['future_hris_role']],
    'no assigned roles' => [[]],
]);

test('an unsupported HRIS account type cannot authenticate', function () {
    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'account_type' => 'unlinked_user',
                'employee_id' => null,
                'is_active' => true,
                'roles' => ['employee'],
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => 'unlinked@example.test',
        'password' => 'current-hris-password',
    ]);

    $this->assertGuest();
    expect(User::query()->exists())->toBeFalse();
});

test('HRIS credential and account rejections never use development fallback', function (int $status) {
    $user = User::factory()->create(['password' => 'valid-ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([], $status),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'valid-ims-password',
    ]);

    $this->assertGuest();
    expect($user->fresh()->last_fallback_authenticated_at)->toBeNull();
})->with([401, 403, 404, 422, 429]);

test('an HRIS server outage uses the development IMS account fallback', function () {
    $user = User::factory()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([], 503),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertAuthenticatedAs($user);

    expect($user->fresh()->last_fallback_authenticated_at)->not->toBeNull()
        ->and(AuditLog::query()->where('user_id', $user->id)->where('event', 'login')->value('new_values'))
        ->toMatchArray(['authentication_source' => 'development_fallback']);
});

test('an HRIS connection failure uses the development IMS account fallback', function () {
    $user = User::factory()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::failedConnection(),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('an empty successful HRIS response uses the development IMS account fallback', function () {
    $user = User::factory()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response(null),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('an HRIS response without roles uses the development IMS account fallback', function () {
    $user = User::factory()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => ['employee_id' => 'EMP-0001', 'is_active' => true],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_fallback_authenticated_at)->not->toBeNull();
});

test('development fallback must be explicitly enabled', function () {
    config()->set('services.hris.development_fallback_enabled', false);
    $user = User::factory()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([], 503),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertGuest();
});

test('development fallback cannot run in production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');
    $user = User::factory()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([], 503),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertGuest();
});

test('an inactive IMS account cannot use development fallback', function () {
    $user = User::factory()->inactive()->create(['password' => 'ims-password']);

    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([], 503),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertGuest();
});

test('successful HRIS authentication requires a synchronized active employee reference', function () {
    Http::fake([
        'https://hris.example.test/api/v1/auth/verify' => Http::response([
            'data' => [
                'employee_id' => 'NOT-SYNCHRONIZED',
                'is_active' => true,
                'roles' => ['employee'],
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => 'employee@example.test',
        'password' => 'current-hris-password',
    ]);

    $this->assertGuest();
    expect(User::query()->exists())->toBeFalse();
});

test('disabled HRIS authentication permits IMS login only through development fallback', function () {
    config()->set('services.hris.auth_enabled', false);
    $user = User::factory()->create(['password' => 'ims-password']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'ims-password',
    ]);

    $this->assertAuthenticatedAs($user);
    Http::assertNothingSent();
});
