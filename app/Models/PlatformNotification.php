<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['audience', 'tenant_id', 'title', 'message', 'sent_by'])]
class PlatformNotification extends Model
{
    use HasUuid;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function scopeForTenant($query, Tenant $tenant)
    {
        return $query->where(function ($q) use ($tenant) {
            $q->where('audience', 'all')
                ->orWhere('audience', $tenant->status->value)
                ->orWhere(fn ($q2) => $q2->where('audience', 'specific')->where('tenant_id', $tenant->id));
        });
    }
}
