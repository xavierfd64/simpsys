<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Enums\ProductInventoryMovementType;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchService;
use App\Services\ProductInventoryService;
use App\Services\TenantContext;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function registerBusiness(): array
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);

        $tenant = app(TenantOnboardingService::class)->register(
            "Juan's Fishball Station",
            'Juan Dela Cruz',
            'juan@example.test',
            'password123',
        );

        return [$tenant, $tenant->owner()->user];
    }

    public function test_owner_can_submit_a_branch_which_starts_pending_approval(): void
    {
        [$business, $owner] = $this->registerBusiness();

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($owner->activeMembership());

        Livewire::test('pages::tenant.branches.index')
            ->set('name', 'Branch 2 - Downtown')
            ->set('branch_code', 'BR-002')
            ->set('branch_address', '123 Main St')
            ->call('create')
            ->assertHasNoErrors();

        $branch = Tenant::where('name', 'Branch 2 - Downtown')->firstOrFail();

        $this->assertTrue($branch->isBranch());
        $this->assertSame($business->id, $branch->parent_tenant_id);
        $this->assertSame(BranchStatus::PendingApproval, $branch->branch_status);
        $this->assertFalse($branch->isOperational());
        $this->assertNotNull($branch->settings);
        // The active TenantContext here is still the root tenant, and
        // PaymentMethod is tenant-scoped — read the branch's own row via
        // the explicit cross-tenant escape hatch rather than the scoped
        // relation, which would (correctly) come back empty here.
        $this->assertSame(1, PaymentMethod::forTenant($branch)->count());
    }

    public function test_a_pending_branch_cannot_be_logged_into(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);

        $branch = $branchService->createBranch($business, $owner, ['name' => 'Pending Branch']);

        // A second user whose ONLY membership is this still-pending branch.
        $staff = User::factory()->create();
        $branch->memberships()->create(['user_id' => $staff->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($staff)->get('/app/pos')->assertForbidden();
    }

    public function test_admin_can_approve_a_branch_making_it_operational(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.branches.index')
            ->set('status', 'pending_approval')
            ->call('approve', $branch->id)
            ->assertHasNoErrors();

        $branch->refresh();
        $this->assertSame(BranchStatus::Active, $branch->branch_status);
        $this->assertNotNull($branch->branch_approved_at);
        $this->assertTrue($branch->isOperational());
    }

    public function test_admin_can_reject_a_branch_with_a_reason(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.branches.index')
            ->call('openReject', $branch->id)
            ->set('rejection_reason', 'Duplicate address on file.')
            ->call('reject')
            ->assertHasNoErrors();

        $branch->refresh();
        $this->assertSame(BranchStatus::Rejected, $branch->branch_status);
        $this->assertSame('Duplicate address on file.', $branch->branch_rejection_reason);
        $this->assertFalse($branch->isOperational());
    }

    public function test_a_rejected_branch_still_lets_the_business_owner_log_into_the_root(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);
        $branchService->reject($branch, User::factory()->create(['is_platform_admin' => true]), 'no');

        // The owner also has a membership on the root, which is unaffected.
        $this->actingAs($owner)->get('/app/dashboard')->assertOk();
    }

    public function test_suspending_the_business_makes_its_approved_branches_non_operational_too(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);
        $branchService->approve($branch, User::factory()->create(['is_platform_admin' => true]));

        $this->assertTrue($branch->fresh()->isOperational());

        $business->update(['status' => TenantStatus::Suspended]);

        $this->assertFalse($branch->fresh()->isOperational());
    }

    public function test_branches_of_the_same_business_keep_fully_isolated_product_data(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);
        $branchService->approve($branch, User::factory()->create(['is_platform_admin' => true]));

        // Root's own product.
        app(TenantContext::class)->setMembership($owner->memberships()->where('tenant_id', $business->id)->first());
        $rootProduct = Product::factory()->for($business)->create(['name' => 'Root Product']);
        app(ProductInventoryService::class)->adjust($rootProduct, 5, ProductInventoryMovementType::StockAdded);

        // Branch's own product, under the same owner switched into the branch.
        app(TenantContext::class)->setMembership($owner->memberships()->where('tenant_id', $branch->id)->first());
        $branchProduct = Product::factory()->for($branch)->create(['name' => 'Branch Product']);
        app(ProductInventoryService::class)->adjust($branchProduct, 3, ProductInventoryMovementType::StockAdded);

        $this->assertSame(1, Product::query()->count());
        $this->assertSame('Branch Product', Product::first()->name);

        app(TenantContext::class)->setMembership($owner->memberships()->where('tenant_id', $business->id)->first());
        $this->assertSame(1, Product::query()->count());
        $this->assertSame('Root Product', Product::first()->name);
    }

    public function test_switch_branch_only_allows_switching_to_a_membership_the_user_actually_has(): void
    {
        [$business, $owner] = $this->registerBusiness();
        $branchService = app(BranchService::class);
        $branch = $branchService->createBranch($business, $owner, ['name' => 'Branch 2']);
        $branchService->approve($branch, User::factory()->create(['is_platform_admin' => true]));

        $otherTenant = Tenant::factory()->create();

        $this->actingAs($owner)
            ->post('/switch-branch', ['tenant_id' => $otherTenant->id])
            ->assertRedirect();

        $this->assertNotSame($otherTenant->id, session('current_tenant_id'));

        $this->actingAs($owner)
            ->post('/switch-branch', ['tenant_id' => $branch->id])
            ->assertRedirect();

        $this->assertSame($branch->id, session('current_tenant_id'));
    }
}
