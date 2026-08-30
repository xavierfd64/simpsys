<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Storing which period_end the last reminder covered (rather than just
     * a timestamp) means eligibility resets automatically on renewal —
     * once current_period_end moves forward, this no longer matches, and
     * a fresh reminder becomes due for the new cycle without needing a
     * separate "reset" step anywhere.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('last_reminder_period_end')->nullable()->after('current_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('last_reminder_period_end');
        });
    }
};
