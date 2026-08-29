<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * WordPress-style install gate: every request is redirected to /install
 * until the wizard has run, and /install itself becomes unreachable again
 * once it has — matching the spec's "no manual .env/SQL/Artisan step, and
 * no accidental reinstallation" requirements.
 *
 * The testing environment is exempt: feature tests manage their own
 * database via RefreshDatabase and never produce an installed.lock file, so
 * enforcing this here would redirect every test request to /install.
 *
 * Livewire's own AJAX endpoint is also exempt. It's registered directly on
 * the `web` middleware group (not under the `/install` prefix) at a path
 * Livewire 4 derives per-installation from APP_KEY — `/livewire-{hash}/
 * update`, not the fixed `/livewire/update` a naive path check would
 * expect — so path-matching it here is a losing game; `Livewire::
 * isLivewireRequest()` checks the `X-Livewire` header Livewire's own JS
 * client sends instead, which is stable regardless of that hash. Without
 * this exemption, every `wire:click`/`wire:submit` on the installer wizard
 * itself — whose initial GET at `/install` legitimately passed this gate —
 * hits that endpoint on its *next* request, which this middleware would
 * otherwise see as "not `/install*`, not installed yet" and redirect away.
 * Livewire's JS then can't parse a redirect as a component response, so
 * from the browser it just looks like the Continue button reloads back to
 * the requirements page instead of advancing.
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') || $request->is('up') || Livewire::isLivewireRequest()) {
            return $next($request);
        }

        $installed = app(InstallerService::class)->isInstalled();
        $onInstaller = $request->is('install') || $request->is('install/*');

        if ($onInstaller) {
            return $installed ? redirect('/') : $next($request);
        }

        return $installed ? $next($request) : redirect('/install');
    }
}
