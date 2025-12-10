<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryPolicy
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
        // Staff can view inventory
        return $user->hasAnyRole(['staff']);
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasAnyRole(['staff']);
    }

    public function create(User $user): bool
    {
        // Only inventory managers (role) or manager/md
        return $user->hasPermissionTo('manage inventory') || $user->hasAnyRole(['inventory_manager']);
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasPermissionTo('manage inventory') || $user->hasAnyRole(['inventory_manager']);
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasPermissionTo('manage inventory');
    }

    public function restore(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function forceDelete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasRole('md');
    }

    /**
     * Only inventory staff and managers can deduct stock
     */
    public function adjustStock(User $user): bool
    {
        return $user->hasPermissionTo('manage inventory') || $user->hasAnyRole(['inventory_manager']);
    }
}
