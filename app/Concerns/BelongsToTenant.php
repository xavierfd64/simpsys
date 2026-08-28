<?php

namespace App\Concerns;

use App\Models\Scopes\TenantScope;
use App\Services\TenantContext;

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
}
