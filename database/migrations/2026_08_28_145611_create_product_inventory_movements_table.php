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

            $table->index(['tenant_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_inventory_movements');
    }
};
