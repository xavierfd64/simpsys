<?php

namespace App\Services;

use App\Enums\KitchenOrderStatus;
use App\Enums\ProductInventoryMovementType;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Models\AuditLog;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoidSaleService
{
    public function __construct(protected ProductInventoryService $productInventory) {}

    /**
     * Void a completed sale: restore ready-to-sell inventory that was
     * deducted for it, cancel its kitchen order if still in progress, and
     * record an audit log entry. Already-voided sales, and completed
     * kitchen orders (food already prepared/served), are left alone.
     */
    public function void(Sale $sale, User $actor, string $reason): Sale
    {
        if ($sale->status === SaleStatus::Voided) {
            throw new RuntimeException('This sale has already been voided.');
        }

        return DB::transaction(function () use ($sale, $actor, $reason) {
            foreach ($sale->items()->with('product')->get() as $item) {
                if ($item->product && $item->product->type === ProductType::ReadyToSell) {
                    $this->productInventory->adjust(
                        $item->product,
                        $item->quantity,
                        ProductInventoryMovementType::VoidReversal,
                        "Void of Sale {$sale->displayNumber()}",
                        $actor,
                    );
                }
            }

            $kitchenOrder = $sale->kitchenOrder;

            if ($kitchenOrder && $kitchenOrder->status !== KitchenOrderStatus::Completed) {
                $kitchenOrder->update([
                    'status' => KitchenOrderStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);
            }

            $sale->update([
                'status' => SaleStatus::Voided,
                'voided_by' => $actor->id,
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            AuditLog::record('sale.voided', [
                'tenant_id' => $sale->tenant_id,
                'user_id' => $actor->id,
                'subject_type' => Sale::class,
                'subject_id' => $sale->id,
                'description' => "Voided sale {$sale->displayNumber()}: {$reason}",
            ]);

            return $sale->fresh();
        });
    }
}
