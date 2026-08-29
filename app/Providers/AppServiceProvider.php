<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\IdentifyTenant;
use App\Services\TenantContext;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        // Some shared-hosting MySQL/MariaDB configurations (older InnoDB row
        // format, or a host-imposed cap — one target host reports "max key
        // length is 1000 bytes") can't index a plain utf8mb4 VARCHAR(255):
        // 255 chars * 4 bytes/char = 1020 bytes, over the limit. This is
        // Laravel's own long-standing fix — every $table->string(...) call
        // with no explicit length (including in Laravel's own stock
        // migrations: users.email, sessions.id, jobs.id/uuid,
        // cache/cache_locks.key, password_reset_tokens.email) drops to 191
        // chars (764 bytes) instead, comfortably under any of these limits,
        // without touching Unicode support or any migration file directly.
        Schema::defaultStringLength(191);

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

            return $user ? route($user->homeRouteName()) : route('home');
        });
    }
}
