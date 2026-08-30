<?php

namespace App\Services;

use App\Enums\BranchStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Mail\BranchApprovedMail;
use App\Mail\BranchRejectedMail;
use App\Mail\BranchSubmittedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SafeMailer;
use Illuminate\Support\Facades\DB;

/**
 * A "branch" is just another Tenant row linked to a business's root tenant
 * via parent_tenant_id — see the migration and Tenant::isBranch() for why.
 * This keeps every existing tenant-scoped feature (products, POS,
 * inventory, sales, expenses, kitchen, users) isolating branch data for
 * free, with no changes to any of that code.
 */
class BranchService
{
    /**
     * Create a new branch under $business's root tenant, owned (in the
     * TenantMembership sense) by $creator. Starts life as Pending Approval
     * — see Tenant::isOperational(), which keeps it unreachable until a
     * Platform Admin approves it.
     *
     * @param  array{name: string, branch_code?: ?string, branch_address?: ?string, branch_contact_number?: ?string, branch_contact_email?: ?string, manager_user_id?: ?int, logo_path?: ?string}  $data
     */
    public function createBranch(Tenant $business, User $creator, array $data): Tenant
    {
        $root = $business->businessRoot();

        $branch = DB::transaction(function () use ($root, $creator, $data) {
            $branch = Tenant::create([
                'name' => $data['name'],
                'slug' => Tenant::uniqueSlug($data['name']),
                'timezone' => $root->timezone,
                'status' => TenantStatus::Active,
                'parent_tenant_id' => $root->id,
                'branch_code' => $data['branch_code'] ?? null,
                'branch_address' => $data['branch_address'] ?? null,
                'branch_contact_number' => $data['branch_contact_number'] ?? null,
                'branch_contact_email' => $data['branch_contact_email'] ?? null,
                'manager_user_id' => $data['manager_user_id'] ?? $creator->id,
                'branch_status' => BranchStatus::PendingApproval,
                'logo_path' => $data['logo_path'] ?? null,
            ]);

            $branch->settings()->create([]);

            $branch->paymentMethods()->create([
                'name' => 'Cash',
                'is_enabled' => true,
                'sort_order' => 0,
            ]);

            $branch->memberships()->create([
                'user_id' => $creator->id,
                'role' => TenantMembershipRole::Owner,
            ]);

            $managerId = $data['manager_user_id'] ?? null;

            if ($managerId && $managerId !== $creator->id) {
                $branch->memberships()->firstOrCreate(
                    ['user_id' => $managerId],
                    ['role' => TenantMembershipRole::Owner],
                );
            }

            return $branch;
        });

        SafeMailer::send($creator->email, new BranchSubmittedMail($branch));

        return $branch;
    }

    public function approve(Tenant $branch, User $admin): void
    {
        $branch->update([
            'branch_status' => BranchStatus::Active,
            'branch_approved_at' => now(),
            'branch_approved_by' => $admin->id,
            'branch_rejection_reason' => null,
        ]);

        if ($ownerEmail = $branch->manager?->email) {
            SafeMailer::send($ownerEmail, new BranchApprovedMail($branch));
        }
    }

    public function reject(Tenant $branch, User $admin, string $reason): void
    {
        $branch->update([
            'branch_status' => BranchStatus::Rejected,
            'branch_rejection_reason' => $reason,
            'branch_approved_by' => $admin->id,
            'branch_approved_at' => now(),
        ]);

        if ($ownerEmail = $branch->manager?->email) {
            SafeMailer::send($ownerEmail, new BranchRejectedMail($branch, $reason));
        }
    }

    public function suspend(Tenant $branch): void
    {
        $branch->update(['branch_status' => BranchStatus::Suspended]);
    }

    public function reactivate(Tenant $branch): void
    {
        $branch->update(['branch_status' => BranchStatus::Active]);
    }
}
