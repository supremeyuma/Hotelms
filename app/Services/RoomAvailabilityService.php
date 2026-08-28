<?php
// app/Services/RoomAvailabilityService.php

namespace App\Services;

use App\Models\Room;
use App\Models\Booking;
use App\Models\RoomTypePriceSchedule;
use App\Models\RoomAvailabilitySchedule;
use App\Services\RoomSchedulingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * RoomAvailabilityService
 *
 * Provides availability checks, conflict detection, and suggestions.
 */
class RoomAvailabilityService
{
    protected RoomSchedulingService $scheduling;

    public function __construct(RoomSchedulingService $scheduling)
    {
        $this->scheduling = $scheduling;
    }

    protected function bookableStatuses(): array
    {
        return ['available', 'dirty'];
    }

    protected function reservingStatuses(): array
    {
        return ['pending_payment', 'confirmed', 'active', 'checked_in'];
    }

protected function activeReservationQuery(string $checkIn, string $checkOut, ?int $excludeBookingId = null)
    {
        return Booking::query()
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->whereIn('status', $this->reservingStatuses())
            ->where(function ($query) {
                $query->where('status', '!=', 'pending_payment')
                    ->orWhere(function ($pending) {
                        $pending->where('status', 'pending_payment')
                            ->where(function ($expiry) {
                                $expiry->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->where('check_in', '<', $checkOut)
                    ->where('check_out', '>', $checkIn);
            });
    }

    /**
     * True when an active unavailability schedule blocks the given room
     * (directly, or via its room type) for any night in [from, to).
     */
    protected function isBlockedBySchedule(int $roomId, ?int $roomTypeId, string $from, string $to): bool
    {
        return RoomAvailabilitySchedule::unavailable()
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->where(function ($query) use ($roomId, $roomTypeId) {
                $query->where('room_id', $roomId)
                    ->when($roomTypeId, fn ($q) => $q->orWhere('room_type_id', $roomTypeId));
            })
            ->exists();
    }

    /**
     * Count rooms of a type that are blocked by unavailability schedules
     * for any night in [from, to).
     */
    protected function blockedRoomsForType(int $roomTypeId, string $from, string $to): int
    {
        $ids = Room::where('room_type_id', $roomTypeId)->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        $roomTypeLevel = RoomAvailabilitySchedule::unavailable()
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->where('room_type_id', $roomTypeId)
            ->exists();

        if ($roomTypeLevel) {
            return count($ids);
        }

        return RoomAvailabilitySchedule::unavailable()
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->whereIn('room_id', $ids)
            ->distinct()
            ->count('room_id');
    }

    /**
     * Inventory-based availability check (quantity driven)
     */
    public function checkAvailability(
        int $roomTypeId,
        string $checkIn,
        string $checkOut,
        int $quantity
    ): bool {
        $totalRooms = Room::where('room_type_id', $roomTypeId)
            ->whereIn('status', $this->bookableStatuses())
            ->count();

$reservedRooms = $this->activeReservationQuery($checkIn, $checkOut)
            ->where('room_type_id', $roomTypeId)
            ->sum('quantity');

        $blockedRooms = $this->blockedRoomsForType($roomTypeId, $checkIn, $checkOut);

        return ($totalRooms - $reservedRooms - $blockedRooms) >= $quantity;
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
            ->where('status', 'available')
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('bookings.status', ['active', 'checked_in', 'confirmed'])
                  ->where(fn ($q) =>
                      $q->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn)
                  );
            })
            ->lockForUpdate()
            ->limit($limit)
            ->get()
            ->reject(fn ($room) => $this->isBlockedBySchedule($room->id, $room->room_type_id, $checkIn, $checkOut));
    }

    /**
     * Count available rooms for a room type between dates.
     */
    public function availableRooms(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        return $this->getAvailableRoomsForType($roomTypeId, $checkIn, $checkOut)->count();
    }

    /**
     * Get specific available rooms for a type (for allocation)
     */
    public function getAvailableRoomsForType(int $roomTypeId, string $checkIn, string $checkOut, ?int $limit = null): Collection
    {
        $query = Room::where('room_type_id', $roomTypeId)
            ->whereIn('status', $this->bookableStatuses())
            ->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('bookings.status', $this->reservingStatuses())
                    ->where(function ($query) {
                        $query->where('bookings.status', '!=', 'pending_payment')
                            ->orWhere(function ($pending) {
                                $pending->where('bookings.status', 'pending_payment')
                                    ->where(function ($expiry) {
                                        $expiry->whereNull('bookings.expires_at')
                                            ->orWhere('bookings.expires_at', '>', Carbon::now());
                                    });
                            });
                    })
                    ->where(function ($q2) use ($checkIn, $checkOut) {
                        $q2->where('bookings.check_in', '<', $checkOut)
                            ->where('bookings.check_out', '>', $checkIn);
                    });
            })
            ->lockForUpdate();

if ($limit) {
            $query->limit($limit);
        }

        return $query->get()
            ->reject(fn ($room) => $this->isBlockedBySchedule($room->id, $room->room_type_id, $checkIn, $checkOut));
    }

    public function areRoomsAvailable(array $roomIds, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $roomIds = array_values(array_unique(array_map('intval', $roomIds)));

        if ($roomIds === []) {
            return false;
        }

$conflicts = $this->activeReservationQuery($checkIn, $checkOut, $excludeBookingId)
            ->whereHas('rooms', fn ($query) => $query->whereIn('rooms.id', $roomIds))
            ->count();

        if ($conflicts !== 0) {
            return false;
        }

        $roomTypeMap = Room::whereIn('id', $roomIds)->pluck('room_type_id', 'id');

        foreach ($roomIds as $roomId) {
            if ($this->isBlockedBySchedule($roomId, $roomTypeMap[$roomId] ?? null, $checkIn, $checkOut)) {
                return false;
            }
        }

        return true;
    }

    public function getAvailableRoomsByIds(array $roomIds, string $checkIn, string $checkOut, ?int $excludeBookingId = null): Collection
    {
        $roomIds = array_values(array_unique(array_map('intval', $roomIds)));

        if ($roomIds === []) {
            return collect();
        }

        $allowedStatuses = $excludeBookingId
            ? array_unique([...$this->bookableStatuses(), 'reserved', 'occupied'])
            : $this->bookableStatuses();

        return Room::whereIn('id', $roomIds)
            ->whereIn('status', $allowedStatuses)
            ->whereDoesntHave('bookings', function ($query) use ($checkIn, $checkOut, $excludeBookingId) {
                $query->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', $excludeBookingId))
                    ->whereIn('bookings.status', $this->reservingStatuses())
                    ->where(function ($statusQuery) {
                        $statusQuery->where('bookings.status', '!=', 'pending_payment')
                            ->orWhere(function ($pending) {
                                $pending->where('bookings.status', 'pending_payment')
                                    ->where(function ($expiry) {
                                        $expiry->whereNull('bookings.expires_at')
                                            ->orWhere('bookings.expires_at', '>', Carbon::now());
                                    });
                            });
                    })
                    ->where(function ($dateQuery) use ($checkIn, $checkOut) {
                        $dateQuery->where('bookings.check_in', '<', $checkOut)
                            ->where('bookings.check_out', '>', $checkIn);
                    });
            })
            ->lockForUpdate()
            ->get();
    }

    /**
     * Bulk availability for date range (for all rooms or filtered by type)
     */
    public function bulkAvailability(string $from, string $to, ?int $roomTypeId = null): array
    {
        $rooms = Room::when($roomTypeId, fn($q) => $q->where('room_type_id', $roomTypeId))
            ->whereIn('status', $this->bookableStatuses())
            ->get();
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
        string $to,
        ?int $excludeBookingId = null
    ): bool {
        $room = Room::find($roomId);

        if (! $room) {
            return false;
        }

        $allowedStatuses = $excludeBookingId
            ? array_unique([...$this->bookableStatuses(), 'reserved', 'occupied'])
            : $this->bookableStatuses();

if (! in_array($room->status, $allowedStatuses, true)) {
            return false;
        }

        if ($this->isBlockedBySchedule($room->id, $room->room_type_id, $from, $to)) {
            return false;
        }

        return ! $this->activeReservationQuery($from, $to, $excludeBookingId)
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $roomId))
            ->exists();
    }
}
