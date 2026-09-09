<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defaults match the spec exactly (5 attempts / 15-minute lockout,
     * CAPTCHA after 3) so an existing installation gets the hardened
     * behavior automatically with no admin action required.
     */
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('login_protection_enabled')->default(true)->after('paypal_currency');
            $table->unsignedTinyInteger('max_login_attempts')->default(5)->after('login_protection_enabled');
            $table->unsignedSmallInteger('lockout_minutes')->default(15)->after('max_login_attempts');
            $table->boolean('captcha_enabled')->default(true)->after('lockout_minutes');
            $table->unsignedTinyInteger('captcha_threshold')->default(3)->after('captcha_enabled');
            $table->boolean('rate_limiting_enabled')->default(true)->after('captcha_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'login_protection_enabled', 'max_login_attempts', 'lockout_minutes',
                'captcha_enabled', 'captcha_threshold', 'rate_limiting_enabled',
            ]);
        });
    }
};
