<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('the IMS login page is displayed at the home route', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('canResetPassword', Features::enabled(Features::resetPasswords()))
            ->where('name', config('app.name'))
        );
});

test('authenticated users are redirected from home to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirectToRoute('dashboard');
});
