<?php

namespace App\Services;

use App\Models\User;

class RoleRedirectService
{
    public function redirectPath(User $user): string
    {
        if ($user->hasRole('superuser') || $user->hasRole('md') || $user->hasRole('ceo')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('manager')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('hr')) {
            return route('hr.staff.index');
        }

        if ($user->hasAnyRole(['accountant', 'Accountant'])) {
            return route('finance.dashboard');
        }

        if ($user->hasRole('frontdesk')) {
            return route('frontdesk.dashboard');
        }

        if ($user->hasRole('laundry')) {
            return route('staff.laundry.dashboard');
        }

        if ($user->hasRole('clean')) {
            return route('clean.dashboard');
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
