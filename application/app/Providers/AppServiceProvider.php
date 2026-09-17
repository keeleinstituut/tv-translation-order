<?php

namespace App\Providers;

use App\Http\Middleware\EnsureJwtBelongsToServiceAccountWithCattoAuthorizationRole;
use App\Repositories\Calendar\PriceVendorLanguageCoverageRepository;
use App\Repositories\Calendar\VendorLanguageCoverageRepositoryInterface;
use App\Services\Calendar\CalendarSettingsResolver;
use App\Services\CattoApiClient;
use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\ApiClients\TvClassifierApiClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TvAuthorizationApiClient::class, function (Application $app) {
            return new TvAuthorizationApiClient(
                $app->make(ServiceAccountJwtRetrieverInterface::class)
            );
        });

        $this->app->bind(TvClassifierApiClient::class, function (Application $app) {
            return new TvClassifierApiClient(
                $app->make(ServiceAccountJwtRetrieverInterface::class)
            );
        });

        $this->app->bind(CattoApiClient::class, function (Application $app) {
            return new CattoApiClient(
                $app->make(ServiceAccountJwtRetrieverInterface::class)
            );
        });

        $this->app->singleton(CalendarSettingsResolver::class);

        $this->app->singleton(
            VendorLanguageCoverageRepositoryInterface::class,
            PriceVendorLanguageCoverageRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::aliasMiddleware(
            'service-account-with-catto-authorization-role',
            EnsureJwtBelongsToServiceAccountWithCattoAuthorizationRole::class
        );
    }
}
