<?php

namespace App\Services;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\TenantMembership;

/**
 * Holds the current tenant for the request, resolved exclusively from the
 * authenticated user's server-side membership record. Never populate this
 * from request input (route params, query strings, headers) — tenant
 * identity must never be trusted from the client.
 */
class TenantContext
{
    protected ?TenantMembership $membership = null;

    protected bool $resolved = false;

    public function setMembership(?TenantMembership $membership): void
    {
        $this->membership = $membership;
        $this->resolved = true;
    }

    public function hasTenant(): bool
    {
        return $this->membership !== null;
    }

    public function membership(): ?TenantMembership
    {
        return $this->membership;
    }

    public function tenant(): ?Tenant
    {
        return $this->membership?->tenant;
    }

    public function role(): ?TenantMembershipRole
    {
        return $this->membership?->role;
    }

    public function hasRole(TenantMembershipRole ...$roles): bool
    {
        return $this->membership !== null && in_array($this->membership->role, $roles, true);
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }
}
