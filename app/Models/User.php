<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasUuid;
use App\Enums\TenantMembershipRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * Every membership on a tenant this user can currently log into and
     * operate — i.e. Tenant::isOperational() — ordered by tenant id so the
     * business's root tenant (created first) is the default choice.
     *
     * @return Collection<int, TenantMembership>
     */
    public function usableMemberships(): Collection
    {
        return $this->memberships()
            ->with('tenant.parent')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($membership) => $membership->tenant?->isOperational())
            ->sortBy('tenant_id')
            ->values();
    }

    /**
     * The membership to use for this request. When a user belongs to more
     * than one branch, $preferredTenantId (the session-remembered "current
     * branch") is honored if it's still valid; otherwise falls back to the
     * first usable membership.
     */
    public function activeMembership(?int $preferredTenantId = null): ?TenantMembership
    {
        $memberships = $this->usableMemberships();

        if ($preferredTenantId) {
            $preferred = $memberships->firstWhere('tenant_id', $preferredTenantId);

            if ($preferred) {
                return $preferred;
            }
        }

        return $memberships->first();
    }

    /**
     * Where this user should land after logging in, based on their role.
     * Only owners see the business dashboard; cashiers go straight to the
     * POS and kitchen staff straight to the kitchen board, since those are
     * the only areas their role can access.
     */
    public function homeRouteName(): string
    {
        if ($this->is_platform_admin) {
            return 'admin.dashboard';
        }

        $role = $this->activeMembership()?->role;

        return match ($role) {
            TenantMembershipRole::Cashier => 'app.pos',
            TenantMembershipRole::KitchenStaff => 'app.kitchen',
            TenantMembershipRole::Owner => 'app.dashboard',
            default => 'home',
        };
    }
}
