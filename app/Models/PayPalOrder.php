<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The internal "pending payment" record created before BizManager ever
 * calls PayPal — see the migration's own docblock for why this exists
 * rather than writing straight to billing_payments. Always resolves back
 * to one of the tenant's existing Subscription rows; this is never a
 * second subscription system.
 */
#[Fillable([
    'tenant_id', 'subscription_id', 'subscription_plan_id', 'billing_period',
    'amount', 'currency', 'paypal_order_id', 'paypal_capture_id', 'status',
    'payer_email', 'raw_response',
])]
class PayPalOrder extends Model
{
    use HasUuid;

    // Eloquent's snake_case convention would derive "pay_pal_orders" from
    // this class name (it splits "PayPal" into "Pay"+"Pal") — the actual
    // migration created "paypal_orders", matching how every other
    // PayPal-related identifier in this app is spelled.
    protected $table = 'paypal_orders';

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
