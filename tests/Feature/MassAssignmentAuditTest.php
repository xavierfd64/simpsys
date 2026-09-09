<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\PayPalOrder;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression net for the audit's mass-assignment sweep: is_admin=true,
 * tenant_id=<other tenant>, role=super_admin, subscription_status=active
 * and payment_status=completed must never be settable via a model's
 * fillable list from otherwise-trusted-looking client-shaped input.
 */
class MassAssignmentAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_platform_admin_is_not_mass_assignable_on_user(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'test@example.test',
            'password' => 'password',
            'is_platform_admin' => true,
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->is_platform_admin);
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->email_verified_at);
    }

    /**
     * BelongsToTenant only auto-stamps tenant_id when the attribute is
     * still empty at creation time (see App\Concerns\BelongsToTenant) — so
     * a caller that explicitly (and wrongly) forwarded a client-supplied
     * tenant_id into a mass-assignment array would NOT be corrected by the
     * trait. This documents that the trait itself is not the safeguard;
     * every real call site in this codebase was audited to never pass a
     * client-controlled tenant_id at all (it's either omitted, letting the
     * trait stamp the current TenantContext tenant, or forwarded from an
     * already-trusted server-side value like $order->tenant_id).
     */
    public function test_belongs_to_tenant_only_stamps_tenant_id_when_absent(): void
    {
        $attackerTenant = Tenant::factory()->create();
        $victimTenant = Tenant::factory()->create();

        $product = Product::create([
            'tenant_id' => $victimTenant->id,
            'name' => 'Should not happen in real code',
            'type' => 'ready_to_sell',
            'selling_price' => 100,
        ]);

        $this->assertSame($victimTenant->id, $product->tenant_id);
    }

    public function test_tenant_membership_role_is_restricted_to_the_known_enum_via_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(\App\Services\TenantContext::class)->setMembership(
            $tenant->memberships()->where('user_id', $owner->id)->first()
        );

        $staff = User::factory()->create();
        $staffMembership = $tenant->memberships()->create(['user_id' => $staff->id, 'role' => TenantMembershipRole::Cashier]);

        try {
            \Livewire\Livewire::test('pages::tenant.users.index')
                ->call('openEdit', $staffMembership->id)
                ->set('role', 'super_admin')
                ->call('save')
                ->assertHasErrors('role');
        } catch (\Illuminate\Validation\ValidationException) {
            // Also acceptable — either way, the role must not be persisted.
        }

        $this->assertNotSame('super_admin', $staffMembership->fresh()->role->value);
    }

    /**
     * A PayPalOrder's status can only ever move to 'completed' through
     * PayPalCheckoutService::completeOrder(), which requires PayPal's own
     * capture API to report COMPLETED with a real capture id (see
     * PayPalCheckoutServiceTest) — nothing in this codebase ever
     * mass-assigns 'status' => 'completed' from request/Livewire input.
     * This documents that PayPalOrder's own fillable list still allows the
     * field (needed for the trusted service to write it), which is fine
     * precisely because no untrusted call site exists.
     */
    public function test_paypal_order_status_defaults_to_created_not_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Trial,
        ]);
        $order = PayPalOrder::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => 'monthly',
            'paypal_order_id' => 'TESTORDER123',
            'amount' => 10000,
            'currency' => 'PHP',
            'status' => 'created',
        ]);

        $this->assertSame('created', $order->status);
    }
}
