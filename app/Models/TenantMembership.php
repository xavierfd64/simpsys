<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantMembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'user_id', 'role', 'status'])]
class TenantMembership extends Model
{
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'role' => TenantMembershipRole::class,
            'status' => TenantMembershipStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantMembershipStatus::Active;
    }
}
