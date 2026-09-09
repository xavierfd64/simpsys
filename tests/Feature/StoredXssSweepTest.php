<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\PlatformNotification;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Real rendered-page checks (not just a source-code read) that a script
 * payload stored in ordinary business data (product/expense names, notes)
 * comes back HTML-escaped, never as a live <script> tag, everywhere it's
 * shown to a user. Blade's {{ }} escapes by default — this exercises that
 * guarantee against the actual live page output rather than assuming it.
 */
class StoredXssSweepTest extends TestCase
{
    use RefreshDatabase;

    protected const PAYLOAD = '<script>alert(document.cookie)</script>';

    protected function makeOwner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        return [$tenant, $owner, $membership];
    }

    public function test_a_malicious_product_name_is_escaped_on_the_products_page(): void
    {
        [$tenant] = $this->makeOwner();
        $category = ProductCategory::create(['tenant_id' => $tenant->id, 'name' => 'Cat']);
        Product::create([
            'tenant_id' => $tenant->id,
            'product_category_id' => $category->id,
            'name' => self::PAYLOAD,
            'type' => 'ready_to_sell',
            'selling_price' => 10000,
            'is_active' => true,
        ]);

        $html = Livewire::test('pages::tenant.products.index')->html();

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_malicious_expense_category_name_is_escaped_on_the_expenses_page(): void
    {
        [$tenant] = $this->makeOwner();
        ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => self::PAYLOAD]);
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'Cash']);

        // Categories only render inside the "Categories" management modal,
        // not the expense list itself — open it so the name is actually on
        // the rendered page rather than asserting against markup that
        // never included it.
        $html = Livewire::test('pages::tenant.expenses.index')
            ->set('showCategoryModal', true)
            ->html();

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_malicious_expense_notes_field_is_escaped_on_the_expenses_page(): void
    {
        [$tenant] = $this->makeOwner();
        $category = ExpenseCategory::create(['tenant_id' => $tenant->id, 'name' => 'Cat']);
        Expense::create([
            'tenant_id' => $tenant->id,
            'expense_category_id' => $category->id,
            'amount' => 5000,
            'expense_date' => now()->toDateString(),
            'payment_method_label' => 'Cash',
            'notes' => self::PAYLOAD,
        ]);

        $html = Livewire::test('pages::tenant.expenses.index')->html();

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
    }

    public function test_a_malicious_business_name_is_escaped_across_the_app_shell(): void
    {
        [$tenant] = $this->makeOwner();
        $tenant->update(['name' => self::PAYLOAD]);

        $html = Livewire::test('pages::tenant.dashboard')->html();

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
    }

    public function test_a_malicious_platform_notice_message_is_escaped_on_the_dashboard_banner(): void
    {
        [$tenant] = $this->makeOwner();

        PlatformNotification::create([
            'audience' => 'all',
            'title' => 'Notice',
            'message' => self::PAYLOAD,
            'is_active' => true,
            'published_at' => now()->subMinute(),
        ]);

        $html = Livewire::test('pages::tenant.dashboard')->html();

        $this->assertStringNotContainsString(self::PAYLOAD, $html);
    }
}
