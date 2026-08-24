<?php

namespace App\Providers;

use App\Contracts\HrisReferenceSource;
use App\Integrations\Hris\HrisApiClient;
use App\Models\HrisIntegrationSetting;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetBorrowing;
use App\Models\InventoryAssetCategory;
use App\Models\InventoryAssetCustodian;
use App\Models\InventoryClassCategory;
use App\Models\InventoryItem;
use App\Models\InventoryItemBatch;
use App\Models\InventoryItemStockOut;
use App\Models\InventoryItemStockOutAllocation;
use App\Models\InventoryMajorCategory;
use App\Models\InventorySeriesCategory;
use App\Models\User;
use App\Observers\AuditObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HrisReferenceSource::class, HrisApiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureAuditing();
    }

    protected function configureAuthorization(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('manage-integrations', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('view-audit-logs', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('manage-inventory', fn (User $user): bool => $user->canManageInventory());
    }

    protected function configureAuditing(): void
    {
        foreach ([
            User::class,
            HrisIntegrationSetting::class,
            InventoryItem::class,
            InventoryItemBatch::class,
            InventoryItemStockOut::class,
            InventoryItemStockOutAllocation::class,
            InventoryAsset::class,
            InventoryAssetCustodian::class,
            InventoryAssetBorrowing::class,
            InventoryMajorCategory::class,
            InventoryClassCategory::class,
            InventorySeriesCategory::class,
            InventoryAssetCategory::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
