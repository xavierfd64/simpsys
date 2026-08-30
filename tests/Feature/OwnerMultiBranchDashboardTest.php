<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BranchService;
use App\Services\ProductInventoryService;
use App\Services\SaleService;
use App\Services\TenantContext;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerMultiBranchDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function recordSaleAndExpenseFor($tenant, User $owner, int $saleTotal, int $expenseAmount): void
    {
        $paymentMethod = PaymentMethod::forTenant($tenant)->first()
            ?? PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'Cash', 'is_enabled' => true, 'sort_order' => 0]);

        $product = Product::factory()->for($tenant)->create(['selling_price' => $saleTotal]);
        app(TenantContext::class)->setMembership($owner->memberships()->where('tenant_id', $tenant->id)->first());
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        app(SaleService::class)->recordSale($tenant, $owner, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, $saleTotal);

        Expense::create([
            'tenant_id' => $tenant->id,
            'amount' => $expenseAmount,
            'expense_date' => now($tenant->timezone)->toDateString(),
            'description' => 'Test expense',
        ]);
    }

    public function test_single_branch_owner_sees_no_branch_filter_or_comparison_table(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        $business = app(TenantOnboardingService::class)->register("Juan's Fishball Station", 'Juan', 'juan@example.test', 'password123');
        $owner = $business->owner()->user;

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($owner->activeMembership());

        Livewire::test('pages::tenant.dashboard')
            ->assertDontSee('All Branches')
            ->assertDontSee('Branch Performance');
    }

    public function test_owner_dashboard_aggregates_sales_and_expenses_across_all_branches(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        $business = app(TenantOnboardingService::class)->register("Juan's Fishball Station", 'Juan', 'juan@example.test', 'password123');
        $owner = $business->owner()->user;
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);
        $branchService->approve($branch, $admin);

        $this->recordSaleAndExpenseFor($business, $owner, 10000, 2000);
        $this->recordSaleAndExpenseFor($branch, $owner, 5000, 1000);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($owner->memberships()->where('tenant_id', $business->id)->first());

        // All Branches (default): combined totals across both.
        Livewire::test('pages::tenant.dashboard')
            ->assertSee('₱150.00')
            ->assertSee('₱30.00')
            ->assertSee('Branch Performance')
            ->assertSee('Branch 2')
            ->set('branchFilter', (string) $business->id)
            ->assertSee('₱100.00')
            ->assertDontSee('₱150.00');
    }

    public function test_branch_performance_table_flags_the_best_and_worst_branch(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        $business = app(TenantOnboardingService::class)->register("Juan's Fishball Station", 'Juan', 'juan@example.test', 'password123');
        $owner = $business->owner()->user;
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Weak Branch']);
        $branchService->approve($branch, $admin);

        $this->recordSaleAndExpenseFor($business, $owner, 20000, 1000);
        $this->recordSaleAndExpenseFor($branch, $owner, 1000, 500);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($owner->memberships()->where('tenant_id', $business->id)->first());

        Livewire::test('pages::tenant.dashboard')
            ->assertSee('Best')
            ->assertSee('Needs Attention');
    }
}
