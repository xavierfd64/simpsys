<?php

namespace Tests\Feature;

use App\Enums\KitchenOrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use App\Services\SaleService;
use App\Services\VoidSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VoidSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_voiding_a_sale_restores_ready_to_sell_inventory(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 20, ProductInventoryMovementType::StockAdded);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier, [['product_id' => $product->id, 'quantity' => 5]], OrderType::ToGo, $paymentMethod, 10000,
        );

        $this->assertSame(15, $product->fresh()->currentStock());

        app(VoidSaleService::class)->void($sale, $owner, 'Customer complaint');

        $this->assertSame(20, $product->fresh()->currentStock());
        $this->assertSame('voided', $sale->fresh()->status->value);
        $this->assertSame('Customer complaint', $sale->fresh()->void_reason);
    }

    public function test_voiding_cancels_a_pending_kitchen_order(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->madeToOrder()->for($tenant)->create(['selling_price' => 6000]);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::DineIn, $paymentMethod, 6000,
        );

        app(VoidSaleService::class)->void($sale, $owner, 'Wrong order');

        $this->assertSame(KitchenOrderStatus::Cancelled, $sale->kitchenOrder->fresh()->status);
    }

    public function test_voiding_leaves_a_completed_kitchen_order_alone(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->madeToOrder()->for($tenant)->create(['selling_price' => 6000]);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::DineIn, $paymentMethod, 6000,
        );
        $sale->kitchenOrder->update(['status' => KitchenOrderStatus::Completed]);

        app(VoidSaleService::class)->void($sale, $owner, 'Late void');

        $this->assertSame(KitchenOrderStatus::Completed, $sale->kitchenOrder->fresh()->status);
    }

    public function test_cannot_void_an_already_voided_sale(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 20, ProductInventoryMovementType::StockAdded);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 2000,
        );
        app(VoidSaleService::class)->void($sale, $owner, 'First void');

        $this->expectException(RuntimeException::class);
        app(VoidSaleService::class)->void($sale->fresh(), $owner, 'Second void');
    }

    public function test_voiding_creates_an_audit_log_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 20, ProductInventoryMovementType::StockAdded);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 2000,
        );
        app(VoidSaleService::class)->void($sale, $owner, 'Audit check');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sale.voided',
            'user_id' => $owner->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
