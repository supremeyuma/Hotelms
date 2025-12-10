<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admin bypass (MD and Manager).
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'manager'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Staff and guests can view room listings; controllers may show public pages without policy
        return $user->hasAnyRole(['staff', 'manager', 'md', 'guest']);
    }

    public function view(User $user, Room $room): bool
    {
        // Anyone in the system can view room details (admins/managers bypassed above)
        return $user->hasAnyRole(['staff', 'guest']);
    }

    public function create(User $user): bool
    {
        // Only manager/md allowed by before(); other roles cannot create
        return false;
    }

    public function update(User $user, Room $room): bool
    {
        // Only manager/md via before() can update
        return false;
    }

    public function delete(User $user, Room $room): bool
    {
        // Only manager/md via before() can delete
        return false;
    }

    public function restore(User $user, Room $room): bool
    {
        return $user->hasAnyRole(['md', 'manager']);
    }

    public function forceDelete(User $user, Room $room): bool
    {
        return $user->hasRole('md');
    }
}
