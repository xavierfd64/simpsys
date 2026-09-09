<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\BillingPayment;
use App\Models\PayPalOrder;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PayPalCheckoutService;
use App\Support\PayPalException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayPalCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function configurePayPal(): void
    {
        PlatformSetting::current()->update([
            'paypal_enabled' => true,
            'paypal_environment' => 'sandbox',
            'paypal_client_id' => 'cid',
            'paypal_client_secret' => 'secret',
            'paypal_webhook_id' => 'WH-1',
            'paypal_currency' => 'PHP',
        ]);
    }

    /**
     * @return array{tenant: Tenant, subscription: Subscription, plan: SubscriptionPlan, owner: User}
     */
    protected function makeTenantWithSubscription(int $monthlyPrice = 999): array
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => $monthlyPrice, 'yearly_price' => $monthlyPrice * 10, 'is_active' => true]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(3),
        ]);

        return compact('tenant', 'subscription', 'plan', 'owner');
    }

    public function test_create_order_computes_the_amount_server_side_from_the_plan_never_from_a_client_value(): void
    {
        $this->configurePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER1',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve']],
            ]),
        ]);

        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription(999);

        $result = app(PayPalCheckoutService::class)->createOrder(
            $tenant, $subscription, $plan, BillingPeriod::Monthly,
            'https://app.test/return', 'https://app.test/cancel',
        );

        $this->assertSame('https://paypal.test/approve', $result['approve_url']);
        $order = $result['order'];
        $this->assertSame(999, $order->amount);
        $this->assertSame('PHP', $order->currency);
        $this->assertSame('created', $order->status);
        $this->assertDatabaseHas('paypal_orders', ['paypal_order_id' => 'ORDER1', 'tenant_id' => $tenant->id]);
    }

    public function test_create_order_refuses_when_paypal_is_not_configured(): void
    {
        // paypal_enabled left false (default) — deliberately not calling configurePayPal().
        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription();

        $this->expectException(PayPalException::class);
        app(PayPalCheckoutService::class)->createOrder(
            $tenant, $subscription, $plan, BillingPeriod::Monthly, 'https://app.test/return', 'https://app.test/cancel',
        );
    }

    /**
     * The exact end-to-end promise: PayPal must confirm the capture
     * genuinely completed before anything in BizManager changes — this
     * activates the subscription only via SubscriptionService's own
     * recordPayment()/renew() path, same as a manually-recorded payment.
     */
    public function test_completing_an_order_with_a_completed_capture_activates_the_subscription_and_records_a_payment(): void
    {
        Mail::fake();
        $this->configurePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1',
                'status' => 'COMPLETED',
                'payer' => ['email_address' => 'payer@example.test'],
                'purchase_units' => [[
                    'payments' => ['captures' => [['id' => 'CAPTURE1', 'status' => 'COMPLETED']]],
                ]],
            ]),
        ]);

        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription(999);

        $order = PayPalOrder::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'amount' => 999,
            'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1',
            'status' => 'created',
        ]);

        $result = app(PayPalCheckoutService::class)->completeOrder('ORDER1');

        $this->assertTrue($result['success']);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('CAPTURE1', $order->fresh()->paypal_capture_id);

        $this->assertDatabaseHas('billing_payments', [
            'subscription_id' => $subscription->id,
            'amount' => 999,
            'payment_method_label' => 'PayPal',
            'paypal_order_id' => 'ORDER1',
            'paypal_capture_id' => 'CAPTURE1',
            'paypal_payer_email' => 'payer@example.test',
        ]);
    }

    public function test_completing_an_already_completed_order_is_idempotent_and_never_double_captures(): void
    {
        Mail::fake();
        $this->configurePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1',
                'status' => 'COMPLETED',
                'payer' => ['email_address' => 'payer@example.test'],
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE1', 'status' => 'COMPLETED']]]]],
            ]),
        ]);

        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription(999);
        PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1', 'status' => 'created',
        ]);

        $service = app(PayPalCheckoutService::class);
        $service->completeOrder('ORDER1');
        $service->completeOrder('ORDER1');
        $service->completeOrder('ORDER1');

        Http::assertSentCount(2); // 1 oauth + 1 capture — the capture endpoint is never hit again once completed.
        $this->assertSame(1, BillingPayment::where('paypal_order_id', 'ORDER1')->count());
    }

    public function test_a_denied_capture_does_not_activate_the_subscription(): void
    {
        $this->configurePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1',
                'status' => 'DECLINED',
            ]),
        ]);

        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription();
        PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1', 'status' => 'created',
        ]);

        $result = app(PayPalCheckoutService::class)->completeOrder('ORDER1');

        $this->assertFalse($result['success']);
        $this->assertSame(TenantStatus::Trial, $tenant->fresh()->status);
        $this->assertSame('failed', $result['order']->status);
        $this->assertDatabaseMissing('billing_payments', ['paypal_order_id' => 'ORDER1']);
    }

    public function test_completing_an_unknown_order_throws(): void
    {
        $this->configurePayPal();

        $this->expectException(PayPalException::class);
        app(PayPalCheckoutService::class)->completeOrder('NO-SUCH-ORDER');
    }

    public function test_mark_cancelled_only_affects_a_still_pending_order(): void
    {
        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription();
        $order = PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1', 'status' => 'created',
        ]);

        app(PayPalCheckoutService::class)->markCancelled('ORDER1');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_mark_denied_never_overrides_an_already_completed_order(): void
    {
        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $plan] = $this->makeTenantWithSubscription();
        $order = PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1', 'status' => 'completed', 'paypal_capture_id' => 'CAPTURE1',
        ]);

        app(PayPalCheckoutService::class)->markDenied('ORDER1');

        $this->assertSame('completed', $order->fresh()->status);
    }

    /**
     * Buying a different plan than the one currently on the subscription
     * (an upgrade, not just a same-plan renewal) must switch the plan too,
     * once payment is confirmed — not just extend the old plan's period.
     */
    public function test_completing_an_order_for_a_different_plan_switches_the_subscription_to_it(): void
    {
        Mail::fake();
        $this->configurePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE1', 'status' => 'COMPLETED']]]]],
            ]),
        ]);

        ['tenant' => $tenant, 'subscription' => $subscription, 'plan' => $oldPlan] = $this->makeTenantWithSubscription(999);
        $newPlan = SubscriptionPlan::factory()->create(['monthly_price' => 1999, 'is_active' => true]);

        PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $newPlan->id,
            'billing_period' => 'monthly', 'amount' => 1999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1', 'status' => 'created',
        ]);

        app(PayPalCheckoutService::class)->completeOrder('ORDER1');

        $this->assertSame($newPlan->id, $subscription->fresh()->subscription_plan_id);
    }

    /**
     * Two tenants' orders must never cross-contaminate — resolving purely
     * by the unique paypal_order_id, an order can only ever touch the one
     * subscription it was created for.
     */
    public function test_completing_one_tenants_order_never_touches_another_tenants_subscription(): void
    {
        Mail::fake();
        $this->configurePayPal();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER-A/capture' => Http::response([
                'id' => 'ORDER-A', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-A', 'status' => 'COMPLETED']]]]],
            ]),
        ]);

        ['tenant' => $tenantA, 'subscription' => $subA, 'plan' => $planA] = $this->makeTenantWithSubscription();
        ['tenant' => $tenantB, 'subscription' => $subB] = $this->makeTenantWithSubscription();

        PayPalOrder::create([
            'tenant_id' => $tenantA->id, 'subscription_id' => $subA->id, 'subscription_plan_id' => $planA->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER-A', 'status' => 'created',
        ]);

        app(PayPalCheckoutService::class)->completeOrder('ORDER-A');

        $this->assertSame(TenantStatus::Active, $tenantA->fresh()->status);
        $this->assertSame(TenantStatus::Trial, $tenantB->fresh()->status);
        $this->assertSame(SubscriptionStatus::Trial, $subB->fresh()->status);
    }
}
