# PayPal Payment Integration

PayPal is a second, optional payment method alongside the existing Manual /
Fund Transfer workflow. Manual payments still work exactly as before — a
Platform Admin verifies an external payment and records it on a business's
detail page. PayPal is fully automatic: once PayPal itself confirms a
payment completed, BizManager records it and activates the subscription
with no admin action needed.

## Architecture decision

BizManager's subscription plans (`subscription_plans.monthly_price`/
`yearly_price`) are flat prices for a fixed billing period, renewed by
extending `subscriptions.current_period_end` — not a recurring/metered
billing engine. This integration therefore uses **PayPal's Orders v2 API**
(one-time order → approve → capture, per purchase/renewal), not PayPal
Subscriptions — it maps directly onto the existing "record a payment, renew
one period" model (`SubscriptionService::recordPayment()`) instead of
introducing a second, PayPal-side recurring-billing concept that would need
to be kept in sync with BizManager's own plan/price data.

## How it fits the existing subscription system

- `App\Models\PayPalOrder` is the "pending payment" record created before
  BizManager ever calls PayPal — see its own docblock. It always points at
  one of a tenant's existing `Subscription` rows; this is not a second
  subscription system.
- Once PayPal confirms a capture is `COMPLETED`, `PayPalCheckoutService`
  calls `SubscriptionService::recordPayment()` — the exact same method a
  Platform Admin's manual "Record Payment" action already uses — so a
  PayPal-funded payment produces a normal `BillingPayment` row, renews the
  subscription, and syncs `Tenant.status`, identically to a manual payment.
  The billing statement, admin business detail page, and every other
  existing billing view work unmodified.
- `BillingPayment` gained three nullable columns (`paypal_order_id`,
  `paypal_capture_id`, `paypal_payer_email`) purely for auditing/filtering
  — a PayPal payment already displays correctly on every existing view
  using the pre-existing `payment_method_label`/`reference` fields alone.

## Setting up PayPal (Platform Admin)

No `.env` editing, no server access, no code changes — everything is
configured from **Platform Admin → Settings → Payment Settings**.

1. Log in to your [PayPal Developer account](https://developer.paypal.com).
2. Create (or open) a PayPal App under *Apps & Credentials*. Sandbox and
   Live each have their own Client ID/Secret — use the Sandbox ones first.
3. Copy the **Client ID** and **Client Secret** into the matching fields in
   BizManager.
4. In your PayPal App, add a webhook pointing to the URL shown on the
   settings page (`https://your-domain/webhooks/paypal`), subscribed to at
   least: `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`,
   `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.PENDING`,
   `CHECKOUT.PAYMENT-APPROVAL.REVERSED`. Paste the webhook's ID into the
   **Webhook ID** field.
5. Click **Test PayPal Connection** — this authenticates with PayPal using
   whatever is currently in the form (even unsaved), so you find out
   immediately if a credential is wrong.
6. Check **Enable PayPal at checkout**, confirm the **Currency** (defaults
   to PHP), and click **Save Settings**.

Manual/Fund Transfer can be turned off independently once you trust PayPal,
via the **Payment Methods** toggle above — it stays on by default so
nothing changes for an installation that never touches this page.

## Testing in Sandbox

With **Environment** set to *Sandbox* and Sandbox credentials saved:

1. Log in as a tenant owner and go to **Billing**.
2. Pick a plan/period, choose **PayPal**, and click **Pay with PayPal**.
3. Approve the payment using a PayPal Sandbox buyer account.
4. You're redirected back to Billing — the subscription should already
   show **Active**, a new row appears in the billing statement's payment
   history, and (if SMTP is configured) the existing payment-received email
   is sent.
5. Check **Platform Admin → Businesses → (the business) → Billing
   History** — the payment shows with method "PayPal" and a "VERIFIED"
   badge, with the PayPal order id as its reference.
6. Try cancelling checkout instead of approving — the subscription must
   stay unchanged and no payment is recorded.

## Going live

Once Sandbox testing works end to end, switch **Environment** to *Live*,
paste your Live Client ID/Secret/Webhook ID (a separate PayPal App from
Sandbox), **Test PayPal Connection** again, and save. No other change is
needed — the same code path is used for both environments.

## Security notes

- The Client Secret is stored encrypted at rest (Laravel's `encrypted`
  cast, the same mechanism already used for the SMTP password) and is
  never redisplayed once saved — the settings page shows only whether one
  is configured.
- Every webhook event is verified via PayPal's own server-side
  `verify-webhook-signature` API before anything in it is trusted; an
  unverified event is rejected outright.
- A subscription is only ever activated after BizManager's own server
  calls PayPal's capture API and PayPal's response says the capture
  `COMPLETED` — never from the browser simply returning from PayPal, and
  never from an unverified webhook body alone.
- PayPal may redeliver the same webhook event; `paypal_webhook_events`
  records every event id before acting on it, so a redelivery is a no-op.
  A concurrent browser-return-and-webhook race is closed with an atomic
  conditional database update (see `PayPalCheckoutService::completeOrder()`).
- The amount sent to PayPal always comes from `SubscriptionPlan::priceFor()`
  on the server — there is no field anywhere in the checkout UI the browser
  could use to influence the charged amount.
