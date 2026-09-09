<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency ledger for incoming PayPal webhook deliveries — see the
 * migration's own docblock. A row existing for an event id means that
 * event has already been handled (or is being handled), regardless of how
 * many times PayPal redelivers it.
 */
#[Fillable(['event_id', 'event_type', 'processed_at', 'note'])]
class PayPalWebhookEvent extends Model
{
    // See PayPalOrder's identical note — Eloquent's convention would derive
    // "pay_pal_webhook_events" from this class name.
    protected $table = 'paypal_webhook_events';

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
