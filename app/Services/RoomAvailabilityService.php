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
     * Check if room is available for date range.
     *
     * @param int $roomId
     * @param string|\DateTime $from
     * @param string|\DateTime $to
     * @return bool
     */
    public function isRoomAvailable(int $roomId, $from, $to): bool
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        $conflict = Booking::where('room_id', $roomId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('check_in', [$from, $to])
                  ->orWhereBetween('check_out', [$from, $to])
                  ->orWhere(function ($q2) use ($from, $to) {
                      $q2->where('check_in', '<=', $from)->where('check_out', '>=', $to);
                  });
            })->exists();

        return ! $conflict;
    }

    /**
     * Find alternative rooms for date range and desired occupancy/type
     *
     * @param string|\DateTime $from
     * @param string|\DateTime $to
     * @param array $criteria ['room_type_id' => int, 'property_id' => int]
     * @return Collection
     */
    public function suggestRooms($from, $to, array $criteria = []): Collection
    {
        $q = Room::with('roomType')->where('status', 'available');

        if (!empty($criteria['room_type_id'])) $q->where('room_type_id', $criteria['room_type_id']);
        if (!empty($criteria['property_id'])) $q->where('property_id', $criteria['property_id']);

        $rooms = $q->get()->filter(function (Room $room) use ($from, $to) {
            return $this->isRoomAvailable($room->id, $from, $to);
        });

        return $rooms;
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
}
