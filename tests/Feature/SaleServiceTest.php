<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InsufficientPaymentException;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantWithCashierAndPaymentMethod(): array
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create(['is_enabled' => true]);

        return [$tenant, $cashier, $paymentMethod];
    }

    public function test_ready_to_sell_sale_deducts_inventory_and_creates_no_kitchen_order(): void
    {
        [$tenant, $cashier, $paymentMethod] = $this->makeTenantWithCashierAndPaymentMethod();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 50, ProductInventoryMovementType::StockAdded);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier,
            [['product_id' => $product->id, 'quantity' => 3]],
            OrderType::ToGo, $paymentMethod, 6000,
        );

        $this->assertSame(6000, $sale->total);
        $this->assertSame(0, $sale->change_amount);
        $this->assertSame(47, $product->fresh()->currentStock());
        $this->assertNull($sale->kitchenOrder);
        $this->assertSame(1, $sale->number);
    }

    public function test_made_to_order_sale_creates_a_kitchen_order_and_does_not_touch_inventory(): void
    {
        [$tenant, $cashier, $paymentMethod] = $this->makeTenantWithCashierAndPaymentMethod();
        $product = Product::factory()->madeToOrder()->for($tenant)->create(['selling_price' => 6000]);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier,
            [['product_id' => $product->id, 'quantity' => 2]],
            OrderType::DineIn, $paymentMethod, 12000,
        );

        $this->assertNotNull($sale->kitchenOrder);
        $this->assertSame(1, $sale->kitchenOrder->items->count());
        $this->assertSame(2, $sale->kitchenOrder->items->first()->quantity);
        $this->assertSame(0, $product->fresh()->currentStock());
    }

    public function test_mixed_cart_deducts_inventory_for_ready_to_sell_and_sends_only_made_to_order_to_the_kitchen(): void
    {
        [$tenant, $cashier, $paymentMethod] = $this->makeTenantWithCashierAndPaymentMethod();
        $fishball = Product::factory()->for($tenant)->create(['name' => 'Fishball', 'selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($fishball, 20, ProductInventoryMovementType::StockAdded);
        $milkTea = Product::factory()->madeToOrder()->for($tenant)->create(['name' => 'Milk Tea', 'selling_price' => 6000]);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier,
            [
                ['product_id' => $fishball->id, 'quantity' => 2],
                ['product_id' => $milkTea->id, 'quantity' => 1],
            ],
            OrderType::ToGo, $paymentMethod, 10000,
        );

        // One sale, one payment.
        $this->assertSame(1, Sale::count());
        $this->assertSame(10000, $sale->total);

        // Fishball inventory deducted.
        $this->assertSame(18, $fishball->fresh()->currentStock());

        // Kitchen order has only the milk tea line.
        $this->assertSame(1, $sale->kitchenOrder->items->count());
        $this->assertSame('Milk Tea', $sale->kitchenOrder->items->first()->product_name);
    }

    public function test_insufficient_inventory_rolls_back_the_entire_sale(): void
    {
        [$tenant, $cashier, $paymentMethod] = $this->makeTenantWithCashierAndPaymentMethod();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 1, ProductInventoryMovementType::StockAdded);

        $this->expectException(InsufficientInventoryException::class);

        try {
            app(SaleService::class)->recordSale(
                $tenant, $cashier,
                [['product_id' => $product->id, 'quantity' => 5]],
                OrderType::ToGo, $paymentMethod, 10000,
            );
        } finally {
            $this->assertSame(0, Sale::count());
            $this->assertSame(1, $product->fresh()->currentStock());
        }
    }

    public function test_insufficient_payment_is_rejected(): void
    {
        [$tenant, $cashier, $paymentMethod] = $this->makeTenantWithCashierAndPaymentMethod();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $this->expectException(InsufficientPaymentException::class);

        app(SaleService::class)->recordSale(
            $tenant, $cashier,
            [['product_id' => $product->id, 'quantity' => 2]],
            OrderType::ToGo, $paymentMethod, 1000,
        );
    }

    public function test_a_disabled_payment_method_cannot_be_used(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create(['is_enabled' => false]);
        $product = Product::factory()->for($tenant)->create(['selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $this->expectException(\InvalidArgumentException::class);

        app(SaleService::class)->recordSale(
            $tenant, $cashier,
            [['product_id' => $product->id, 'quantity' => 1]],
            OrderType::ToGo, $paymentMethod, 2000,
        );
    }

    public function test_sale_numbers_are_sequential_per_tenant(): void
    {
        [$tenant, $cashier, $paymentMethod] = $this->makeTenantWithCashierAndPaymentMethod();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 1000]);
        app(ProductInventoryService::class)->adjust($product, 100, ProductInventoryMovementType::StockAdded);

        $first = app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], null, $paymentMethod, 1000);
        $second = app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], null, $paymentMethod, 1000);

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
    }
}
