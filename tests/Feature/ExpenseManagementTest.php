<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_record_an_expense(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.expenses.index')
            ->call('openCreate')
            ->set('amount', '500.00')
            ->set('expense_date', now()->toDateString())
            ->set('description', 'Cooking gas refill')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'tenant_id' => $tenant->id,
            'amount' => 50000,
            'description' => 'Cooking gas refill',
            'recorded_by' => $owner->id,
        ]);
    }

    /**
     * 2:00 AM Manila time (UTC+8) on Sept 1 is still 6:00 PM Aug 31 in UTC.
     * openCreate() used to default expense_date to bare now()->toDateString()
     * — the server's UTC "today" — so an expense recorded at that hour got
     * dated Aug 31 while the page's own default date filter (computed
     * correctly with the tenant's timezone) starts at Sept 1: the expense
     * saved successfully but fell outside the visible range, reading as
     * "recorded but not showing up."
     */
    public function test_new_expense_defaults_to_the_tenant_local_date_not_utc(): void
    {
        // setTestNow() takes a UTC instant, not tenant-local time — 18:00
        // UTC on Aug 31 is 02:00 the *next* day in Asia/Manila.
        Carbon::setTestNow('2026-08-31 18:00:00');

        $tenant = Tenant::factory()->create(['timezone' => 'Asia/Manila']);
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.expenses.index')
            ->call('openCreate')
            ->assertSet('expense_date', '2026-09-01')
            ->set('amount', '500.00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('₱500.00');

        Carbon::setTestNow();
    }

    public function test_expenses_are_scoped_to_the_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Expense::factory()->for($tenantA)->create(['description' => 'Tenant A rent']);
        Expense::factory()->for($tenantB)->create(['description' => 'Tenant B rent']);

        $owner = User::factory()->create();
        $membership = $tenantA->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        app(TenantContext::class)->setMembership($membership);

        $descriptions = Expense::query()->pluck('description');

        $this->assertTrue($descriptions->contains('Tenant A rent'));
        $this->assertFalse($descriptions->contains('Tenant B rent'));
    }

    public function test_cashier_cannot_access_expenses(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/expenses')->assertForbidden();
    }
}
