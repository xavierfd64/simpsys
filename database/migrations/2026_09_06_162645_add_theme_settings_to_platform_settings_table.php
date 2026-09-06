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
            // Nullable: null means "use the built-in default" for both —
            // no row needs a migration/data-repair step, an unconfigured
            // installation just keeps looking exactly as it does today.
            $table->string('theme_primary_color', 7)->nullable()->after('favicon_path');
            $table->string('theme_font', 30)->nullable()->after('theme_primary_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['theme_primary_color', 'theme_font']);
        });
    }
};
