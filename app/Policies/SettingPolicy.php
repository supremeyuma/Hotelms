<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SettingPolicy
{
    use HandlesAuthorization;

    /**
     * Only MD (super-admin) should bypass checks here.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'superuser'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('manager');
    }

    public function view(User $user, $setting = null): bool
    {
        return $user->hasRole('manager');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('md');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('manager');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('md');
    }

    public function restore(User $user): bool
    {
        return $user->hasRole('md');
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasRole('md');
    }
}
