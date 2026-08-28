<?php

namespace Tests\Feature;

use App\Enums\KitchenOrderStatus;
use App\Enums\OrderType;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\KitchenService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class KitchenServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeKitchenOrder()
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->madeToOrder()->for($tenant)->create(['selling_price' => 6000]);

        $sale = app(SaleService::class)->recordSale(
            $tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 6000,
        );

        return $sale->kitchenOrder;
    }

    public function test_advance_moves_through_the_full_pipeline_with_timestamps(): void
    {
        $order = $this->makeKitchenOrder();
        $service = new KitchenService;

        $order = $service->advance($order);
        $this->assertSame(KitchenOrderStatus::Preparing, $order->status);
        $this->assertNotNull($order->started_at);

        $order = $service->advance($order);
        $this->assertSame(KitchenOrderStatus::Ready, $order->status);
        $this->assertNotNull($order->ready_at);

        $order = $service->advance($order);
        $this->assertSame(KitchenOrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_a_completed_order_cannot_be_advanced_further(): void
    {
        $order = $this->makeKitchenOrder();
        $service = new KitchenService;
        $order = $service->advance($service->advance($service->advance($order)));

        $this->expectException(RuntimeException::class);
        $service->advance($order);
    }

    public function test_a_cancelled_order_cannot_be_advanced(): void
    {
        $order = $this->makeKitchenOrder();
        $order->update(['status' => KitchenOrderStatus::Cancelled]);

        $this->expectException(RuntimeException::class);
        (new KitchenService)->advance($order);
    }
}
