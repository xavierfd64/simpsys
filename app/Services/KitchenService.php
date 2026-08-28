<?php

namespace App\Services;

use App\Enums\KitchenOrderStatus;
use App\Models\KitchenOrder;
use RuntimeException;

class KitchenService
{
    /**
     * Advance a kitchen order to its next status in the
     * pending → preparing → ready → completed pipeline, stamping the
     * matching timestamp. Cancelled orders (from a voided sale) and
     * already-completed orders have no next status.
     */
    public function advance(KitchenOrder $order): KitchenOrder
    {
        $next = $order->status->next();

        if (! $next) {
            throw new RuntimeException("Kitchen order {$order->displayNumber()} cannot be advanced further.");
        }

        $timestampColumn = match ($next) {
            KitchenOrderStatus::Preparing => 'started_at',
            KitchenOrderStatus::Ready => 'ready_at',
            KitchenOrderStatus::Completed => 'completed_at',
        };

        $order->update([
            'status' => $next,
            $timestampColumn => now(),
        ]);

        return $order->fresh();
    }
}
