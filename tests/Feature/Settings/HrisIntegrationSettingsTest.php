<?php

use App\Actions\Integrations\SyncHrisReferences;
use App\Models\AuditLog;
use App\Models\HrisIntegrationSetting;
use App\Models\User;
use App\Services\HrisEndpointGuard;
use App\UserRole;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('services.hris.url', null);
    config()->set('services.hris.employees_path', '/api/v1/inventory/employees');
    config()->set('services.hris.allowed_hosts', [
        'hris.example.test',
        'new-hris.example.test',
    ]);
});

test('the HRIS integration setting requires a recently confirmed password', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('hris-integration.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($superAdmin)
        ->put(route('hris-integration.update'), [
            'base_url' => 'https://hris.example.test',
        ])
        ->assertRedirect(route('password.confirm'));

    expect(HrisIntegrationSetting::query()->exists())->toBeFalse();
});

test('an expired password confirmation cannot reveal the HRIS API URL', function () {
    config()->set('services.hris.url', 'https://hris.example.test');

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->withSession([
            'auth.password_confirmed_at' => now()->subMinutes(16)->timestamp,
        ])
        ->get(route('hris-integration.edit'))
        ->assertRedirect(route('password.confirm'));
});

test('only super admins can access the HRIS integration setting', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('hris-integration.edit'))
        ->assertForbidden();
})->with([
    'inventory manager' => UserRole::InventoryManager,
    'employee' => UserRole::Employee,
]);

test('a super admin can view and update the HRIS API base URL', function () {
    config()->set('services.hris.url', 'https://hris.example.test');

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('hris-integration.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/hris-integration')
            ->where('baseUrl', 'https://hris.example.test')
            ->where('employeesPath', '/api/v1/inventory/employees')
            ->where('usingDatabaseOverride', false)
            ->where('allowedHosts', [
                'hris.example.test',
                'new-hris.example.test',
            ]));

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->put(route('hris-integration.update'), [
            'base_url' => ' https://new-hris.example.test/ ',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('hris-integration.edit');

    $setting = HrisIntegrationSetting::query()->sole();

    expect($setting->base_url)->toBe('https://new-hris.example.test')
        ->and($setting->updated_by)->toBe($superAdmin->id);

    $auditLog = AuditLog::query()
        ->where('auditable_type', HrisIntegrationSetting::class)
        ->sole();

    expect($auditLog->new_values)->not->toHaveKey('base_url');
});

test('unsafe HRIS API destinations are rejected', function (string $baseUrl) {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->put(route('hris-integration.update'), ['base_url' => $baseUrl])
        ->assertInvalid('base_url');

    expect(HrisIntegrationSetting::query()->exists())->toBeFalse();
})->with([
    'unencrypted HTTP' => 'http://hris.example.test',
    'localhost' => 'https://localhost',
    'private IPv4 address' => 'https://10.0.0.8',
    'loopback IPv6 address' => 'https://[::1]',
    'embedded credentials' => 'https://admin:secret@hris.example.test',
    'query string' => 'https://hris.example.test?redirect=https://127.0.0.1',
    'fragment' => 'https://hris.example.test#internal',
    'unapproved public host' => 'https://attacker.example.com',
]);

test('the HRIS client prefers the password-protected database setting', function () {
    config()->set('services.hris.url', 'https://hris.example.test');

    HrisIntegrationSetting::query()->create([
        'id' => HrisIntegrationSetting::SINGLETON_ID,
        'base_url' => 'https://new-hris.example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://new-hris.example.test/api/v1/inventory/employees*' => Http::response(['data' => [[
            'employee_id' => 'emp-200',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.test',
        ]], 'meta' => ['next_cursor' => null]]),
    ]);

    app(SyncHrisReferences::class)->handle();

    Http::assertSent(fn (Request $request): bool => $request->url()
        === 'https://new-hris.example.test/api/v1/inventory/employees?per_page=250');
});

test('HTTP loopback HRIS URLs are allowed only for the configured local development endpoint', function (string $environment, string $url, bool $allowed) {
    app()->instance('env', $environment);
    config()->set('services.hris.url', 'http://localhost:8000');

    $error = app(HrisEndpointGuard::class)->validationError($url);

    expect($error === null)->toBe($allowed);
})->with([
    'configured local endpoint' => ['local', 'http://localhost:8000', true],
    'production endpoint' => ['production', 'http://localhost:8000', false],
    'different local port' => ['local', 'http://localhost:9000', false],
    'public HTTP endpoint' => ['local', 'http://hris.example.test', false],
]);
