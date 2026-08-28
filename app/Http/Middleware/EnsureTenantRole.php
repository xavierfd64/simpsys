<?php

namespace App\Http\Middleware;

use App\Enums\TenantMembershipRole;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRole
{
    public function __construct(protected TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => TenantMembershipRole::from($role), $roles);

        if (! $this->tenantContext->hasRole(...$allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
