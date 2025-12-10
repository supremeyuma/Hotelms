<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookingPolicy
{
    use HandlesAuthorization;

    /**
     * Managers and MD have full access.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['md', 'manager'])) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        // Staff with booking permission allowed; guests not allowed to view all bookings
        return $user->hasAnyRole(['staff']);
    }

    public function view(User $user, Booking $booking): bool
    {
        // Owner can view own booking; staff may view if permission; managers bypassed above
        if ($booking->user_id && $user->id === $booking->user_id) {
            return true;
        }

        return $user->hasAnyRole(['staff']);
    }

    public function create(User $user): bool
    {
        // Guests (unauthenticated) may create via public flow; this policy applies to authenticated users
        return $user->hasAnyRole(['staff', 'manager', 'md']);
    }

    public function update(User $user, Booking $booking): bool
    {
        // Only managers/md via before() can update bookings in admin; staff cannot modify bookings except managers
        return false;
    }

    public function delete(User $user, Booking $booking): bool
    {
        // Only managers/md can delete/cancel bookings
        return false;
    }

    public function restore(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['md','manager']);
    }

    public function forceDelete(User $user, Booking $booking): bool
    {
        return $user->hasRole('md');
    }
}
