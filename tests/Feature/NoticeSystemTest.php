<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\PlatformNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoticeSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_notification_updates_it_in_place_rather_than_duplicating(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.notifications.index')
            ->set('audience', 'all')
            ->set('title', 'Original Title')
            ->set('message', 'Original message.')
            ->call('save');

        $this->assertSame(1, PlatformNotification::query()->count());
        $notification = PlatformNotification::first();

        Livewire::test('pages::admin.notifications.index')
            ->call('openEdit', $notification->id)
            ->set('title', 'Updated Title')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, PlatformNotification::query()->count());
        $this->assertSame('Updated Title', $notification->fresh()->title);
    }

    public function test_admin_can_unpublish_and_republish_a_notification(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        $test = Livewire::test('pages::admin.notifications.index')
            ->set('audience', 'active')
            ->set('title', 'Heads Up')
            ->set('message', 'Something important.')
            ->call('save');

        $notification = PlatformNotification::first();

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);
        Livewire::test('pages::tenant.dashboard')->assertSee('Heads Up');

        // Unpublish — the notice must disappear even though it was already
        // shown once.
        $this->actingAs($admin);
        Livewire::test('pages::admin.notifications.index')->call('togglePublish', $notification->id);
        $this->assertFalse($notification->fresh()->is_active);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);
        Livewire::test('pages::tenant.dashboard')->assertDontSee('Heads Up');

        // Republish — visible again.
        $this->actingAs($admin);
        Livewire::test('pages::admin.notifications.index')->call('togglePublish', $notification->id);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);
        Livewire::test('pages::tenant.dashboard')->assertSee('Heads Up');
    }

    public function test_admin_can_delete_a_notification(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.notifications.index')
            ->set('audience', 'all')
            ->set('title', 'Temp Notice')
            ->set('message', 'Delete me.')
            ->call('save');

        $notification = PlatformNotification::first();

        Livewire::test('pages::admin.notifications.index')->call('delete', $notification->id);

        $this->assertSame(0, PlatformNotification::query()->count());
    }

    public function test_viewing_a_notice_on_the_dashboard_marks_it_read_and_admin_sees_the_tally(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenantA = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $tenantB = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $membershipA = $tenantA->memberships()->create(['user_id' => $ownerA->id, 'role' => TenantMembershipRole::Owner]);
        $tenantB->memberships()->create(['user_id' => $ownerB->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.notifications.index')
            ->set('audience', 'active')
            ->set('title', 'Read Tracking Test')
            ->set('message', 'Please read this.')
            ->call('save');

        $notification = PlatformNotification::first();

        // Only tenant A's owner actually visits the dashboard.
        $this->actingAs($ownerA);
        app(TenantContext::class)->setMembership($membershipA);
        Livewire::test('pages::tenant.dashboard');

        $this->actingAs($admin);
        $stats = Livewire::test('pages::admin.notifications.index')
            ->call('viewReads', $notification->id)
            ->get('readStats');

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['read']);
        $this->assertSame(1, $stats['unread']);
    }
}
