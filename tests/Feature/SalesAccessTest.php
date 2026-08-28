<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Enums\TenantMembershipRole;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use App\Services\SaleService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_only_see_their_own_sales(): void
    {
        $tenant = Tenant::factory()->create();
        $cashierA = User::factory()->create();
        $cashierB = User::factory()->create();
        $membershipA = $tenant->memberships()->create(['user_id' => $cashierA->id, 'role' => TenantMembershipRole::Cashier]);
        $tenant->memberships()->create(['user_id' => $cashierB->id, 'role' => TenantMembershipRole::Cashier]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 1000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $saleA = app(SaleService::class)->recordSale($tenant, $cashierA, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 1000);
        $saleB = app(SaleService::class)->recordSale($tenant, $cashierB, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 1000);

        $this->actingAs($cashierA);
        app(TenantContext::class)->setMembership($membershipA);

        Livewire::test('pages::tenant.sales.index')
            ->assertSee($saleA->displayNumber())
            ->assertDontSee($saleB->displayNumber());
    }

    public function test_cashier_cannot_view_another_cashiers_sale_detail(): void
    {
        $tenant = Tenant::factory()->create();
        $cashierA = User::factory()->create();
        $cashierB = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashierA->id, 'role' => TenantMembershipRole::Cashier]);
        $tenant->memberships()->create(['user_id' => $cashierB->id, 'role' => TenantMembershipRole::Cashier]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 1000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $saleB = app(SaleService::class)->recordSale($tenant, $cashierB, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 1000);

        $this->actingAs($cashierA)
            ->get(route('app.sales.show', $saleB))
            ->assertForbidden();
    }

    public function test_owner_sees_all_sales_and_can_void(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $ownerMembership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 1000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $sale = app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 1000);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($ownerMembership);

        Livewire::test('pages::tenant.sales.show', ['sale' => $sale])
            ->set('void_reason', 'Test void')
            ->call('voidSale')
            ->assertHasNoErrors();

        $this->assertSame('voided', $sale->fresh()->status->value);
    }

    public function test_cashier_cannot_void_even_via_direct_component_call(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 1000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $sale = app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 1000);

        $this->actingAs($cashier);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.sales.show', ['sale' => $sale])
            ->set('void_reason', 'Trying to void')
            ->call('voidSale')
            ->assertForbidden();

        $this->assertSame('completed', $sale->fresh()->status->value);
    }
}
