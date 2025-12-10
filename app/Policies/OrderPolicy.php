<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admin (MD / Manager) bypass.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'manager'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Staff with any role may attempt to view orders, but department-specific checks apply in custom methods
        return $user->hasAnyRole(['staff']);
    }

    public function view(User $user, Order $order): bool
    {
        // Guests can view their own order
        if ($order->user_id && $user->id === $order->user_id) {
            return true;
        }

        // Staff can view orders for their department
        return $this->isStaffForDepartment($user, $order->department);
    }

    public function create(User $user): bool
    {
        // Orders can be created by guests via public flow; authenticated staff may create orders for rooms
        return $user->hasAnyRole(['staff','manager','md']);
    }

    public function update(User $user, Order $order): bool
    {
        // Only staff assigned to department or managers can update
        return $this->isStaffForDepartment($user, $order->department);
    }

    public function delete(User $user, Order $order): bool
    {
        // Only managers/md can delete orders (handled by before)
        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasRole('md');
    }

    /**
     * Custom: can the user view queue for a department?
     */
    public function viewQueue(User $user, string $department): bool
    {
        if ($user->hasAnyRole(['md','manager'])) {
            return true;
        }
        return $this->isStaffForDepartment($user, $department);
    }

    /**
     * Check if user belongs to department (via staffProfile->department or role)
     */
    protected function isStaffForDepartment(User $user, ?string $department): bool
    {
        if ($user->hasRole('md') || $user->hasRole('manager')) {
            return true;
        }

        // Department stored on staffProfile->department (e.g., 'kitchen','laundry')
        $profile = $user->staffProfile;
        if (! $profile) return false;

        return (isset($profile->department) && $profile->department === $department) || $user->hasPermissionTo("manage {$department} orders");
    }
}
