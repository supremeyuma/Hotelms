<?php

namespace App\Policies;

use App\Models\RoomType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomTypePolicy
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
        return $user->hasAnyRole(['staff', 'guest', 'manager', 'md']);
    }

    public function view(User $user, RoomType $roomType): bool
    {
        return $user->hasAnyRole(['staff', 'guest']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RoomType $roomType): bool
    {
        return false;
    }

    public function delete(User $user, RoomType $roomType): bool
    {
        return false;
    }

    public function restore(User $user, RoomType $roomType): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function forceDelete(User $user, RoomType $roomType): bool
    {
        return $user->hasRole('md');
    }
}
