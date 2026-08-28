<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\SubscriptionPlan;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_creates_a_fully_wired_tenant(): void
    {
        $plan = SubscriptionPlan::factory()->create(['slug' => 'business', 'is_active' => true]);

        $tenant = app(TenantOnboardingService::class)->register(
            "Aling Nena's Turo-Turo",
            'Nena Reyes',
            'nena@example.test',
            'password123',
            $plan,
        );

        $this->assertDatabaseHas('users', ['email' => 'nena@example.test']);
        $this->assertNotNull($tenant->owner());
        $this->assertSame(TenantMembershipRole::Owner, $tenant->owner()->role);
        $this->assertNotNull($tenant->settings);
        $this->assertSame(1, $tenant->paymentMethods()->count());

        $subscription = $tenant->currentSubscription();
        $this->assertNotNull($subscription);
        $this->assertSame(SubscriptionStatus::Trial, $subscription->status);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
    }

    public function test_duplicate_business_names_get_unique_slugs(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        $service = app(TenantOnboardingService::class);

        $first = $service->register('Fishball House', 'Owner One', 'one@example.test', 'password123');
        $second = $service->register('Fishball House', 'Owner Two', 'two@example.test', 'password123');

        $this->assertNotSame($first->slug, $second->slug);
    }
}
