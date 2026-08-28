<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trial', 'active'])
            ->latest('id')
            ->first();
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function supplyCategories(): HasMany
    {
        return $this->hasMany(SupplyCategory::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function kitchenOrders(): HasMany
    {
        return $this->hasMany(KitchenOrder::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingPayment::class);
    }

    /**
     * UTC datetime bounds for a single calendar day *in this tenant's
     * timezone*. Timestamp columns are always stored in UTC, so comparing
     * them against a tenant-local "today" requires converting the
     * boundaries, not the stored value — `whereDate('created_at', $today)`
     * silently compares against the wrong calendar day whenever local time
     * and UTC fall on different dates (i.e. every non-UTC timezone, for
     * part of the day). Use with whereBetween(), not whereDate().
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function localDayBoundsUtc(string $date): array
    {
        return [
            Carbon::parse($date, $this->timezone)->startOfDay()->utc(),
            Carbon::parse($date, $this->timezone)->endOfDay()->utc(),
        ];
    }

    /**
     * UTC datetime bounds spanning a tenant-local date range (inclusive).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function localRangeBoundsUtc(string $fromDate, string $toDate): array
    {
        return [
            Carbon::parse($fromDate, $this->timezone)->startOfDay()->utc(),
            Carbon::parse($toDate, $this->timezone)->endOfDay()->utc(),
        ];
    }
}
