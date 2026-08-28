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

    public function activeMembership(): ?TenantMembership
    {
        return $this->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->whereNotIn('status', ['cancelled']))
            ->first();
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
