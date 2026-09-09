<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TenantUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwnerAndVictim(): array
    {
        $attackerTenant = Tenant::factory()->create();
        $attackerOwner = User::factory()->create();
        $attackerMembership = $attackerTenant->memberships()->create(['user_id' => $attackerOwner->id, 'role' => TenantMembershipRole::Owner]);

        $victimTenant = Tenant::factory()->create();
        $victimUser = User::factory()->create(['password' => 'victims-real-password']);
        $victimMembership = $victimTenant->memberships()->create(['user_id' => $victimUser->id, 'role' => TenantMembershipRole::Owner]);

        return compact('attackerTenant', 'attackerOwner', 'attackerMembership', 'victimTenant', 'victimUser', 'victimMembership');
    }

    /**
     * CRITICAL PoC: TenantMembership::findOrFail() has no tenant scope of
     * its own (unlike Product/Sale/Expense/etc, which use BelongsToTenant).
     * An authenticated owner of one tenant supplying another tenant's real
     * membership id to resetPassword() must not be able to overwrite that
     * victim's password — this is a full cross-tenant account takeover if
     * unguarded.
     */
    public function test_a_tenant_owner_cannot_reset_another_tenants_users_password(): void
    {
        $data = $this->makeOwnerAndVictim();

        $this->actingAs($data['attackerOwner']);
        app(TenantContext::class)->setMembership($data['attackerMembership']);

        try {
            Livewire::test('pages::tenant.users.index')
                ->call('openResetPassword', $data['victimMembership']->id)
                ->set('new_password', 'attacker-controlled-password')
                ->call('resetPassword');
        } catch (ModelNotFoundException) {
            // Expected — a cross-tenant id must 404, not silently no-op.
        }

        $this->assertTrue(Hash::check('victims-real-password', $data['victimUser']->fresh()->password));
        $this->assertFalse(Hash::check('attacker-controlled-password', $data['victimUser']->fresh()->password));
    }

    public function test_a_tenant_owner_cannot_edit_another_tenants_membership(): void
    {
        $data = $this->makeOwnerAndVictim();
        $originalEmail = $data['victimUser']->email;

        $this->actingAs($data['attackerOwner']);
        app(TenantContext::class)->setMembership($data['attackerMembership']);

        try {
            Livewire::test('pages::tenant.users.index')
                ->call('openEdit', $data['victimMembership']->id)
                ->set('email', 'attacker-takeover@example.test')
                ->set('name', 'Hijacked')
                ->set('role', 'owner')
                ->call('save');
        } catch (ModelNotFoundException) {
            // Expected — a cross-tenant id must 404, not silently no-op.
        }

        $this->assertSame($originalEmail, $data['victimUser']->fresh()->email);
        $this->assertNotSame('Hijacked', $data['victimUser']->fresh()->name);
    }

    public function test_a_tenant_owner_cannot_deactivate_another_tenants_membership(): void
    {
        $data = $this->makeOwnerAndVictim();

        $this->actingAs($data['attackerOwner']);
        app(TenantContext::class)->setMembership($data['attackerMembership']);

        try {
            Livewire::test('pages::tenant.users.index')
                ->call('toggleActive', $data['victimMembership']->id);
        } catch (ModelNotFoundException) {
            // Expected — a cross-tenant id must 404, not silently no-op.
        }

        $this->assertSame(
            TenantMembershipStatus::Active,
            $data['victimMembership']->fresh()->status,
        );
    }

    public function test_an_owner_can_still_manage_their_own_tenants_memberships(): void
    {
        $attackerTenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $attackerTenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $staff = User::factory()->create();
        $staffMembership = $attackerTenant->memberships()->create(['user_id' => $staff->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($owner);
        $ownerMembership = $attackerTenant->memberships()->where('user_id', $owner->id)->first();
        app(TenantContext::class)->setMembership($ownerMembership);

        Livewire::test('pages::tenant.users.index')
            ->call('openResetPassword', $staffMembership->id)
            ->set('new_password', 'a-new-valid-password')
            ->call('resetPassword');

        $this->assertTrue(Hash::check('a-new-valid-password', $staff->fresh()->password));
    }
}
