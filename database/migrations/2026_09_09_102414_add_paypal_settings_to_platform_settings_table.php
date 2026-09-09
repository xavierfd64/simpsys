<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            // Every column nullable/defaulted so an existing installation
            // needs no data migration or repair step — an unconfigured
            // install behaves exactly as it does today: manual payment
            // only, PayPal simply not offered anywhere.
            $table->boolean('manual_payment_enabled')->default(true)->after('mail_from_name');
            $table->boolean('paypal_enabled')->default(false)->after('manual_payment_enabled');
            $table->string('paypal_environment', 10)->default('sandbox')->after('paypal_enabled');
            $table->string('paypal_client_id')->nullable()->after('paypal_environment');
            $table->text('paypal_client_secret')->nullable()->after('paypal_client_id');
            $table->string('paypal_webhook_id')->nullable()->after('paypal_client_secret');
            $table->string('paypal_currency', 3)->default('PHP')->after('paypal_webhook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'manual_payment_enabled',
                'paypal_enabled',
                'paypal_environment',
                'paypal_client_id',
                'paypal_client_secret',
                'paypal_webhook_id',
                'paypal_currency',
            ]);
        });
    }
};
