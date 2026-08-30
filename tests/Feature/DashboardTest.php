<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Enums\TenantMembershipRole;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use App\Services\SaleService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_todays_sales_expenses_and_net_income(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 10000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 10000);
        Expense::factory()->for($tenant)->create(['amount' => 3000, 'expense_date' => now($tenant->timezone)->toDateString()]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.dashboard')
            ->assertSee('₱100.00')
            ->assertSee('₱30.00')
            ->assertSee('₱70.00');
    }

    public function test_dashboard_flags_low_stock_products_and_supplies(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $product = Product::factory()->for($tenant)->create(['name' => 'Siomai', 'low_stock_threshold' => 10]);
        app(ProductInventoryService::class)->adjust($product, 3, ProductInventoryMovementType::StockAdded);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.dashboard')->assertSee('Siomai');
    }

    public function test_todays_sales_use_tenant_local_calendar_day_not_utc(): void
    {
        // 8:00 PM UTC on Jan 1 is already 4:00 AM Jan 2 in Asia/Manila
        // (UTC+8) — a naive whereDate('created_at', ...) against the raw
        // UTC-stored timestamp would bucket this sale under Jan 1 and miss
        // it entirely when the tenant asks for "today" (Jan 2, their time).
        Carbon::setTestNow('2026-01-01 20:00:00');

        $tenant = Tenant::factory()->create(['timezone' => 'Asia/Manila']);
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 5000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 5000);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.dashboard')
            ->assertSee('Jan 2, 2026')
            ->assertSee('₱50.00');

        Carbon::setTestNow();
    }

    public function test_dashboard_period_filter_scopes_sales_and_expenses_to_the_selected_range(): void
    {
        $tenant = Tenant::factory()->create(['timezone' => 'Asia/Manila']);
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['selling_price' => 5000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        // A sale and expense from three days ago — outside "today" by default.
        Carbon::setTestNow(now($tenant->timezone)->subDays(3));
        app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 1]], OrderType::ToGo, $paymentMethod, 5000);
        Expense::factory()->for($tenant)->create(['amount' => 2000, 'expense_date' => now($tenant->timezone)->toDateString()]);
        $threeDaysAgo = now($tenant->timezone)->toDateString();
        Carbon::setTestNow();

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.dashboard')
            ->assertSee('No transactions yet.')
            ->set('period', 'custom')
            ->set('dateFrom', $threeDaysAgo)
            ->set('dateTo', $threeDaysAgo)
            ->assertDontSee('No transactions yet.')
            ->assertSee('₱50.00')
            ->assertSee('₱20.00');
    }

    public function test_only_owner_can_access_the_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $kitchenStaff = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);
        $tenant->memberships()->create(['user_id' => $kitchenStaff->id, 'role' => TenantMembershipRole::KitchenStaff]);

        $this->actingAs($cashier)->get('/app/dashboard')->assertForbidden();
        $this->actingAs($kitchenStaff)->get('/app/dashboard')->assertForbidden();
    }
}
