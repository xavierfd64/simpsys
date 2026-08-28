<?php

namespace Tests\Feature;

use App\Enums\ProductInventoryMovementType;
use App\Exceptions\InsufficientInventoryException;
use App\Models\Product;
use App\Services\ProductInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_stock_creates_a_movement_and_updates_quantity(): void
    {
        $product = Product::factory()->create();
        $service = new ProductInventoryService;

        $movement = $service->adjust($product, 50, ProductInventoryMovementType::StockAdded);

        $this->assertSame(50, $product->fresh()->currentStock());
        $this->assertSame(50, $movement->quantity_change);
        $this->assertSame(50, $movement->quantity_after);
    }

    public function test_deducting_more_than_available_throws_and_does_not_change_stock(): void
    {
        $product = Product::factory()->create();
        $service = new ProductInventoryService;
        $service->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        $this->expectException(InsufficientInventoryException::class);

        try {
            $service->adjust($product, -20, ProductInventoryMovementType::Sale);
        } finally {
            $this->assertSame(10, $product->fresh()->currentStock());
        }
    }

    public function test_stock_never_goes_negative_via_repeated_deductions(): void
    {
        $product = Product::factory()->create();
        $service = new ProductInventoryService;
        $service->adjust($product, 5, ProductInventoryMovementType::StockAdded);
        $service->adjust($product, -5, ProductInventoryMovementType::Sale);

        $this->assertSame(0, $product->fresh()->currentStock());

        $this->expectException(InsufficientInventoryException::class);
        $service->adjust($product, -1, ProductInventoryMovementType::Sale);
    }

    public function test_low_stock_is_flagged_at_or_below_threshold(): void
    {
        $product = Product::factory()->create(['low_stock_threshold' => 5]);
        $service = new ProductInventoryService;

        $service->adjust($product, 5, ProductInventoryMovementType::StockAdded);
        $this->assertTrue($product->fresh()->isLowStock());

        $service->adjust($product, 10, ProductInventoryMovementType::StockAdded);
        $this->assertFalse($product->fresh()->isLowStock());
    }
}
