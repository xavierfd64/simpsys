<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'subscription_plan_id', 'billing_period', 'status',
    'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at',
])]
class Subscription extends Model
{
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isInGoodStanding(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Trial, SubscriptionStatus::Active], true);
    }
}
