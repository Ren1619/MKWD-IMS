<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateHrisIntegrationRequest;
use App\Models\HrisIntegrationSetting;
use App\Services\HrisEndpointGuard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HrisIntegrationController extends Controller
{
    public function __construct(private HrisEndpointGuard $endpointGuard) {}

    public function edit(): Response
    {
        $setting = HrisIntegrationSetting::query()->find(HrisIntegrationSetting::SINGLETON_ID);
        $environmentBaseUrl = config('services.hris.base_url');
        $employeesPath = config('services.hris.employees_path');

        return Inertia::render('settings/hris-integration', [
            'baseUrl' => $setting === null
                ? (is_string($environmentBaseUrl) ? $environmentBaseUrl : '')
                : $setting->base_url,
            'employeesPath' => is_string($employeesPath) ? $employeesPath : '',
            'usingDatabaseOverride' => $setting !== null,
            'allowedHosts' => $this->endpointGuard->allowedHosts(),
        ]);
    }

    public function update(UpdateHrisIntegrationRequest $request): RedirectResponse
    {
        HrisIntegrationSetting::query()->updateOrCreate(
            ['id' => HrisIntegrationSetting::SINGLETON_ID],
            [
                'base_url' => $this->endpointGuard->normalize($request->string('base_url')->toString()),
                'updated_by' => $request->user()->getKey(),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'HRIS employee API setting updated.',
        ]);

        return redirect()->route('hris-integration.edit');
    }
}
