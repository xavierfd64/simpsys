<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'stock_added', 'batch_produced', 'sale', 'spoilage', 'damage',
                'personal_consumption', 'missing', 'adjustment', 'void_reversal',
            ]);
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Named explicitly: Laravel's auto-generated name for this
            // column combination is 65 characters, one past MySQL's
            // 64-character identifier limit (SQLite has no such limit,
            // which is why this only surfaces once a real MySQL/MariaDB
            // database runs the migration).
            $table->index(['tenant_id', 'product_id', 'created_at'], 'product_inventory_movements_tenant_product_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_inventory_movements');
    }
};
