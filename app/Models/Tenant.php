<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\BranchStatus;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'slug', 'timezone', 'logo_path', 'status', 'trial_ends_at',
    'parent_tenant_id', 'branch_code', 'branch_address', 'branch_contact_number',
    'branch_contact_email', 'manager_user_id', 'branch_status',
    'branch_rejection_reason', 'branch_approved_at', 'branch_approved_by',
])]
class Tenant extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'branch_status' => BranchStatus::class,
            'branch_approved_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * The business's root tenant this branch belongs to, or null if this
     * tenant itself is the root (see isBranch()).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'parent_tenant_id');
    }

    /**
     * Branches under this tenant, when this tenant is itself a business
     * root. Empty for a branch (branches never have their own sub-branches
     * — this is deliberately a flat, two-level hierarchy).
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Tenant::class, 'parent_tenant_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function branchApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'branch_approved_by');
    }

    public function isBranch(): bool
    {
        return $this->parent_tenant_id !== null;
    }

    /**
     * The business this tenant belongs to — itself if it's the root, or
     * its parent if it's a branch.
     */
    public function businessRoot(): self
    {
        return $this->isBranch() ? $this->parent : $this;
    }

    /**
     * Every tenant row in this business, root first: the root tenant
     * itself plus all of its branches.
     */
    public function allBranches(): Collection
    {
        $root = $this->businessRoot();

        return collect([$root])->concat($root->branches);
    }

    /**
     * Whether this tenant can currently be logged into / operated on,
     * combining its own status with (for a branch) the branch approval
     * status and the parent business's own standing — a branch must not
     * stay reachable if the business's subscription itself has lapsed.
     */
    /**
     * A unique, URL-safe slug derived from $name, disambiguated with a
     * numeric suffix on collision (including against soft-deleted rows, so
     * a re-registration can't collide with a deleted tenant's old slug).
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 1;

        while (static::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function isOperational(): bool
    {
        if (in_array($this->status, [TenantStatus::Suspended, TenantStatus::Cancelled], true)) {
            return false;
        }

        if ($this->isBranch()) {
            if ($this->branch_status !== BranchStatus::Active) {
                return false;
            }

            $root = $this->parent;

            if ($root && in_array($root->status, [TenantStatus::Suspended, TenantStatus::Cancelled, TenantStatus::Expired], true)) {
                return false;
            }
        }

        return true;
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
