<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_a_new_business_and_is_logged_in(): void
    {
        $plan = SubscriptionPlan::factory()->create(['slug' => 'business', 'is_active' => true]);

        Livewire::test('auth.register')
            ->set('planSlug', $plan->slug)
            ->set('business_name', "Aling Nena's Turo-Turo")
            ->set('owner_name', 'Nena Reyes')
            ->set('email', 'nena@example.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertRedirect(route('app.dashboard'));

        $user = User::where('email', 'nena@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($plan->id, $user->activeMembership()->tenant->currentSubscription()->subscription_plan_id);
    }

    public function test_registration_requires_accepting_terms(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);

        Livewire::test('auth.register')
            ->set('business_name', 'Test Biz')
            ->set('owner_name', 'Test Owner')
            ->set('email', 'test@example.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', false)
            ->call('register')
            ->assertHasErrors('terms_accepted');

        $this->assertGuest();
    }

    public function test_register_page_preselects_the_plan_from_the_query_string(): void
    {
        $plan = SubscriptionPlan::factory()->create(['slug' => 'premium', 'is_active' => true, 'name' => 'Premium']);

        $this->get('/register?plan=premium')->assertSee('Premium plan selected.');
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        User::factory()->create(['email' => 'existing@example.test']);

        Livewire::test('auth.register')
            ->set('business_name', 'Test Biz')
            ->set('owner_name', 'Test Owner')
            ->set('email', 'existing@example.test')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('terms_accepted', true)
            ->call('register')
            ->assertHasErrors('email');
    }
}
