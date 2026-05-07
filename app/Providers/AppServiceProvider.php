<?php

namespace App\Providers;

use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\M365MailAdapter;
use App\Services\Integrations\S3Adapter;
use App\Services\TenantContext;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());
        $this->app->singleton(IntegrationManager::class, function () {
            return new IntegrationManager([
                new M365MailAdapter(),
                new S3Adapter(),
            ]);
        });

        // Register the permission middleware
        $this->app->singleton('permission', function ($app) {
            return new \Spatie\Permission\Middleware\PermissionMiddleware($app->make(\Spatie\Permission\PermissionRegistrar::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by(
                    ($request->input('employee_number') ?? $request->input('email') ?? 'guest')
                    .$request->ip()
                ),
            ];
        });

        RateLimiter::for('tenant-api', function (Request $request) {
            $tenantSlug = (string) $request->header('X-Tenant', 'default');

            return [
                Limit::perMinute(120)->by($tenantSlug . '|' . $request->ip()),
            ];
        });
    }
}
