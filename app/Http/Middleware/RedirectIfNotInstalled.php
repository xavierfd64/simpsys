<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
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
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') || $request->is('up')) {
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
