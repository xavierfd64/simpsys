<?php

namespace App\Enums;

enum TenantMembershipStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
