<?php

namespace App\Services;

use App\Models\RoomTypePriceSchedule;
use App\Models\RoomAvailabilitySchedule;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RoomSchedulingService
{
    /**
     * Get custom price for room type on specific date
     */
    public function getCustomPrice(int $roomTypeId, int $propertyId, Carbon $date): ?float
    {
        $schedule = RoomTypePriceSchedule::active()
            ->where('room_type_id', $roomTypeId)
            ->where('property_id', $propertyId)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->first();

        return $schedule?->custom_price;
    }

    /**
     * Check if room is available on specific date
     */
    public function isRoomAvailable(int $roomId, Carbon $date, ?int $excludeBookingId = null): bool
    {
        // Check room-specific unavailability schedules
        $roomUnavailable = RoomAvailabilitySchedule::unavailable()
            ->where('room_id', $roomId)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->exists();

        if ($roomUnavailable) {
            return false;
        }

        // Check if room has any active booking on this date
        $hasBooking = Room::where('id', $roomId)
            ->whereHas('bookings', function ($query) use ($date, $excludeBookingId) {
                $query->whereIn('status', ['pending_payment', 'confirmed', 'active', 'checked_in'])
                    ->where('check_in', '<=', $date->format('Y-m-d'))
                    ->where('check_out', '>', $date->format('Y-m-d'));
                
                if ($excludeBookingId) {
                    $query->where('id', '!=', $excludeBookingId);
                }
            })
            ->exists();

        return !$hasBooking;
    }

    /**
     * Get all unavailable dates for a room type
     */
    public function getUnavailableDatesForRoomType(int $roomTypeId, int $propertyId, Carbon $startDate, Carbon $endDate): array
    {
        $schedules = RoomAvailabilitySchedule::unavailable()
            ->where('room_type_id', $roomTypeId)
            ->where('property_id', $propertyId)
            ->where('start_date', '<=', $endDate->format('Y-m-d'))
            ->where('end_date', '>=', $startDate->format('Y-m-d'))
            ->get();

        $unavailableDates = [];

        foreach ($schedules as $schedule) {
            $current = clone $schedule->start_date;
            $end = $schedule->end_date;

            while ($current <= $end) {
                if ($current >= $startDate && $current <= $endDate) {
                    $unavailableDates[] = $current->format('Y-m-d');
                }
                $current->addDay();
            }
        }

        return $unavailableDates;
    }

    /**
     * Get custom price schedule for room type
     */
    public function getPriceSchedule(int $roomTypeId, int $propertyId, Carbon $date): ?array
    {
        $schedule = RoomTypePriceSchedule::active()
            ->where('room_type_id', $roomTypeId)
            ->where('property_id', $propertyId)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->first();

        if (!$schedule) {
            return null;
        }

        return [
            'start_date' => $schedule->start_date,
            'end_date' => $schedule->end_date,
            'custom_price' => $schedule->custom_price,
            'description' => $schedule->description,
        ];
    }
}