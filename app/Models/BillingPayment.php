<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'subscription_id', 'recorded_by', 'amount', 'payment_method_label', 'reference', 'paid_at', 'notes',
    'paypal_order_id', 'paypal_capture_id', 'paypal_payer_email',
])]
class BillingPayment extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * A PayPal-originated payment is identified by carrying an order id —
     * there is no separate "payment_method" taxonomy column, since this
     * one fact is all any call site actually needs to know.
     */
    public function isPayPal(): bool
    {
        return filled($this->paypal_order_id);
    }
}
