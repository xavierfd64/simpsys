<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per (notice, tenant) — the banner is tenant-wide, so whoever
     * from that tenant first sees it marks it read for the whole business,
     * matching the admin's "Business | User | Status | Read At" view.
     */
    public function up(): void
    {
        Schema::create('platform_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['platform_notification_id', 'tenant_id'], 'notice_reads_notice_tenant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_notification_reads');
    }
};
