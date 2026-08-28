<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
