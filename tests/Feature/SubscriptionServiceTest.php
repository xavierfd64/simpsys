<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSubscription(): Subscription
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        return $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function test_extend_pushes_out_the_period_end_and_activates(): void
    {
        $subscription = $this->makeSubscription();
        $service = new SubscriptionService;

        $result = $service->extend($subscription, 30);

        $this->assertSame(SubscriptionStatus::Active, $result->status);
        $this->assertTrue($result->current_period_end->isAfter(now()->addDays(29)));
    }

    public function test_suspend_and_cancel_set_the_expected_status(): void
    {
        $subscription = $this->makeSubscription();
        $service = new SubscriptionService;

        $suspended = $service->suspend($subscription);
        $this->assertSame(SubscriptionStatus::Suspended, $suspended->status);

        $cancelled = $service->cancel($subscription->fresh());
        $this->assertSame(SubscriptionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_recording_a_payment_creates_a_billing_record_and_renews(): void
    {
        $subscription = $this->makeSubscription();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $service = new SubscriptionService;

        $payment = $service->recordPayment($subscription, 599, 'GCash', 'REF123', 'Paid via GCash', $admin);

        $this->assertDatabaseHas('billing_payments', [
            'id' => $payment->id,
            'amount' => 599,
            'payment_method_label' => 'GCash',
            'reference' => 'REF123',
        ]);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    /**
     * The exact bug reported in production: a business activated after a
     * verified payment kept showing "Trial" everywhere that reads
     * Tenant::status, because only the Subscription row was ever updated.
     */
    public function test_recording_a_payment_activates_the_tenants_own_status_too_not_just_the_subscription(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        app(SubscriptionService::class)->recordPayment($subscription, 599, 'GCash', 'REF123', null, $admin);

        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
    }

    public function test_activate_extend_and_renew_all_sync_the_tenants_status_to_active(): void
    {
        $service = app(SubscriptionService::class);

        $tenant = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);
        $service->activate($subscription);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);

        $tenant2 = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $subscription2 = $tenant2->subscriptions()->create([
            'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);
        $service->extend($subscription2, 30);
        $this->assertSame(TenantStatus::Active, $tenant2->fresh()->status);

        $tenant3 = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $subscription3 = $tenant3->subscriptions()->create([
            'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);
        $service->renew($subscription3);
        $this->assertSame(TenantStatus::Active, $tenant3->fresh()->status);
    }

    public function test_expire_suspend_and_cancel_all_sync_the_tenants_status(): void
    {
        $service = app(SubscriptionService::class);

        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => SubscriptionPlan::factory()->create()->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Active,
        ]);

        $service->expire($subscription);
        $this->assertSame(TenantStatus::Expired, $tenant->fresh()->status);

        $service->suspend($subscription->fresh());
        $this->assertSame(TenantStatus::Suspended, $tenant->fresh()->status);

        $service->cancel($subscription->fresh());
        $this->assertSame(TenantStatus::Cancelled, $tenant->fresh()->status);
    }
}
