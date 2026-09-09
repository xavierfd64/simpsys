<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\PayPalOrder;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayPalOrderTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithOrder(string $status = 'created'): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);
        $order = PayPalOrder::create([
            'tenant_id' => $tenant->id, 'subscription_id' => $subscription->id, 'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly', 'amount' => 999, 'currency' => 'PHP',
            'paypal_order_id' => 'ORDER-VICTIM', 'status' => $status,
        ]);

        return compact('tenant', 'owner', 'order');
    }

    /**
     * PoC: an authenticated Tenant A owner, given (or guessing) Tenant B's
     * real PayPal order id, hits the cancel route directly — this must
     * not be able to touch an order that belongs to a different tenant.
     */
    public function test_a_tenant_cannot_cancel_another_tenants_pending_paypal_order(): void
    {
        ['order' => $victimOrder] = $this->makeTenantWithOrder();

        $attackerTenant = Tenant::factory()->create();
        $attacker = User::factory()->create();
        $attackerTenant->memberships()->create(['user_id' => $attacker->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($attacker)->get('/app/billing/paypal/cancel?token=ORDER-VICTIM');

        $this->assertSame('created', $victimOrder->fresh()->status);
    }

    /**
     * Same attack via the return route — must not be able to trigger
     * completion/capture attempts against another tenant's order.
     */
    public function test_a_tenant_cannot_trigger_completion_of_another_tenants_paypal_order(): void
    {
        ['order' => $victimOrder] = $this->makeTenantWithOrder();

        $attackerTenant = Tenant::factory()->create();
        $attacker = User::factory()->create();
        $attackerTenant->memberships()->create(['user_id' => $attacker->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($attacker)->get('/app/billing/paypal/return?token=ORDER-VICTIM');

        $this->assertSame('created', $victimOrder->fresh()->status);
    }

    public function test_a_tenant_can_still_cancel_their_own_order(): void
    {
        ['order' => $order, 'owner' => $owner] = $this->makeTenantWithOrder();

        $this->actingAs($owner)->get('/app/billing/paypal/cancel?token=ORDER-VICTIM');

        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
