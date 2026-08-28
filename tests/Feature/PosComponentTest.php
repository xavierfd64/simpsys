<?php

namespace Tests\Feature;

use App\Enums\ProductInventoryMovementType;
use App\Enums\TenantMembershipRole;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsCashier(): array
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier);
        app(TenantContext::class)->setMembership($membership);

        return [$tenant, $cashier];
    }

    public function test_cashier_can_add_products_and_complete_a_sale(): void
    {
        [$tenant] = $this->actingAsCashier();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();

        Livewire::test('pages::tenant.pos.index')
            ->call('addToCart', $product->id)
            ->call('addToCart', $product->id)
            ->assertSet('cart', [$product->id => 2])
            ->call('openCheckout')
            ->set('payment_method_id', $paymentMethod->id)
            ->set('amount_received', '40.00')
            ->call('confirmSale')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $this->assertSame(8, $product->fresh()->currentStock());
        $this->assertDatabaseHas('sales', ['tenant_id' => $tenant->id, 'total' => 4000]);
    }

    public function test_cannot_add_more_than_available_stock_to_the_cart(): void
    {
        [$tenant] = $this->actingAsCashier();
        $product = Product::factory()->for($tenant)->create();
        app(ProductInventoryService::class)->adjust($product, 1, ProductInventoryMovementType::StockAdded);

        Livewire::test('pages::tenant.pos.index')
            ->call('addToCart', $product->id)
            ->assertSet('cart', [$product->id => 1])
            ->call('addToCart', $product->id)
            ->assertHasErrors('cart')
            ->assertSet('cart', [$product->id => 1]);
    }

    public function test_kitchen_staff_cannot_access_pos(): void
    {
        $tenant = Tenant::factory()->create();
        $kitchenStaff = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $kitchenStaff->id, 'role' => TenantMembershipRole::KitchenStaff]);

        $this->actingAs($kitchenStaff)->get('/app/pos')->assertForbidden();
    }
}
