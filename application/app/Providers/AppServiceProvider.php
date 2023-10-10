<?php

namespace App\Providers;

use App\Services\NecTm\NecTmApiClient;
use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\ApiClients\TvClassifierApiClient;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use KeycloakAuthGuard\Services\CachedServiceAccountJwtRetriever;
use KeycloakAuthGuard\Services\Decoders\JwtTokenDecoder;
use KeycloakAuthGuard\Services\RealmJwkRetrieverInterface;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetriever;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
