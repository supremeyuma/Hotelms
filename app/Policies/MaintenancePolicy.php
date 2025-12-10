<?php

namespace App\Policies;

use App\Models\MaintenanceTicket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenancePolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'manager'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Maintenance staff can view tickets
        return $user->hasAnyRole(['staff']);
    }

    public function view(User $user, MaintenanceTicket $ticket): bool
    {
        // Assigned staff, ticket reporter, or maintenance staff can view
        if ($ticket->staff_id === $user->id) return true;
        if ($user->id === $ticket->reporter_id) return true;
        return $user->hasPermissionTo('manage maintenance') || ($user->staffProfile && $user->staffProfile->department === 'maintenance');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['staff', 'manager', 'md']);
    }

    public function update(User $user, MaintenanceTicket $ticket): bool
    {
        // Assigned maintenance staff or managers can update
        if ($ticket->staff_id === $user->id) return true;
        return $user->hasPermissionTo('manage maintenance');
    }

    public function delete(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function restore(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function forceDelete(User $user, MaintenanceTicket $ticket): bool
    {
        return $user->hasRole('md');
    }
}
