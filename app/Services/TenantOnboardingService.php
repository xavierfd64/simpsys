<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantOnboardingService
{
    public const TRIAL_DAYS = 14;

    /**
     * Create a new tenant, its owner user, and a trial subscription in one
     * atomic operation. Partial failure must never leave an orphaned tenant
     * or user behind.
     */
    public function register(string $businessName, string $ownerName, string $email, string $password, ?SubscriptionPlan $plan = null): Tenant
    {
        return DB::transaction(function () use ($businessName, $ownerName, $email, $password, $plan) {
            $trialEndsAt = now()->addDays(self::TRIAL_DAYS);

            $tenant = Tenant::create([
                'name' => $businessName,
                'slug' => $this->uniqueSlug($businessName),
                'timezone' => 'Asia/Manila',
                'status' => TenantStatus::Trial,
                'trial_ends_at' => $trialEndsAt,
            ]);

            $owner = User::create([
                'name' => $ownerName,
                'email' => $email,
                'password' => $password,
                'email_verified_at' => now(),
            ]);

            $tenant->memberships()->create([
                'user_id' => $owner->id,
                'role' => TenantMembershipRole::Owner,
            ]);

            $tenant->settings()->create([]);

            $tenant->paymentMethods()->create([
                'name' => 'Cash',
                'is_enabled' => true,
                'sort_order' => 0,
            ]);

            $plan ??= SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->firstOrFail();

            $tenant->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'billing_period' => BillingPeriod::Monthly,
                'status' => SubscriptionStatus::Trial,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
            ]);

            return $tenant;
        });
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
