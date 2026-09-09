<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Direct IDOR/BOLA probes across every BelongsToTenant-scoped model with a
 * client-reachable id: crafting another tenant's real record id and
 * attempting to view/act on it must 404 or no-op, never succeed — server-
 * side, independent of any hidden UI element.
 */
class TenantIdorSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTwoTenants(): array
    {
        $tenantA = Tenant::factory()->create();
        $ownerA = User::factory()->create();
        $membershipA = $tenantA->memberships()->create(['user_id' => $ownerA->id, 'role' => TenantMembershipRole::Owner]);

        $tenantB = Tenant::factory()->create();
        $ownerB = User::factory()->create();
        $tenantB->memberships()->create(['user_id' => $ownerB->id, 'role' => TenantMembershipRole::Owner]);

        return compact('tenantA', 'ownerA', 'membershipA', 'tenantB', 'ownerB');
    }

    public function test_a_tenant_cannot_view_another_tenants_product_by_id(): void
    {
        ['tenantB' => $tenantB, 'ownerA' => $ownerA] = $this->makeTwoTenants();
        $category = ProductCategory::withoutGlobalScope(TenantScope::class)->create(['tenant_id' => $tenantB->id, 'name' => 'Cat']);
        $victimProduct = Product::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenantB->id,
            'product_category_id' => $category->id,
            'name' => "Tenant B's Secret Recipe",
            'type' => 'ready_to_sell',
            'selling_price' => 10000,
            'is_active' => true,
        ]);

        $this->actingAs($ownerA)->get("/app/products/{$victimProduct->uuid}")->assertNotFound();
    }

    public function test_a_tenant_cannot_view_another_tenants_sale_by_id(): void
    {
        ['tenantB' => $tenantB, 'ownerA' => $ownerA, 'ownerB' => $ownerB] = $this->makeTwoTenants();
        $paymentMethod = PaymentMethod::withoutGlobalScope(TenantScope::class)->create(['tenant_id' => $tenantB->id, 'name' => 'Cash']);
        $victimSale = Sale::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenantB->id,
            'number' => 1,
            'cashier_id' => $ownerB->id,
            'order_type' => 'dine_in',
            'status' => 'completed',
            'total' => 10000,
            'amount_received' => 10000,
            'change_amount' => 0,
            'payment_method_id' => $paymentMethod->id,
            'payment_method_name' => 'Cash',
        ]);

        $this->actingAs($ownerA)->get("/app/sales/{$victimSale->uuid}")->assertNotFound();
    }

    /**
     * Livewire action-level IDOR: an owner supplying another tenant's
     * expense id to an edit/delete action (not just a route-bound page)
     * must not be able to touch it.
     */
    public function test_a_tenant_cannot_delete_another_tenants_expense_via_a_livewire_action(): void
    {
        ['tenantA' => $tenantA, 'ownerA' => $ownerA, 'membershipA' => $membershipA, 'tenantB' => $tenantB] = $this->makeTwoTenants();
        $categoryB = ExpenseCategory::withoutGlobalScope(TenantScope::class)->create(['tenant_id' => $tenantB->id, 'name' => 'Cat B']);
        $victimExpense = Expense::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenantB->id,
            'expense_category_id' => $categoryB->id,
            'amount' => 5000,
            'expense_date' => now()->toDateString(),
            'payment_method_label' => 'Cash',
        ]);

        $this->actingAs($ownerA);
        app(TenantContext::class)->setMembership($membershipA);

        try {
            Livewire::test('pages::tenant.expenses.index')->call('delete', $victimExpense->id);
        } catch (ModelNotFoundException) {
            // Expected.
        }

        // Bypass the tenant scope for this check too — otherwise, since
        // TenantContext is still set to tenant A, even a genuinely
        // deleted tenant-B row would look "missing" for the wrong reason.
        $this->assertNotNull(Expense::withoutGlobalScope(TenantScope::class)->find($victimExpense->id));
    }

    public function test_a_tenant_cannot_adjust_another_tenants_supply_stock_via_a_livewire_action(): void
    {
        ['tenantA' => $tenantA, 'ownerA' => $ownerA, 'membershipA' => $membershipA, 'tenantB' => $tenantB] = $this->makeTwoTenants();
        $categoryB = SupplyCategory::withoutGlobalScope(TenantScope::class)->create(['tenant_id' => $tenantB->id, 'name' => 'Cat B']);
        $victimSupply = Supply::withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenantB->id,
            'supply_category_id' => $categoryB->id,
            'name' => "Tenant B's Supply",
            'unit' => 'kg',
            'low_stock_threshold' => 10,
        ]);
        $victimSupply->inventory()->updateOrCreate([], ['quantity' => 100]);

        $this->actingAs($ownerA);
        app(TenantContext::class)->setMembership($membershipA);

        // openAdjust() itself does no lookup (it only stores the raw id
        // for later), so adjustStock() — where Supply::findOrFail()
        // actually runs — is where the tenant scope must reject it.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test('pages::tenant.supplies.index')
            ->call('openAdjust', $victimSupply->id)
            ->set('quantity', '999')
            ->call('adjustStock');
    }
}
