<?php

namespace App\Http\Controllers;

use App\Models\PayPalOrder;
use App\Services\PayPalCheckoutService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where PayPal redirects the customer's browser if they cancel checkout
 * before approving. No payment happened, so nothing is activated — this
 * only marks the pending order as cancelled for the admin's own records.
 */
class PayPalCancelController extends Controller
{
    public function __invoke(Request $request, PayPalCheckoutService $checkout, TenantContext $tenantContext): RedirectResponse
    {
        $orderId = $request->query('token');

        // Same ownership check as PayPalReturnController — an order id is
        // opaque but not secret, and nothing else stops one authenticated
        // tenant from cancelling another tenant's still-pending order by
        // replaying its id here.
        if (filled($orderId)) {
            $order = PayPalOrder::where('paypal_order_id', $orderId)->first();
            $currentTenant = $tenantContext->tenant()?->businessRoot();

            if ($order && $currentTenant && $order->tenant_id === $currentTenant->id) {
                $checkout->markCancelled($orderId);
            }
        }

        return redirect()->route('app.billing')->with('billing_error', 'Payment cancelled. No payment was completed.');
    }
}
