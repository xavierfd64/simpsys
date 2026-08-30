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

        $preferredTenantId = $request->session()->get('current_tenant_id');
        $membership = $user?->activeMembership($preferredTenantId);

        if (! $membership) {
            $suspendedMembership = $user?->memberships()
                ->whereHas('tenant', fn ($q) => $q->whereIn('status', ['suspended', 'cancelled']))
                ->first();

            if ($suspendedMembership) {
                abort(403, 'This business account has been suspended. Please contact support.');
            }

            $pendingBranch = $user?->memberships()
                ->whereHas('tenant', fn ($q) => $q->where('branch_status', 'pending_approval'))
                ->first();

            if ($pendingBranch && $user->memberships()->count() === 1) {
                abort(403, 'This branch is pending Platform Admin approval.');
            }

            abort(403, 'No active business was found for this account.');
        }

        // Keep the session's "current branch" in sync with what actually
        // resolved (e.g. a stale/invalid session value falls back to the
        // first usable membership above — remember that choice instead of
        // re-resolving it on every request).
        $request->session()->put('current_tenant_id', $membership->tenant_id);

        $this->tenantContext->setMembership($membership);

        return $next($request);
    }
}
