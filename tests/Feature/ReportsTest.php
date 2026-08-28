<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Enums\ProductInventoryMovementType;
use App\Enums\TenantMembershipRole;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductInventoryService;
use App\Services\SaleService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_totals_completed_sales_in_range(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create(['name' => 'Cash']);
        $product = Product::factory()->for($tenant)->create(['selling_price' => 5000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);

        app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 2]], OrderType::ToGo, $paymentMethod, 10000);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.reports.index')
            ->assertSee('₱100.00')
            ->assertSee('Cash');
    }

    public function test_product_report_shows_quantity_sold_and_revenue(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $paymentMethod = PaymentMethod::factory()->for($tenant)->create();
        $product = Product::factory()->for($tenant)->create(['name' => 'Fishball', 'selling_price' => 2000]);
        app(ProductInventoryService::class)->adjust($product, 10, ProductInventoryMovementType::StockAdded);
        app(SaleService::class)->recordSale($tenant, $cashier, [['product_id' => $product->id, 'quantity' => 3]], OrderType::ToGo, $paymentMethod, 6000);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.reports.index')
            ->set('tab', 'products')
            ->assertSee('Fishball')
            ->assertSee('₱60.00');
    }

    public function test_cashier_cannot_access_reports(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/reports')->assertForbidden();
    }
}
