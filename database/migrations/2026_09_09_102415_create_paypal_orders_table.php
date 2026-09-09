<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The internal "pending payment" record BizManager creates before ever
     * calling PayPal — tracks a tenant's in-progress plan purchase/renewal
     * from order creation through capture (or cancellation/failure), so a
     * webhook or a browser return can both find the same record and act on
     * it idempotently. This is the one and only new "order" concept added
     * for PayPal — it always resolves to an existing tenant Subscription
     * (a BillingPayment + subscription renewal via the existing
     * SubscriptionService), never a second subscription system.
     */
    public function up(): void
    {
        Schema::create('paypal_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_period', 10);
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('paypal_order_id')->unique();
            $table->string('paypal_capture_id')->nullable()->unique();
            $table->string('status', 20)->default('created');
            $table->string('payer_email')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_orders');
    }
};
