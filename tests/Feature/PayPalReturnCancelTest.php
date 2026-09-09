<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\PayPalOrder;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayPalReturnCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwnerWithOrder(): array
    {
        PlatformSetting::current()->update([
            'paypal_enabled' => true,
            'paypal_client_id' => 'cid',
            'paypal_client_secret' => 'secret',
        ]);

        $tenant = Tenant::factory()->create(['status' => TenantStatus::Trial]);
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 999, 'is_active' => true]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);
        $order = PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER1', 'status' => 'created',
        ]);

        return compact('tenant', 'owner', 'subscription', 'order');
    }

    public function test_a_successful_browser_return_captures_and_activates(): void
    {
        Mail::fake();
        ['owner' => $owner, 'tenant' => $tenant, 'order' => $order] = $this->makeOwnerWithOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response([
                'id' => 'ORDER1', 'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE1', 'status' => 'COMPLETED']]]]],
            ]),
        ]);

        $this->actingAs($owner)
            ->get('/app/billing/paypal/return?token=ORDER1')
            ->assertRedirect(route('app.billing'));

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
    }

    public function test_a_denied_capture_on_return_does_not_activate_and_shows_an_error(): void
    {
        ['owner' => $owner, 'tenant' => $tenant, 'order' => $order] = $this->makeOwnerWithOrder();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders/ORDER1/capture' => Http::response(['id' => 'ORDER1', 'status' => 'DECLINED']),
        ]);

        $response = $this->actingAs($owner)->get('/app/billing/paypal/return?token=ORDER1');

        $response->assertRedirect(route('app.billing'));
        $this->assertTrue(session()->has('billing_error'));
        $this->assertSame(TenantStatus::Trial, $tenant->fresh()->status);
    }

    public function test_cancelling_marks_the_order_cancelled_without_touching_the_subscription(): void
    {
        ['owner' => $owner, 'tenant' => $tenant, 'order' => $order] = $this->makeOwnerWithOrder();

        $this->actingAs($owner)
            ->get('/app/billing/paypal/cancel?token=ORDER1')
            ->assertRedirect(route('app.billing'));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(TenantStatus::Trial, $tenant->fresh()->status);
    }

    public function test_a_cashier_cannot_reach_the_billing_paypal_routes(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/billing/paypal/return?token=X')->assertForbidden();
        $this->actingAs($cashier)->get('/app/billing/paypal/cancel?token=X')->assertForbidden();
    }
}
