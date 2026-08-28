<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_kitchen_staff_can_access_the_kitchen_board(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $staff->id, 'role' => TenantMembershipRole::KitchenStaff]);

        $this->actingAs($staff)->get('/app/kitchen')->assertOk();
    }

    public function test_cashier_cannot_access_the_kitchen_board(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/kitchen')->assertForbidden();
    }

    public function test_kitchen_staff_cannot_access_pos_or_products(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $staff->id, 'role' => TenantMembershipRole::KitchenStaff]);

        $this->actingAs($staff)->get('/app/pos')->assertForbidden();
        $this->actingAs($staff)->get('/app/products')->assertForbidden();
    }
}
