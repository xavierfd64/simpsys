<?php

namespace App\Enums;

/**
 * The approval lifecycle for a branch (a non-root Tenant row — see
 * Tenant::isBranch()). Null on a root/business tenant, which continues to
 * use TenantStatus exclusively; this enum only applies to branches.
 */
enum BranchStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Full literal Tailwind class strings — Tailwind's build-time scanner
     * only picks up classes it can see spelled out in source, so these
     * cannot be assembled via string interpolation (e.g. "bg-{$color}-50").
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingApproval => 'bg-amber-50 text-amber-700',
            self::Active => 'bg-green-50 text-green-700',
            self::Rejected, self::Suspended => 'bg-red-50 text-red-700',
        };
    }
}
