<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'timezone', 'logo_path', 'status', 'trial_ends_at'])]
class Tenant extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function owner(): ?TenantMembership
    {
        return $this->memberships()
            ->where('role', 'owner')
            ->first();
    }
}
