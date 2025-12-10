<?php
// app/Services/BookingService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Exception;

/**
 * BookingService
 *
 * Encapsulates booking lifecycle: checks, create, modify, cancel, check-in/out.
 */
class BookingService
{
    protected RoomAvailabilityService $availability;
    protected AuditLoggerService $audit;

    public function __construct(RoomAvailabilityService $availability, AuditLoggerService $audit)
    {
        $this->availability = $availability;
        $this->audit = $audit;
    }

    /**
     * Check if room is available for date range.
     *
     * @param int $roomId
     * @param string|\DateTime $from
     * @param string|\DateTime $to
     * @return bool
     */
    public function isAvailable(int $roomId, $from, $to): bool
    {
        return $this->availability->isRoomAvailable($roomId, $from, $to);
    }

    /**
     * Create a booking and optionally assign room(s).
     *
     * @param array $data
     * @return Booking
     * @throws Exception
     */
    public function createBooking(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            // Validate room availability before creating
            if (isset($data['room_id'])) {
                $available = $this->isAvailable($data['room_id'], $data['check_in'], $data['check_out']);
                if (! $available) {
                    throw new Exception('Room is not available for the selected dates.');
                }
            }

            $booking = Booking::create([
                'property_id' => $data['property_id'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'booking_code' => $data['booking_code'] ?? strtoupper('BKG-'.uniqid()),
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'guests' => $data['guests'] ?? 1,
                'total_amount' => $data['total_amount'] ?? 0,
                'status' => $data['status'] ?? 'pending',
                'details' => $data['details'] ?? null,
            ]);

            $this->audit->log('booking_created', $booking, $booking->id, ['payload' => $data]);

            return $booking;
        });
    }

    /**
     * Update existing booking.
     *
     * @param Booking $booking
     * @param array $data
     * @return Booking
     * @throws Exception
     */
    public function updateBooking(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data) {
            $before = $booking->toArray();

            if (isset($data['room_id'])) {
                $available = $this->isAvailable($data['room_id'], $data['check_in'] ?? $booking->check_in, $data['check_out'] ?? $booking->check_out);
                if (! $available) {
                    throw new Exception('Requested room is not available for the new dates.');
                }
            }

            $booking->update($data);

            $this->audit->logChange('booking_updated', $booking, $before, $booking->toArray());

            return $booking;
        });
    }

    /**
     * Cancel a booking (soft-delete or status change).
     *
     * @param Booking $booking
     * @param string $reason
     * @return Booking
     */
    public function cancelBooking(Booking $booking, string $reason = ''): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {
            $before = $booking->toArray();
            $booking->update(['status' => 'cancelled']);
            $booking->delete();

            $this->audit->log('booking_cancelled', $booking, $booking->id, ['reason' => $reason, 'before' => $before]);

            return $booking;
        });
    }

    /**
     * Check-in workflow
     *
     * @param Booking $booking
     * @param User|null $by
     * @return Booking
     */
    public function checkIn(Booking $booking, ?User $by = null): Booking
    {
        $booking->update(['status' => 'checked_in', 'checked_in_at' => Carbon::now()]);
        $this->audit->log('booking_checked_in', $booking, $booking->id, ['by' => $by?->id ?? null]);

        return $booking;
    }

    /**
     * Check-out workflow
     *
     * @param Booking $booking
     * @param User|null $by
     * @return Booking
     */
    public function checkOut(Booking $booking, ?User $by = null): Booking
    {
        $booking->update(['status' => 'checked_out', 'checked_out_at' => Carbon::now()]);
        $this->audit->log('booking_checked_out', $booking, $booking->id, ['by' => $by?->id ?? null]);

        return $booking;
    }

    /**
     * List bookings with filters & pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = Booking::with(['room.roomType','user'])->latest();

        if (!empty($filters['status'])) $q->where('status', $filters['status']);
        if (!empty($filters['from'])) $q->whereDate('check_in', '>=', $filters['from']);
        if (!empty($filters['to'])) $q->whereDate('check_out', '<=', $filters['to']);
        if (!empty($filters['room_id'])) $q->where('room_id', $filters['room_id']);

        return $q->paginate($perPage);
    }
}
