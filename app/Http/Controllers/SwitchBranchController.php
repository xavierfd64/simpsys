<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the "current branch" session value used by IdentifyTenant to
 * pick which of the user's memberships to load. Deliberately not a
 * Livewire component — a plain POST + redirect is the simplest correct
 * way to change something IdentifyTenant reads on every subsequent
 * request, tenant/role middleware included.
 */
class SwitchBranchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['tenant_id' => ['required', 'integer']]);

        $user = $request->user();

        // Only ever switch to a tenant the user actually has a usable
        // membership on — never trust the posted id past that check.
        $membership = $user->usableMemberships()->firstWhere('tenant_id', (int) $request->input('tenant_id'));

        if ($membership) {
            $request->session()->put('current_tenant_id', $membership->tenant_id);
        }

        return redirect()->route($user->homeRouteName());
    }
}
