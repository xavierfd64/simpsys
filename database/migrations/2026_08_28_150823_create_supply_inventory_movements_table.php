<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['stock_added', 'manual_usage', 'spoilage', 'adjustment']);
            $table->decimal('quantity_change', 10, 2);
            $table->decimal('quantity_after', 10, 2);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Named explicitly for symmetry with the product_inventory_movements
            // migration — that one's auto-generated name exceeds MySQL's
            // 64-character identifier limit; this one is only 1 character
            // under it, so a future column rename would tip it over too.
            $table->index(['tenant_id', 'supply_id', 'created_at'], 'supply_inventory_movements_tenant_supply_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_inventory_movements');
    }
};
