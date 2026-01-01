<?php

namespace App\Services;

use App\Models\User;

class RoleRedirectService
{
    public function redirectPath(User $user): string
    {
        if ($user->hasRole('md') || $user->hasRole('ceo')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('manager')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('frontdesk')) {
            return route('frontdesk.dashboard');
        }

        if ($user->hasRole('laundry')) {
            return route('staff.laundry.dashboard');
        }

        if ($user->hasRole('cleaner')) {
            return route('cleaning.dashboard');
        }

        if ($user->hasRole('staff')) {
            return route('staff.dashboard');
        }
        if ($user->hasRole('kitchen')) {
            return route('staff.kitchen.dashboard');
        }
        if ($user->hasRole('bar')) {
            return route('staff.bar.dashboard');
        }

        // Fallback (should never happen)
        return route('login');
    }
}
