<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('message');
            $table->timestamp('published_at')->nullable()->after('is_active');
            $table->timestamp('expires_at')->nullable()->after('published_at');
        });

        // Every notice that already exists was, under the old behavior,
        // visible immediately on creation — backfill published_at from
        // created_at so existing notices don't silently disappear.
        DB::table('platform_notifications')->whereNull('published_at')->update([
            'published_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'published_at', 'expires_at']);
        });
    }
};
