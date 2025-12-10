<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportPolicy
{
    use HandlesAuthorization;

    /**
     * Only managers and MD can access reports
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'manager'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Deny for non-managers
        return false;
    }

    public function view(User $user, $model = null): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return $user->hasAnyRole(['md', 'manager']);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('md');
    }

    public function restore(User $user): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasRole('md');
    }
}
