<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    /**
     * Only MD role (super admin) should be able to view audit logs.
     * Managers may also be allowed depending on business rules.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('md')) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Only manager or MD can view any audit logs if before didn't return true
        return $user->hasRole('manager');
    }

    public function view(User $user, $model = null): bool
    {
        return $user->hasRole('manager');
    }

    public function create(User $user): bool
    {
        // Audit logs are created by system; disallow via policy
        return false;
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
