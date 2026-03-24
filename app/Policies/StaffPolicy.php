<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaffPolicy
{
    use HandlesAuthorization;

    /**
     * Allow MD and Managers full access
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'manager', 'hr'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Only managers/md can view all staff; staff can view limited self list - deny here
        return false;
    }

    public function view(User $user, User $staff): bool
    {
        // A staff member can view their own profile
        if ($user->id === $staff->id) {
            return true;
        }

        // Managers/md handled by before(); others cannot view other staff
        return false;
    }

    public function create(User $user): bool
    {
        // Only managers/md via before() can create staff
        return false;
    }

    public function update(User $user, User $staff): bool
    {
        // Staff can update their own profile
        if ($user->id === $staff->id) {
            return true;
        }
        return false;
    }

    public function delete(User $user, User $staff): bool
    {
        // Only managers/md via before()
        return false;
    }

    public function restore(User $user, User $staff): bool
    {
        return $user->hasAnyRole(['md','manager', 'hr']);
    }

    public function forceDelete(User $user, User $staff): bool
    {
        return $user->hasRole('md');
    }
}
