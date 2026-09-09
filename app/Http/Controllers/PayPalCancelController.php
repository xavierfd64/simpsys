<?php

namespace App\Http\Controllers;

use App\Services\PayPalCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where PayPal redirects the customer's browser if they cancel checkout
 * before approving. No payment happened, so nothing is activated — this
 * only marks the pending order as cancelled for the admin's own records.
 */
class PayPalCancelController extends Controller
{
    public function __invoke(Request $request, PayPalCheckoutService $checkout): RedirectResponse
    {
        $orderId = $request->query('token');

        if (filled($orderId)) {
            $checkout->markCancelled($orderId);
        }

        return redirect()->route('app.billing')->with('billing_error', 'Payment cancelled. No payment was completed.');
    }
}
