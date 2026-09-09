<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * config/session.php's 'secure' option is env-driven and null by default —
 * PHP treats a null 'secure' flag on a cookie as "not secure", so an admin
 * who never set SESSION_SECURE_COOKIE (the installer never asks for or
 * writes it, and manual .env editing is explicitly something this app's
 * deployment model avoids) would silently ship a session cookie usable
 * over plain HTTP even on a real HTTPS site. AppServiceProvider::boot()
 * derives it from the actual request instead when unconfigured.
 *
 * boot() already ran once during this test's own application bootstrap
 * (in setUp(), before the test body), so exercising its behavior for a
 * *different* config/request combination means invoking it directly with a
 * crafted request bound into the container first — the same pattern this
 * project already uses for RedirectIfNotInstalled's real branches (see
 * InstallerTest), since both depend on the container-bound request() at
 * the moment they run, not a parameter passed in.
 */
class SessionCookieSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function boot(string $url): void
    {
        $this->app->instance('request', Request::create($url));
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_session_cookie_is_marked_secure_when_the_admin_never_configured_it_and_the_request_is_https(): void
    {
        config(['session.secure' => null]);

        $this->boot('https://example.test/login');

        $this->assertTrue(config('session.secure'));
    }

    public function test_session_cookie_is_not_marked_secure_over_plain_http_when_unconfigured(): void
    {
        config(['session.secure' => null]);

        $this->boot('http://example.test/login');

        $this->assertFalse(config('session.secure'));
    }

    public function test_an_explicit_false_configuration_is_never_overridden_even_on_an_https_request(): void
    {
        config(['session.secure' => false]);

        $this->boot('https://example.test/login');

        $this->assertFalse(config('session.secure'));
    }
}
