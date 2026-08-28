<?php

namespace App\Providers;

use App\Services\TenantContext;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            $user = $request->user();

            if ($user?->is_platform_admin) {
                return route('admin.dashboard');
            }

            if ($user?->activeMembership()) {
                return route('app.dashboard');
            }

            return route('home');
        });
    }
}
