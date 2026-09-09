<?php

namespace App\Http\Controllers;

use App\Models\PayPalOrder;
use App\Services\PayPalCheckoutService;
use App\Services\TenantContext;
use App\Support\PayPalException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where PayPal redirects the customer's browser back to after they approve
 * payment (?token={order id}&PayerID=...). This is only ever the trigger to
 * ask PayPal "is this really done" — the actual activation happens inside
 * PayPalCheckoutService::completeOrder(), which calls PayPal's own capture
 * API and only proceeds once PayPal's own response says COMPLETED. Never
 * activate anything from the mere fact that this route was hit.
 */
class PayPalReturnController extends Controller
{
    public function __invoke(Request $request, PayPalCheckoutService $checkout, TenantContext $tenantContext): RedirectResponse
    {
        $orderId = $request->query('token');

        if (blank($orderId)) {
            return redirect()->route('app.billing')->with('billing_error', 'Missing PayPal order reference.');
        }

        // An order id is an opaque PayPal-issued token, not a secret — but
        // nothing stops one authenticated tenant from typing/replaying
        // another tenant's order id here. Ownership must be checked before
        // acting: this route (and the analogous cancel route) is the only
        // one of the three PayPal entry points that runs with an
        // authenticated tenant in context at all, so it's the only one
        // that both can and must enforce it — the webhook path has no
        // "current user" to compare against and relies on PayPal's own
        // signature instead.
        $order = PayPalOrder::where('paypal_order_id', $orderId)->first();
        $currentTenant = $tenantContext->tenant()?->businessRoot();

        if (! $order || ! $currentTenant || $order->tenant_id !== $currentTenant->id) {
            return redirect()->route('app.billing')->with('billing_error', 'This payment reference could not be found for your account.');
        }

        try {
            $result = $checkout->completeOrder($orderId);
        } catch (PayPalException $e) {
            return redirect()->route('app.billing')->with('billing_error', $e->getMessage());
        }

        return $result['success']
            ? redirect()->route('app.billing')->with('billing_status', $result['message'])
            : redirect()->route('app.billing')->with('billing_error', $result['message'].' No payment was completed — please try again or choose another payment method.');
    }
}
