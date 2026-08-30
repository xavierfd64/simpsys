<?php

namespace App\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Defense-in-depth for tenant isolation: every query against a model using
 * this trait is automatically filtered to the current TenantContext's
 * tenant, and new records are auto-stamped with it. This never replaces the
 * `tenant` + `role` middleware — it exists so a forgotten `where('tenant_id', ...)`
 * in a controller/component cannot leak another tenant's data. When no
 * tenant is resolved (artisan commands, seeders, tests without a request),
 * the scope is a no-op so those contexts keep working normally.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $context = app(TenantContext::class);

                if ($context->hasTenant()) {
                    $model->tenant_id = $context->tenant()->id;
                }
            }
        });
    }

    /**
     * Explicit, opt-in escape hatch for legitimate cross-branch access
     * (creating a resource for a branch other than the current session's
     * active one, or an owner's multi-branch dashboard aggregating several
     * branches at once) — bypasses the current-tenant global scope for
     * this query only, in favor of the given tenant(s). Everywhere else
     * keeps going through the normal scoped query as before.
     */
    public static function forTenant(Tenant $tenant): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenant->id);
    }

    /**
     * @param  iterable<int>  $tenantIds
     */
    public static function forTenants(iterable $tenantIds): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class)->whereIn('tenant_id', $tenantIds);
    }
}
