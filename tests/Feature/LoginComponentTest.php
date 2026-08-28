<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_redirected_to_the_tenant_dashboard_on_successful_login(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['password' => 'correct-password']);
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => TenantMembershipRole::Owner]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_cashier_is_redirected_straight_to_pos_on_successful_login(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['password' => 'correct-password']);
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => TenantMembershipRole::Cashier]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('app.pos'));
    }

    public function test_kitchen_staff_is_redirected_straight_to_the_kitchen_board_on_successful_login(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['password' => 'correct-password']);
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => TenantMembershipRole::KitchenStaff]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('app.kitchen'));
    }

    public function test_platform_admin_is_redirected_to_the_admin_dashboard_on_successful_login(): void
    {
        $admin = User::factory()->create(['password' => 'correct-password', 'is_platform_admin' => true]);

        Livewire::test('auth.login')
            ->set('email', $admin->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_wrong_password_fails_with_a_validation_error(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_without_a_business_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
