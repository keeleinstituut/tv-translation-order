<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::withoutMiddleware([StartSession::class])
                ->group(function () {
                    Route::get('/healthz/startup', [\App\Http\Controllers\HealthCheckController::class, 'startup'])->name('healthz.startup');
                    Route::get('/healthz/ready', [\App\Http\Controllers\HealthCheckController::class, 'ready'])->name('healthz.ready');
                    Route::get('/healthz/live', [\App\Http\Controllers\HealthCheckController::class, 'live'])->name('healthz.live');
                });

            Route::middleware(['api', 'auth:api'])
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            //            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
