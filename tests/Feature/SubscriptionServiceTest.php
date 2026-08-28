<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
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
}
