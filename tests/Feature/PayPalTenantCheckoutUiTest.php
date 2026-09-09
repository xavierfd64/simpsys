<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PayPalTenantCheckoutUiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 999, 'yearly_price' => 9999, 'is_active' => true]);
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);

        return compact('tenant', 'owner', 'plan', 'subscription', 'membership');
    }

    public function test_manual_payment_option_is_shown_when_enabled_and_paypal_is_not_configured(): void
    {
        ['owner' => $owner] = $this->makeOwner();

        $this->actingAs($owner)
            ->get('/app/billing')
            ->assertSee('Manual / Fund Transfer')
            ->assertDontSee('Pay with PayPal');
    }

    public function test_paypal_option_only_appears_once_fully_configured(): void
    {
        ['owner' => $owner] = $this->makeOwner();
        PlatformSetting::current()->update(['paypal_enabled' => true]); // enabled but no credentials yet

        $this->actingAs($owner)->get('/app/billing')->assertDontSee('Pay with PayPal');

        PlatformSetting::current()->update(['paypal_client_id' => 'cid', 'paypal_client_secret' => 'secret']);

        $this->actingAs($owner)->get('/app/billing')->assertSee('PayPal');
    }

    public function test_disabling_manual_payment_hides_it_from_the_tenant(): void
    {
        ['owner' => $owner] = $this->makeOwner();
        PlatformSetting::current()->update(['manual_payment_enabled' => false]);

        $this->actingAs($owner)->get('/app/billing')->assertDontSee('Manual / Fund Transfer');
    }

    public function test_paying_with_paypal_redirects_the_browser_to_the_approve_url(): void
    {
        ['owner' => $owner, 'plan' => $plan, 'membership' => $membership] = $this->makeOwner();
        PlatformSetting::current()->update([
            'paypal_enabled' => true, 'paypal_client_id' => 'cid', 'paypal_client_secret' => 'secret',
        ]);

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER1',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve/ORDER1']],
            ]),
        ]);

        $this->actingAs($owner);

        // Livewire::test() mounts the component directly, bypassing route
        // middleware, so TenantContext must be seeded the way IdentifyTenant
        // would seed it on a real request before calling an action that
        // reads it (see BusinessSettingsTest for the same pattern).
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.billing')
            ->set('selected_plan_id', (string) $plan->id)
            ->set('selected_period', 'monthly')
            ->set('payment_method', 'paypal')
            ->call('payWithPayPal')
            ->assertRedirect('https://paypal.test/approve/ORDER1');

        $this->assertDatabaseHas('paypal_orders', ['paypal_order_id' => 'ORDER1', 'amount' => 999]);
    }

    public function test_a_cashier_cannot_reach_the_billing_page(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/billing')->assertForbidden();
    }
}
