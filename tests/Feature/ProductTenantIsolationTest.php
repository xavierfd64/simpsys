<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_scoped_to_the_current_tenant_automatically(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Product::factory()->for($tenantA)->create(['name' => 'Tenant A Fishball']);
        Product::factory()->for($tenantB)->create(['name' => 'Tenant B Siomai']);

        app(TenantContext::class)->setMembership(
            $tenantA->memberships()->create([
                'user_id' => User::factory()->create()->id,
                'role' => TenantMembershipRole::Owner,
            ])
        );

        $visible = Product::query()->pluck('name');

        $this->assertTrue($visible->contains('Tenant A Fishball'));
        $this->assertFalse($visible->contains('Tenant B Siomai'));
    }

    public function test_owner_cannot_view_another_tenants_product_via_url(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $ownerA = User::factory()->create();
        $tenantA->memberships()->create(['user_id' => $ownerA->id, 'role' => TenantMembershipRole::Owner]);

        $productB = Product::factory()->for($tenantB)->create();

        $this->actingAs($ownerA)
            ->get(route('app.products.show', $productB))
            ->assertNotFound();
    }

    public function test_cashier_cannot_manage_products(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/products')->assertForbidden();
    }
}
