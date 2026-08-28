<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('order_types_enabled')->default(false);
            $table->boolean('dine_in_enabled')->default(true);
            $table->boolean('to_go_enabled')->default(true);
            $table->enum('default_order_type', ['dine_in', 'to_go'])->default('to_go');
            $table->boolean('kitchen_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
