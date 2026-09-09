<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two endpoints the audit's rate-limiting scope explicitly names had no
 * limiting of their own before this round: password-reset requests (an
 * attacker could otherwise cheaply flood many different victims' inboxes,
 * or hammer the endpoint's own DB/SMTP work, from one source — Laravel's
 * own per-email 60s throttle doesn't cover either of those) and PayPal
 * order creation (an authenticated tenant could otherwise spam calls to
 * this installation's own configured PayPal API credentials/quota).
 */
class RateLimitingSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('password-reset|127.0.0.1');
        parent::tearDown();
    }

    public function test_password_reset_requests_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test('auth.forgot-password')
                ->set('email', "user{$i}@example.test")
                ->call('sendResetLink');
        }

        Livewire::test('auth.forgot-password')
            ->set('email', 'onemore@example.test')
            ->call('sendResetLink')
            ->assertHasErrors('email');
    }

    public function test_paypal_order_creation_is_rate_limited_per_user(): void
    {
        PlatformSetting::current()->update([
            'paypal_client_id' => 'test-client-id',
            'paypal_client_secret' => 'test-secret',
            'paypal_mode' => 'sandbox',
        ]);

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create();
        Subscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
        ]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('paypal-order|'.$owner->id, 600);
        }

        $component = Livewire::test('pages::tenant.billing')
            ->set('selected_plan_id', (string) $plan->id)
            ->set('selected_period', 'monthly')
            ->call('payWithPayPal');

        $component->assertSet('paypal_error', 'Too many payment attempts. Please wait a few minutes and try again.');
    }
}
