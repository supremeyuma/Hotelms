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
use Log;

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

            // 1. Fetch and lock available rooms
            $rooms = $this->availability->getAvailableRoomsForType(
                $data['room_type_id'],
                $data['check_in'],
                $data['check_out'],
                $data['quantity']
            );

            if ($rooms->count() < $data['quantity']) {
                throw new Exception('Not enough rooms available for the selected dates.');
            }

            // 2. Create booking
            $booking = Booking::create([
                'property_id' => $data['property_id'] ?? 1,
                'user_id' => $data['user_id'] ?? null,
                'booking_code' => $data['booking_code'] ?? strtoupper('BKG-'.uniqid()),
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'adults' => $data['adults'],
                'children' => $data['children'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'] ?? 'pending_payment',
                'details' => $data['details'] ?? null,
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'],
                'special_requests' => $data['special_requests'] ?? null,
                'expires_at' => Carbon::now()->addMinutes(45),
                'guest_name' => $data['guest_name'],
                'room_type_id' => $data['room_type_id'],
                'quantity' => $data['quantity'],
                'nightly_rate' => $data['nightly_rate'],
            ]);

            // 3. Attach rooms (THIS assigns room numbers)
            $booking->rooms()->attach($rooms->pluck('id'));

            // 4. Audit
            $this->audit->log(
                'booking_created',
                $booking,
                $booking->id,
                ['rooms' => $rooms->pluck('room_number')->toArray()]
            );

            return $booking;
        });
    }


    public function confirmBooking(Booking $booking)
    {
        $booking->update([
            'status' => 'confirmed',
            'expires_at' => null
        ]);

        // Trigger email/SMS hook here
        //\Mail::to($booking->guest_email)->send(new \App\Mail\BookingConfirmed($booking));

        // SMS (example using a service like Twilio)
        // Twilio::message($booking->guest_phone, "Your booking #{$booking->id} is confirmed.");

        return $booking;
    }

    public function cancelExpiredBookings()
    {
        Booking::where('status', 'pending_payment')
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => 'cancelled']);
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
        DB::transaction(function () use ($booking, $by) {

            foreach ($booking->rooms as $room) {
                if ($room->pivot->status === 'active') {
                    app(RoomCheckoutService::class)
                        ->checkoutRoom($booking, $room, $by);
                }
            }

            $booking->update([
                'status' => 'checked_out',
                'checked_out_at' => now()
            ]);

            $this->audit->log(
                'booking_checked_out',
                $booking,
                $booking->id,
                ['by' => $by?->id]
            );
        });

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
