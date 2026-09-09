<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\BillingPayment;
use App\Models\PayPalOrder;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function configurePayPal(): void
    {
        PlatformSetting::current()->update([
            'paypal_enabled' => true,
            'paypal_client_id' => 'cid',
            'paypal_client_secret' => 'secret',
            'paypal_webhook_id' => 'WH-1',
            'paypal_currency' => 'PHP',
        ]);
    }

    protected function makeOrder(): PayPalOrder
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 999, 'is_active' => true]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);

        return PayPalOrder::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'amount' => 999,
            'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1',
            'status' => 'created',
        ]);
    }

    protected function webhookHeaders(): array
    {
        return [
            'Paypal-Transmission-Id' => 't1',
            'Paypal-Transmission-Time' => now()->toIso8601String(),
            'Paypal-Cert-Url' => 'https://api.paypal.com/cert',
            'Paypal-Auth-Algo' => 'SHA256withRSA',
            'Paypal-Transmission-Sig' => 'sig',
        ];
    }

    public function test_a_capture_completed_event_with_a_verified_signature_activates_the_subscription(): void
    {
        Mail::fake();
        $this->configurePayPal();
        $order = $this->makeOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE1', 'status' => 'COMPLETED']]]]],
            ]),
        ]);

        $this->postJson('/webhooks/paypal', [
            'id' => 'EVT-1',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER1']]],
        ], $this->webhookHeaders())->assertOk();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(TenantStatus::Active, $order->fresh()->tenant->status);
        $this->assertDatabaseHas('billing_payments', ['paypal_order_id' => 'ORDER1']);
    }

    public function test_an_unverified_signature_is_rejected_and_never_processed(): void
    {
        $this->configurePayPal();
        $order = $this->makeOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);

        $this->postJson('/webhooks/paypal', [
            'id' => 'EVT-2',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER1']]],
        ], $this->webhookHeaders())->assertStatus(400);

        $this->assertSame('created', $order->fresh()->status);
        $this->assertSame(TenantStatus::Trial, $order->fresh()->tenant->status);
    }

    /**
     * PayPal may redeliver the same event — the second delivery must be a
     * complete no-op, not a second payment/activation.
     */
    public function test_a_duplicate_webhook_event_is_only_processed_once(): void
    {
        Mail::fake();
        $this->configurePayPal();
        $order = $this->makeOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE1', 'status' => 'COMPLETED']]]]],
            ]),
        ]);

        $payload = [
            'id' => 'EVT-3',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER1']]],
        ];

        $this->postJson('/webhooks/paypal', $payload, $this->webhookHeaders())->assertOk();
        $this->postJson('/webhooks/paypal', $payload, $this->webhookHeaders())->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, BillingPayment::where('paypal_order_id', 'ORDER1')->count());
    }

    public function test_a_pending_capture_event_never_activates_anything(): void
    {
        $this->configurePayPal();
        $order = $this->makeOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $this->postJson('/webhooks/paypal', [
            'id' => 'EVT-4',
            'event_type' => 'PAYMENT.CAPTURE.PENDING',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER1']]],
        ], $this->webhookHeaders())->assertOk();

        $this->assertSame('created', $order->fresh()->status);
        $this->assertSame(TenantStatus::Trial, $order->fresh()->tenant->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/capture'));
    }

    public function test_a_denied_capture_event_marks_the_order_denied_without_activating(): void
    {
        $this->configurePayPal();
        $order = $this->makeOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $this->postJson('/webhooks/paypal', [
            'id' => 'EVT-5',
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => ['supplementary_data' => ['related_ids' => ['order_id' => 'ORDER1']]],
        ], $this->webhookHeaders())->assertOk();

        $this->assertSame('denied', $order->fresh()->status);
        $this->assertSame(TenantStatus::Trial, $order->fresh()->tenant->status);
    }

    public function test_a_reversed_approval_event_marks_the_order_denied(): void
    {
        $this->configurePayPal();
        $order = $this->makeOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $this->postJson('/webhooks/paypal', [
            'id' => 'EVT-6',
            'event_type' => 'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
            'resource' => ['id' => 'ORDER1'],
        ], $this->webhookHeaders())->assertOk();

        $this->assertSame('denied', $order->fresh()->status);
    }

    public function test_a_malformed_payload_is_ignored_without_error(): void
    {
        $this->postJson('/webhooks/paypal', ['foo' => 'bar'])->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_a_webhook_for_an_unknown_order_is_acknowledged_without_crashing(): void
    {
        $this->configurePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $this->postJson('/webhooks/paypal', [
            'id' => 'EVT-7',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'NO-SUCH-ORDER'],
        ], $this->webhookHeaders())->assertOk();
    }
}
