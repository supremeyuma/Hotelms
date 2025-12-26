<?php
// app/Services/RoomAvailabilityService.php

namespace App\Services;

use App\Models\Room;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * RoomAvailabilityService
 *
 * Provides availability checks, conflict detection, and suggestions.
 */
class RoomAvailabilityService
{
    /**
     * Inventory-based availability check (quantity driven)
     */
    public function checkAvailability(
        int $roomTypeId,
        string $checkIn,
        string $checkOut,
        int $quantity
    ): bool {
        $totalRooms = Room::where('room_type_id', $roomTypeId)->count();

        $reservedRooms = Booking::where('room_type_id', $roomTypeId)
            ->whereIn('status', ['pending_payment', 'confirmed'])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(fn ($q) =>
                $q->where('check_in', '<', $checkOut)
                  ->where('check_out', '>', $checkIn)
            )
            ->sum('quantity');

        return ($totalRooms - $reservedRooms) >= $quantity;
    }

    /**
     * Lock and fetch physical rooms for CHECK-IN
     * Rooms are assigned ONLY here
     */
    public function lockRoomsForCheckIn(
        int $roomTypeId,
        string $checkIn,
        string $checkOut,
        int $limit
    ): Collection {
        return Room::where('room_type_id', $roomTypeId)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('bookings.status', ['checked_in', 'confirmed'])
                  ->where(fn ($q) =>
                      $q->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn)
                  );
            })
            ->lockForUpdate()
            ->limit($limit)
            ->get();
    }

    /**
     * Count available rooms for a room type between dates.
     */
    public function availableRooms(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)->count();

        $bookedRooms = Booking::whereHas('rooms', function ($q) use ($roomTypeId) {
                $q->where('room_type_id', $roomTypeId);
            })
            ->whereIn('bookings.status', ['confirmed', 'pending_payment'])
            ->where(function ($q) {
                $q->whereNull('bookings.expires_at')
                  ->orWhere('bookings.expires_at', '>', Carbon::now());
            })
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('bookings.check_in', '<', $checkOut)
                  ->where('bookings.check_out', '>', $checkIn);
            })
            ->count();

        return max($totalRooms - $bookedRooms, 0);
    }

    /**
     * Get specific available rooms for a type (for allocation)
     */
    public function getAvailableRoomsForType(int $roomTypeId, string $checkIn, string $checkOut, int $limit): Collection
    {
        return Room::where('room_type_id', $roomTypeId)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('bookings.status', ['confirmed', 'pending_payment'])
                  ->where(function ($q2) use ($checkIn, $checkOut) {
                      $q2->where('bookings.check_in', '<', $checkOut)
                         ->where('bookings.check_out', '>', $checkIn);
                  });
            })
            ->lockForUpdate()
            ->limit($limit)
            ->get();
    }

    /**
     * Bulk availability for date range (for all rooms or filtered by type)
     */
    public function bulkAvailability(string $from, string $to, ?int $roomTypeId = null): array
    {
        $rooms = Room::when($roomTypeId, fn($q) => $q->where('room_type_id', $roomTypeId))->get();
        $res = [];

        foreach ($rooms as $room) {
            $res[$room->id] = $this->isRoomAvailable($room->id, $from, $to);
        }

        return $res;
    }

    /**
     * Single-room availability check (used for swaps & extensions)
     */
    public function isRoomAvailable(
        int $roomId,
        string $from,
        string $to
    ): bool {
        return ! Booking::whereHas('rooms', fn ($q) =>
                $q->where('rooms.id', $roomId)
            )
            ->whereIn('status', ['checked_in', 'confirmed'])
            ->where(fn ($q) =>
                $q->where('check_in', '<', $to)
                  ->where('check_out', '>', $from)
            )
            ->exists();
    }
}
