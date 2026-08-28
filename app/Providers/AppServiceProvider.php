<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\IdentifyTenant;
use App\Services\TenantContext;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // Livewire only replays a hardcoded allowlist of framework middleware
        // (auth, SubstituteBindings, ...) on subsequent component action
        // requests (wire:click/wire:submit hit /livewire/update, not the
        // original page route) — custom middleware is skipped unless
        // registered here. Without this, TenantContext is populated on the
        // initial page load but empty on every action call that follows.
        Livewire::addPersistentMiddleware([
            IdentifyTenant::class,
            EnsureTenantRole::class,
            EnsurePlatformAdmin::class,
        ]);

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
