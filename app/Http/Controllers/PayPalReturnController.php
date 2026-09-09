<?php

namespace App\Http\Controllers;

use App\Services\PayPalCheckoutService;
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
    public function __invoke(Request $request, PayPalCheckoutService $checkout): RedirectResponse
    {
        $orderId = $request->query('token');

        if (blank($orderId)) {
            return redirect()->route('app.billing')->with('billing_error', 'Missing PayPal order reference.');
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
