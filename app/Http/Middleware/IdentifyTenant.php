<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant strictly from the authenticated user's
 * server-side membership. Tenant identity is never accepted from the
 * request (route params, query string, headers, session).
 */
class IdentifyTenant
{
    public function __construct(protected TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $membership = $user?->activeMembership();

        if (! $membership) {
            abort(403, 'No active business was found for this account.');
        }

        $this->tenantContext->setMembership($membership);

        return $next($request);
    }
}
