<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

/** @return array{rendered: array<string, string>, pages: list<string>, destinations: list<array{page: string, url: string}>} */
function breadcrumbNavigationEvidence(): array
{
    static $evidence;

    if ($evidence === null) {
        $result = Process::path(base_path())->timeout(90)->run(['node', base_path('tests/BreadcrumbNavigation.mjs')]);
        expect($result->successful())->toBeTrue($result->errorOutput());
        $evidence = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
    }

    return $evidence;
}

test('parent breadcrumbs remain links even when they are the only or last item', function () {
    $rendered = breadcrumbNavigationEvidence()['rendered'];
    expect($rendered['parent'])->toContain('href="/inventory/returns"')->not->toContain('aria-current="page"');
    expect($rendered['nested'])->toContain('href="/inventory/items"', 'href="/inventory/warehouse"')->not->toContain('aria-current="page"');
});

test('current pages and destinationless labels do not become broken breadcrumb links', function () {
    $rendered = breadcrumbNavigationEvidence()['rendered'];
    foreach (['current', 'absolute'] as $case) {
        expect($rendered[$case])->toContain('aria-current="page"')->not->toContain('href=');
    }
    expect($rendered['title'])->toContain('href="/inventory/counts"', 'aria-current="page"', 'Count 7');
    expect($rendered['group'])->toContain('<span>Administration</span>', 'aria-current="page"')->not->toContain('href=');
});

test('every page breadcrumb destination is a registered reachable GET route', function () {
    $evidence = breadcrumbNavigationEvidence();
    expect($evidence['pages'])->not->toBeEmpty()->and($evidence['destinations'])->not->toBeEmpty();
    $this->actingAs(User::factory()->superAdmin()->create())->withSession(['auth.password_confirmed_at' => time()]);

    foreach (collect($evidence['destinations'])->unique('url') as $destination) {
        $request = Request::create($destination['url'], 'GET');
        $route = Route::getRoutes()->match($request);
        expect($route->methods())->toContain('GET');
        $this->get($destination['url'])->assertSuccessful();
    }
});
