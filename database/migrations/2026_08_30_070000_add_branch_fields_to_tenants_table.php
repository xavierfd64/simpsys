<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A "branch" is just another row in this same table: `parent_tenant_id`
     * links it to the business's root tenant, so every existing
     * tenant-scoped feature (products, POS, inventory, sales, expenses,
     * kitchen, users) already isolates branch data correctly via the
     * existing BelongsToTenant/TenantContext machinery — no changes needed
     * there. A root tenant (parent_tenant_id null) keeps using the existing
     * `status` column exactly as before; the new `branch_status` column
     * only applies to non-root (branch) rows.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('parent_tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->string('branch_code', 50)->nullable()->after('name');
            $table->string('branch_address')->nullable();
            $table->string('branch_contact_number', 50)->nullable();
            $table->string('branch_contact_email')->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('branch_status', 30)->nullable();
            $table->string('branch_rejection_reason')->nullable();
            $table->timestamp('branch_approved_at')->nullable();
            $table->foreignId('branch_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('parent_tenant_id');
            $table->index('branch_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_tenant_id');
            $table->dropConstrainedForeignId('manager_user_id');
            $table->dropConstrainedForeignId('branch_approved_by');
            $table->dropColumn([
                'branch_code', 'branch_address', 'branch_contact_number', 'branch_contact_email',
                'branch_status', 'branch_rejection_reason', 'branch_approved_at',
            ]);
        });
    }
};
