<?php
// app/Services/RoomAvailabilityService.php

namespace App\Services;

use App\Models\Room;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\RoomType;

/**
 * RoomAvailabilityService
 *
 * Provides availability checks, conflict detection, and suggestions.
 */
class RoomAvailabilityService
{
    /**
     * Check if room is available for date range.
     *
     * @param int $roomId
     * @param string|\DateTime $from
     * @param string|\DateTime $to
     * @return bool
     */
    public function checkAvailability(int $roomTypeId, string $checkIn, string $checkOut, int $requestedQuantity): bool
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)->count();

        $bookedRooms = Booking::whereHas('room', function ($query) use ($roomTypeId) {
                $query->where('room_type_id', $roomTypeId);
            })
            ->whereIn('status', ['confirmed', 'pending_payment'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in', '<', $checkOut)
                      ->where('check_out', '>', $checkIn);
            })
            ->count();

            //dd($totalRooms);

        return ($totalRooms - $bookedRooms) >= $requestedQuantity;
    }

    /**
     * Find alternative rooms for date range and desired occupancy/type
     *
     * @param string|\DateTime $from
     * @param string|\DateTime $to
     * @param array $criteria ['room_type_id' => int, 'property_id' => int]
     * @return Collection
     */
    public function availableRooms(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $totalRooms = Room::where('room_type_id', $roomTypeId)->count();
        
        $bookedRooms = Booking::whereHas('room', function ($query) use ($roomTypeId) {
                $query->where('room_type_id', $roomTypeId);
            })
            ->whereIn('status', ['confirmed', 'pending_payment'])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in', '<', $checkOut)
                      ->where('check_out', '>', $checkIn);
            })
            ->count();

            //dd($totalRooms);

        return max($totalRooms - $bookedRooms, 0);
    }

    public function getAvailableRoomsForType(
        int $roomTypeId,
        $checkIn,
        $checkOut,
        int $limit
    ): Collection {
        return Room::where('room_type_id', $roomTypeId)
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('status', ['confirmed', 'pending_payment'])
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn);
                });
            })
            ->lockForUpdate()
            ->limit($limit)
            ->get();
    }


    /**
     * Bulk availability for date range
     *
     * @param string $from
     * @param string $to
     * @param int|null $roomTypeId
     * @return array [room_id => available(bool)]
     */
    public function bulkAvailability(string $from, string $to, ?int $roomTypeId = null): array
    {
        $rooms = Room::when($roomTypeId, fn($q) => $q->where('room_type_id', $roomTypeId))->get();
        $res = [];
        foreach ($rooms as $r) {
            $res[$r->id] = $this->isRoomAvailable($r->id, $from, $to);
        }
        return $res;
    }

     public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut): bool
    {
        $conflictingBookings = Booking::whereHas('rooms', function ($query) use ($roomId) {
                $query->where('rooms.id', $roomId);
            })
            ->where('status', 'active')
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                  ->orWhereBetween('check_out', [$checkIn, $checkOut])
                  ->orWhere(function ($q2) use ($checkIn, $checkOut) {
                      $q2->where('check_in', '<', $checkIn)
                         ->where('check_out', '>', $checkOut);
                  });
            })
            ->exists();

        return ! $conflictingBookings;
    }
}
