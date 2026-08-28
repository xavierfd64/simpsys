<?php

namespace App\Services;

use App\Enums\KitchenOrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Exceptions\InsufficientPaymentException;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SaleService
{
    public function __construct(protected ProductInventoryService $productInventory) {}

    /**
     * Record a POS sale in a single atomic operation: create the sale and
     * its line items, deduct sellable inventory for ready-to-sell products,
     * and create one kitchen order (with its items) if the cart contains
     * any made-to-order products. If any step fails — most importantly,
     * insufficient inventory — everything rolls back and no sale is
     * recorded, per the spec's POS transaction rule.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $cartItems
     */
    public function recordSale(
        Tenant $tenant,
        User $cashier,
        array $cartItems,
        ?OrderType $orderType,
        PaymentMethod $paymentMethod,
        int $amountReceived,
    ): Sale {
        if (empty($cartItems)) {
            throw new InvalidArgumentException('Cannot record a sale with an empty cart.');
        }

        if ($paymentMethod->tenant_id !== $tenant->id || ! $paymentMethod->is_enabled) {
            throw new InvalidArgumentException('Selected payment method is not available for this business.');
        }

        return DB::transaction(function () use ($tenant, $cashier, $cartItems, $orderType, $paymentMethod, $amountReceived) {
            $productIds = array_column($cartItems, 'product_id');
            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

            $total = 0;
            $lines = [];

            foreach ($cartItems as $item) {
                $product = $products->get($item['product_id']);

                if (! $product || ! $product->is_active) {
                    throw new RuntimeException('One of the selected products is no longer available.');
                }

                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    continue;
                }

                $lineTotal = $product->selling_price * $quantity;
                $total += $lineTotal;
                $lines[] = ['product' => $product, 'quantity' => $quantity, 'line_total' => $lineTotal];
            }

            if (empty($lines)) {
                throw new InvalidArgumentException('Cannot record a sale with an empty cart.');
            }

            if ($amountReceived < $total) {
                throw InsufficientPaymentException::for($total, $amountReceived);
            }

            $number = ($tenant->sales()->lockForUpdate()->max('number') ?? 0) + 1;

            $sale = Sale::create([
                'tenant_id' => $tenant->id,
                'number' => $number,
                'cashier_id' => $cashier->id,
                'order_type' => $orderType,
                'payment_method_id' => $paymentMethod->id,
                'payment_method_name' => $paymentMethod->name,
                'total' => $total,
                'amount_received' => $amountReceived,
                'change_amount' => $amountReceived - $total,
                'status' => SaleStatus::Completed,
            ]);

            $kitchenItems = [];

            foreach ($lines as $line) {
                $product = $line['product'];

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->selling_price,
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                if ($product->type === ProductType::ReadyToSell) {
                    $this->productInventory->adjust(
                        $product,
                        -$line['quantity'],
                        ProductInventoryMovementType::Sale,
                        "Sale {$sale->displayNumber()}",
                        $cashier,
                    );
                } else {
                    $kitchenItems[] = $line;
                }
            }

            if (! empty($kitchenItems)) {
                $kitchenNumber = ($tenant->kitchenOrders()->lockForUpdate()->max('number') ?? 0) + 1;

                $kitchenOrder = $sale->kitchenOrder()->create([
                    'tenant_id' => $tenant->id,
                    'number' => $kitchenNumber,
                    'order_type' => $orderType,
                    'status' => KitchenOrderStatus::Pending,
                ]);

                foreach ($kitchenItems as $line) {
                    $kitchenOrder->items()->create([
                        'product_id' => $line['product']->id,
                        'product_name' => $line['product']->name,
                        'quantity' => $line['quantity'],
                    ]);
                }
            }

            return $sale->load('items', 'kitchenOrder.items');
        });
    }
}
