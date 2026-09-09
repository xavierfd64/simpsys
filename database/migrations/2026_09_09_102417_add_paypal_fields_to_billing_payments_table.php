<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every existing manually-recorded payment already prints fine on the
     * statement view using payment_method_label/reference alone — these
     * columns are purely additive, for reliable admin filtering/auditing
     * and idempotency, not required for a payment to display correctly.
     * Null on every payment that isn't a PayPal one, including every
     * existing row.
     */
    public function up(): void
    {
        Schema::table('billing_payments', function (Blueprint $table) {
            $table->string('paypal_order_id')->nullable()->after('notes');
            $table->string('paypal_capture_id')->nullable()->after('paypal_order_id');
            $table->string('paypal_payer_email')->nullable()->after('paypal_capture_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_payments', function (Blueprint $table) {
            $table->dropColumn(['paypal_order_id', 'paypal_capture_id', 'paypal_payer_email']);
        });
    }
};
