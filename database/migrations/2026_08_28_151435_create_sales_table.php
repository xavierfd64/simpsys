<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->enum('order_type', ['dine_in', 'to_go'])->nullable();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('payment_method_name');
            $table->unsignedInteger('total');
            $table->unsignedInteger('amount_received');
            $table->unsignedInteger('change_amount');
            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
