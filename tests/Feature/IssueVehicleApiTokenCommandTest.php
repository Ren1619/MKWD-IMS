<?php

use App\Models\User;

test('it issues a scoped expiring token for an active user', function () {
    $user = User::factory()->create();

    $this->artisan('ims:issue-vehicle-api-token', [
        'email' => $user->email,
        'name' => 'fleet-portal',
        '--expires' => 30,
    ])
        ->expectsOutputToContain('Copy this token now')
        ->assertSuccessful();

    $token = $user->tokens()->sole();

    expect($token->name)->toBe('fleet-portal')
        ->and($token->abilities)->toBe(['vehicles:read'])
        ->and($token->expires_at->isBetween(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});

test('it refuses to issue a token for an inactive user', function () {
    $user = User::factory()->inactive()->create();

    $this->artisan('ims:issue-vehicle-api-token', [
        'email' => $user->email,
        'name' => 'fleet-portal',
    ])->assertFailed();

    expect($user->tokens()->exists())->toBeFalse();
});

test('it rotates a named token when replace is requested', function () {
    $user = User::factory()->create();
    $existingToken = $user->createToken('fleet-portal', ['vehicles:read'])->accessToken;

    $this->artisan('ims:issue-vehicle-api-token', [
        'email' => $user->email,
        'name' => 'fleet-portal',
        '--replace' => true,
    ])->assertSuccessful();

    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->firstOrFail()->getKey())->not->toBe($existingToken->getKey());
});

test('it revokes a named token without affecting other recipients', function () {
    $user = User::factory()->create();
    $user->createToken('fleet-portal', ['vehicles:read']);
    $user->createToken('reporting-system', ['vehicles:read']);

    $this->artisan('ims:revoke-vehicle-api-token', [
        'email' => $user->email,
        'name' => 'fleet-portal',
    ])->assertSuccessful();

    expect($user->tokens()->pluck('name')->all())->toBe(['reporting-system']);
});
