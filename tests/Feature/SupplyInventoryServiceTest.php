<?php

namespace Tests\Feature;

use App\Enums\SupplyInventoryMovementType;
use App\Exceptions\InsufficientSupplyException;
use App\Models\Supply;
use App\Services\SupplyInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_stock_updates_quantity_with_decimal_precision(): void
    {
        $supply = Supply::factory()->create(['unit' => 'kg']);
        $service = new SupplyInventoryService;

        $service->adjust($supply, 12.5, SupplyInventoryMovementType::StockAdded);

        $this->assertSame(12.5, $supply->fresh()->currentStock());
    }

    public function test_manual_usage_deducts_and_rejects_going_negative(): void
    {
        $supply = Supply::factory()->create();
        $service = new SupplyInventoryService;
        $service->adjust($supply, 5, SupplyInventoryMovementType::StockAdded);

        $service->adjust($supply, -3, SupplyInventoryMovementType::ManualUsage);
        $this->assertSame(2.0, $supply->fresh()->currentStock());

        $this->expectException(InsufficientSupplyException::class);
        $service->adjust($supply, -10, SupplyInventoryMovementType::ManualUsage);
    }

    public function test_low_stock_flag_respects_threshold(): void
    {
        $supply = Supply::factory()->create(['low_stock_threshold' => 2]);
        $service = new SupplyInventoryService;

        $service->adjust($supply, 1.5, SupplyInventoryMovementType::StockAdded);
        $this->assertTrue($supply->fresh()->isLowStock());

        $service->adjust($supply, 5, SupplyInventoryMovementType::StockAdded);
        $this->assertFalse($supply->fresh()->isLowStock());
    }
}
