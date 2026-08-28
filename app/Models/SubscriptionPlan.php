<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'monthly_price', 'yearly_price', 'user_limit', 'features', 'is_active', 'sort_order'])]
class SubscriptionPlan extends Model
{
    use HasFactory, HasUuid;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceFor(BillingPeriod $period): int
    {
        return $period === BillingPeriod::Yearly ? $this->yearly_price : $this->monthly_price;
    }
}
