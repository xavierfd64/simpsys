<?php

namespace App\Services;

use App\Enums\SupplyInventoryMovementType;
use App\Exceptions\InsufficientSupplyException;
use App\Models\Supply;
use App\Models\SupplyInventory;
use App\Models\SupplyInventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupplyInventoryService
{
    /**
     * Apply a signed quantity change to a supply's stock, recording a
     * movement. Negative stock is never allowed. Mirrors
     * ProductInventoryService but with decimal quantities, since supplies
     * are measured in units like kg/liters rather than whole pieces.
     */
    public function adjust(
        Supply $supply,
        float $delta,
        SupplyInventoryMovementType $type,
        ?string $note = null,
        ?User $actor = null,
    ): SupplyInventoryMovement {
        return DB::transaction(function () use ($supply, $delta, $type, $note, $actor) {
            $inventory = SupplyInventory::query()
                ->lockForUpdate()
                ->firstOrCreate(['supply_id' => $supply->id], ['quantity' => 0]);

            $newQuantity = round($inventory->quantity + $delta, 2);

            if ($newQuantity < 0) {
                throw InsufficientSupplyException::forSupply($supply->name, $inventory->quantity, abs($delta));
            }

            $inventory->update(['quantity' => $newQuantity]);

            return SupplyInventoryMovement::create([
                'tenant_id' => $supply->tenant_id,
                'supply_id' => $supply->id,
                'user_id' => $actor?->id,
                'type' => $type,
                'quantity_change' => $delta,
                'quantity_after' => $newQuantity,
                'note' => $note,
            ]);
        });
    }
}
