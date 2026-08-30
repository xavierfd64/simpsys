<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['audience', 'tenant_id', 'title', 'message', 'sent_by', 'is_active', 'published_at', 'expires_at'])]
class PlatformNotification extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PlatformNotificationRead::class);
    }

    public function isPublished(): bool
    {
        return $this->is_active
            && $this->published_at !== null
            && $this->published_at->isPast()
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    /**
     * Every tenant currently matched by this notice's audience — used both
     * to compute "total recipients" for the admin's read-tracking view and
     * (via scopeForTenant below) to decide whether one specific tenant
     * should see it.
     */
    public function matchingTenants()
    {
        return Tenant::query()
            ->when($this->audience === 'specific', fn ($q) => $q->where('id', $this->tenant_id))
            ->when($this->audience !== 'specific' && $this->audience !== 'all', fn ($q) => $q->where('status', $this->audience))
            ->get();
    }

    public function scopeForTenant($query, Tenant $tenant)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($q) use ($tenant) {
                $q->where('audience', 'all')
                    ->orWhere('audience', $tenant->status->value)
                    ->orWhere(fn ($q2) => $q2->where('audience', 'specific')->where('tenant_id', $tenant->id));
            });
    }

    /**
     * Records that $tenant has seen this notice — a no-op if already
     * marked read, since the banner re-renders on every dashboard visit.
     */
    public function markReadFor(Tenant $tenant, ?User $user): void
    {
        $this->reads()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['user_id' => $user?->id, 'read_at' => now()],
        );
    }
}
