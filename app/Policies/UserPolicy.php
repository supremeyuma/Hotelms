<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * MD has full access to manage users.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'superuser'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Managers can view staff lists; normal staff cannot.
        return $user->hasAnyRole(['manager', 'hr']);
    }

    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) return true;

        // Managers can view staff
        if ($user->hasAnyRole(['manager', 'hr'])) return true;

        return false;
    }

    public function create(User $user): bool
    {
        // Only managers/md can create new users
        return $user->hasAnyRole(['manager', 'hr']);
    }

    public function update(User $user, User $model): bool
    {
        // Users can update their own profile; managers can update any user
        if ($user->id === $model->id) return true;
        return $user->hasAnyRole(['manager', 'hr']);
    }

    public function delete(User $user, User $model): bool
    {
        // Managers cannot delete MD; only MD can permanently delete
        if ($model->hasRole('md')) return false;
        return $user->hasAnyRole(['manager', 'hr']);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasAnyRole(['md', 'manager', 'hr']);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasRole('md');
    }
}
