<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PayPal may redeliver the same webhook event — this table is the
     * idempotency ledger: an event id is recorded before it is acted on,
     * so a redelivery is recognized and skipped rather than double-applying
     * a payment/activation.
     */
    public function up(): void
    {
        Schema::create('paypal_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type', 100);
            $table->timestamp('processed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_webhook_events');
    }
};
