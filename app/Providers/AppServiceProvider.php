<?php

namespace App\Providers;

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\IdentifyTenant;
use App\Services\BillingReminderService;
use App\Services\PayPalClient;
use App\Services\TenantContext;
use App\Support\MailConfigurator;
use App\Support\OpportunisticScheduler;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        // Without this binding, container auto-resolution would build
        // PayPalClient's PlatformSetting constructor argument as a bare
        // `new PlatformSetting` (no attributes at all) rather than the
        // actual saved settings row — every credential would read blank.
        $this->app->bind(PayPalClient::class, fn () => PayPalClient::fromSettings());
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

        // config/session.php's 'secure' option defaults to whatever
        // SESSION_SECURE_COOKIE is in .env — null if the admin never set
        // it, which PHP treats as "not secure" (the cookie would be sent
        // over plain HTTP too, even on a site that's actually served over
        // HTTPS). This app's own installer never asks for or writes that
        // variable, and the "no manual .env editing" deployment promise
        // means most real installs would silently ship with an
        // unnecessarily insecure session cookie on an HTTPS site. Checked
        // via config(), not env() directly — env() always returns null
        // outside config files once `php artisan config:cache` has run (a
        // common production step on shared hosting), which would make this
        // wrongly override even a deliberate explicit 'false'; config()
        // reflects the real value baked in at cache time either way. Only
        // override when the admin hasn't explicitly configured a value (a
        // deliberate 'false' — e.g. a local HTTP-only dev/staging box —
        // must still be respected), and derive it from the actual request
        // instead: secure exactly when this request itself arrived over
        // HTTPS, which naturally also survives a plain-HTTP site later
        // getting an SSL certificate with no config change needed.
        if (config('session.secure') === null) {
            config(['session.secure' => request()->isSecure()]);
        }

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

        // Lets the Platform Admin configure SMTP from the UI (no .env edit,
        // no server restart) — same "hot-swap runtime config" pattern the
        // installer already uses for the database connection.
        MailConfigurator::applyFromDatabase();

        // Shared hosting can't be asked to configure a system cron job, so
        // daily billing-reminder/expiry work instead piggybacks on the
        // first real request of the day (see OpportunisticScheduler).
        // Skipped in tests — see BillingReminderServiceTest, which calls
        // the service directly instead of relying on this to fire.
        if (! app()->environment('testing')) {
            try {
                if (Schema::hasTable('subscriptions')) {
                    OpportunisticScheduler::runDaily('billing-reminders', function () {
                        $service = app(BillingReminderService::class);
                        $service->sendDueReminders();
                        $service->expireLapsedSubscriptions();
                    });
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
