<?php

namespace App\Policies;

use App\Models\GuestRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GuestRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Super-admin bypass, matching the convention in every other policy here.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasRole('md')) {
            return true;
        }
    }

    /**
     * A guest request belongs to one booking (booking_id + room_id, both
     * non-null). Front desk sees every booking, so there is no per-user
     * scoping on the collection; the rule is a role gate only.
     *
     * Confirmed 2026-10-05: frontdesk + md. Fulfilment departments
     * (housekeeping, laundry, kitchen, bar) intentionally get no access.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['frontdesk', 'md']);
    }

    public function view(User $user, GuestRequest $request): bool
    {
        return $user->hasAnyRole(['frontdesk', 'md']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['frontdesk', 'md']);
    }

    public function update(User $user, GuestRequest $request): bool
    {
        return $user->hasAnyRole(['frontdesk', 'md']);
    }

    /**
     * Backs GuestRequestController::acknowledge().
     */
    public function acknowledge(User $user, GuestRequest $request): bool
    {
        return $user->hasAnyRole(['frontdesk', 'md']);
    }

    /**
     * Backs GuestRequestController::complete().
     */
    public function resolve(User $user, GuestRequest $request): bool
    {
        return $user->hasAnyRole(['frontdesk', 'md']);
    }

    public function delete(User $user, GuestRequest $request): bool
    {
        return $user->hasAnyRole(['md']);
    }
}
