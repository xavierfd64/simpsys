<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
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
            self::Trial => 'bg-blue-50 text-blue-700',
            self::Active => 'bg-green-50 text-green-700',
            self::Expired => 'bg-amber-50 text-amber-700',
            self::Suspended, self::Cancelled => 'bg-red-50 text-red-700',
        };
    }
}
