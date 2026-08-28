<?php

namespace App\Services;

use App\Enums\ProductInventoryMovementType;
use App\Exceptions\InsufficientInventoryException;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductInventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductInventoryService
{
    /**
     * Apply a signed quantity change to a product's sellable inventory,
     * recording a movement. Negative inventory is never allowed. Locks the
     * inventory row for the duration of the transaction so concurrent POS
     * sales cannot oversell the same stock.
     */
    public function adjust(
        Product $product,
        int $delta,
        ProductInventoryMovementType $type,
        ?string $note = null,
        ?User $actor = null,
    ): ProductInventoryMovement {
        return DB::transaction(function () use ($product, $delta, $type, $note, $actor) {
            $inventory = ProductInventory::query()
                ->lockForUpdate()
                ->firstOrCreate(['product_id' => $product->id], ['quantity' => 0]);

            $newQuantity = $inventory->quantity + $delta;

            if ($newQuantity < 0) {
                throw InsufficientInventoryException::forProduct($product->name, $inventory->quantity, abs($delta));
            }

            $inventory->update(['quantity' => $newQuantity]);

            return ProductInventoryMovement::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'user_id' => $actor?->id,
                'type' => $type,
                'quantity_change' => $delta,
                'quantity_after' => $newQuantity,
                'note' => $note,
            ]);
        });
    }
}
