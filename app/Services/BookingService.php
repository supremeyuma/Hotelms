<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use App\Services\AuditLoggerService;
use App\Services\RoomCheckoutService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Exception;
use App\Models\Room;

/**
 * BookingService
 *
 * Encapsulates booking lifecycle: availability checks,
 * creation, modification, cancellation, check-in/out.
 */
class BookingService
{
    protected RoomAvailabilityService $availability;
    protected AuditLoggerService $audit;

    public function __construct(
        RoomAvailabilityService $availability,
        AuditLoggerService $audit
    ) {
        $this->availability = $availability;
        $this->audit = $audit;
    }

    /**
     * Check if a room is available for a date range.
     */
    public function isAvailable(int $roomId, $from, $to): bool
    {
        return $this->availability->isRoomAvailable($roomId, $from, $to);
    }

    /**
     * Create a booking and assign rooms immediately.
     *
     * @throws \Exception
     */
    public function createBooking(array $data): Booking
    {
        return DB::transaction(function () use ($data) {

            // Inventory-level availability check (NO rooms yet)
            $available = $this->availability->checkAvailability(
                $data['room_type_id'],
                $data['check_in'],
                $data['check_out'],
                $data['quantity']
            );

            if (! $available) {
                throw new \Exception('Not enough rooms available for the selected dates.');
            }

            $booking = Booking::create([
                'property_id'  => $data['property_id'] ?? 1,
                'user_id'      => $data['user_id'] ?? null,
                'booking_code' => strtoupper('BKG-' . uniqid()),
                'check_in'     => $data['check_in'],
                'check_out'    => $data['check_out'],
                'room_type_id' => $data['room_type_id'],
                'quantity'     => $data['quantity'],
                'adults'       => $data['adults'],
                'children'     => $data['children'],
                'nightly_rate' => $data['nightly_rate'],
                'total_amount' => $data['total_amount'],
                'status'       => 'pending_payment',
                'expires_at'   => now()->addMinutes(45),
                'guest_name'   => $data['guest_name'],
                'guest_email'  => $data['guest_email'],
                'guest_phone'  => $data['guest_phone'],
            ]);

            $this->audit->log(
                'booking_created',
                $booking,
                $booking->id,
                ['quantity' => $booking->quantity]
            );

            return $booking;
        });
    }


    /**
     * Confirm a booking.
     */
    public function confirmBooking(Booking $booking): Booking
    {
        $booking->update([
            'status'     => 'confirmed',
            'expires_at' => null,
        ]);

        $this->audit->log('booking_confirmed', $booking, $booking->id);

        return $booking;
    }


    /**
     * Cancel expired pending bookings.
     */
    public function cancelExpiredBookings(): void
    {
        Booking::where('status', 'pending_payment')
            ->where('expires_at', '<', now())
            ->update(['status' => 'cancelled']);
    }

    /**
     * Update an existing booking.
     *
     * Handles:
     * - Date changes
     * - Quantity changes
     * - Room type changes
     *
     * @throws \Exception
     */
    public function updateBooking(Booking $booking, array $data): Booking
    {
        /*if (! $booking->isEditable()) {
            throw new \Exception('Cannot modify a booking after check-in.');
        }*/

        return DB::transaction(function () use ($booking, $data) {

            $before = $booking->load('rooms')->toArray();

            $checkIn  = $data['check_in']  ?? $booking->check_in;
            $checkOut = $data['check_out'] ?? $booking->check_out;

            // If dates change → verify current rooms
            if (isset($data['check_in']) || isset($data['check_out'])) {
                foreach ($booking->rooms as $room) {
                    if (! $this->availability->isRoomAvailable(
                        $room->id,
                        $checkIn,
                        $checkOut
                    )) {
                        throw new \Exception(
                            "Room {$room->name} is not available for the new dates."
                        );
                    }
                }
            }

            // If quantity or room type changes → reallocate rooms
            if (
                isset($data['quantity']) ||
                isset($data['room_type_id'])
            ) {
                $quantity   = $data['quantity'] ?? $booking->quantity;
                $roomTypeId = $data['room_type_id'] ?? $booking->room_type_id;

                $booking->rooms()->detach();

                $rooms = $this->availability->getAvailableRoomsForType(
                    $roomTypeId,
                    $checkIn,
                    $checkOut,
                    $quantity
                );

                if ($rooms->count() < $quantity) {
                    throw new \Exception('Not enough rooms available for this update.');
                }

                $booking->rooms()->attach($rooms->pluck('id'));
            }

            $booking->update($data);

            $this->audit->logChange(
                'booking_updated',
                $booking,
                $before,
                $booking->load('rooms')->toArray()
            );

            return $booking;
        });
    }

    /**
     * Cancel a booking.
     */
    public function cancelBooking(Booking $booking, string $reason = ''): Booking
    {
        return DB::transaction(function () use ($booking, $reason) {

            $before = $booking->toArray();

            $booking->update(['status' => 'cancelled']);
            $booking->delete();

            $this->audit->log(
                'booking_cancelled',
                $booking,
                $booking->id,
                ['reason' => $reason, 'before' => $before]
            );

            return $booking;
        });
    }

    /**
     * Check-in workflow.
     */
     /* ---------------------------------------------------------
     | CHECK-IN (ROOM ASSIGNMENT + TOKENS)
     * ---------------------------------------------------------*/
    public function checkIn(Booking $booking, int $roomsToCheckIn = null, ?User $by = null): Booking 
    {
        return DB::transaction(function () use ($booking, $roomsToCheckIn, $by) {

            if ($booking->status !== 'confirmed') {
                throw new \Exception('Booking not eligible for check-in.');
            }

            $alreadyCheckedIn = $booking->rooms()->count();
            $remaining = $booking->quantity - $alreadyCheckedIn;

            $roomsToCheckIn ??= $remaining;

            if ($roomsToCheckIn > $remaining) {
                throw new \Exception('Exceeds remaining rooms.');
            }

            $rooms = $this->availability->lockRoomsForCheckIn(
                $booking->room_type_id,
                $booking->check_in,
                $booking->check_out,
                $roomsToCheckIn
            );

            foreach ($rooms as $room) {
                // Attach room to booking
                $booking->rooms()->attach($room->id, [
                    'status'        => 'active',
                    'checked_in_at' => now(),
                ]);

                // 🔒 Mark room as occupied
                $room->update(['status' => 'occupied']);
            }

            // Generate access tokens AFTER rooms exist
            $booking->generateRoomAccessTokens();

            if ($booking->rooms()->count() === $booking->quantity) {
                $booking->update([
                    'status'        => 'active',
                    'checked_in_at' => now(),
                ]);
            }

            $this->audit->log('booking_checked_in', $booking, $booking->id, [
                'rooms' => $rooms->pluck('name'),
                'by'    => $by?->id,
            ]);

            return $booking;
        });
    }




    /**
     * Check-out workflow.
     */
    public function checkOut(Booking $booking, ?Room $room = null): Booking
    {
        return DB::transaction(function () use ($booking, $room) {

            // -----------------------------------------
            // ROOM-LEVEL CHECKOUT
            // -----------------------------------------
            if ($room) {

                // 1. Finalize nightly revenue (idempotent)
                app(NightlyRoomChargeService::class)
                    ->finalize($booking, $room);

                // 2. Enforce GL-based room balance
                if (! app(RoomBalanceService::class)
                    ->roomCanCheckout($booking, $room)) {

                    throw new \Exception(
                        "Room {$room->name} has an outstanding balance."
                    );
                }

                // 3. Update pivot
                $booking->rooms()->updateExistingPivot($room->id, [
                    'status'         => 'checked_out',
                    'checked_out_at' => now(),
                ]);

                // 4. Mark room dirty
                $room->update(['status' => 'dirty']);
            }

            // -----------------------------------------
            // BOOKING-LEVEL CHECKOUT
            // -----------------------------------------
            else {

                foreach ($booking->rooms as $r) {

                    // Finalize nightly revenue per room
                    app(NightlyRoomChargeService::class)
                        ->finalize($booking, $r);

                    // Enforce GL-based balance per room
                    if (! app(RoomBalanceService::class)
                        ->roomCanCheckout($booking, $r)) {

                        throw new \Exception(
                            "Room {$r->name} has an outstanding balance."
                        );
                    }

                    // Update pivot
                    $booking->rooms()->updateExistingPivot($r->id, [
                        'status'         => 'checked_out',
                        'checked_out_at' => now(),
                    ]);

                    // Mark room dirty
                    $r->update(['status' => 'dirty']);
                }

                // Mark booking checked out only when all rooms pass
                $booking->update([
                    'status'         => 'checked_out',
                    'checked_out_at' => now(),
                ]);
            }

            // -----------------------------------------
            // AUDIT
            // -----------------------------------------
            $this->audit->log('booking_checked_out', $booking, $booking->id, [
                'room' => $room?->name,
            ]);

            return $booking;
        });
    }



    /**
     * Extend an existing booking's stay.
     *
     * @throws \Exception
     */
    public function extendStay(Booking $booking, string $newCheckOut, ?User $by = null): Booking
    {
        if (! $booking->isEditable()) {
            throw new \Exception('Cannot extend a checked-in or completed booking.');
        }

        if (strtotime($newCheckOut) <= strtotime($booking->check_out)) {
            throw new \Exception('New check-out must be after current check-out.');
        }

        foreach ($booking->rooms as $room) {
            if (! $this->availability->isRoomAvailable(
                $room->id,
                $booking->check_out,
                $newCheckOut
            )) {
                throw new \Exception(
                    "Room {$room->name} is not available for the extended dates."
                );
            }
        }

        $old = $booking->check_out;

        $booking->update(['check_out' => $newCheckOut]);

        $this->audit->log(
            'booking_extended',
            $booking,
            $booking->id,
            [
                'old_check_out' => $old,
                'new_check_out' => $newCheckOut,
                'by'            => $by?->id,
            ]
        );

        return $booking;
    }

    /**
     * List bookings with filters & pagination.
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = Booking::with(['rooms.roomType', 'user'])->latest();

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $q->whereDate('check_in', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $q->whereDate('check_out', '<=', $filters['to']);
        }

        if (! empty($filters['room_id'])) {
            $q->whereHas('rooms', fn ($qr) =>
                $qr->where('rooms.id', $filters['room_id'])
            );
        }

        return $q->paginate($perPage);
    }


     /* ---------------------------------------------------------
     | ROOM SWAP (MID-STAY)
     * ---------------------------------------------------------*/
    public function swapRoom(Booking $booking, Room $oldRoom, Room $newRoom, ?User $by = null): void 
    {
        DB::transaction(function () use ($booking, $oldRoom, $newRoom, $by) {

            $pivot = $booking->rooms()
                ->where('room_id', $oldRoom->id)
                ->first()?->pivot;

            if (! $pivot || $pivot->status !== 'active') {
                throw new \Exception('Old room not active.');
            }

            if (! $this->availability->isRoomAvailable(
                $newRoom->id,
                now(),
                $booking->check_out
            )) {
                throw new \Exception('New room unavailable.');
            }

            // Checkout old room
            $booking->rooms()->updateExistingPivot($oldRoom->id, [
                'status'         => 'swapped_out',
                'checked_out_at' => now(),
            ]);

            $oldRoom->update(['status' => 'dirty']);

            // Check-in new room
            $booking->rooms()->attach($newRoom->id, [
                'status'        => 'active',
                'checked_in_at' => now(),
            ]);

            $newRoom->update(['status' => 'occupied']);

            $booking->generateRoomAccessTokens();

            $this->audit->log('room_swapped', $booking, $booking->id, [
                'from' => $oldRoom->name,
                'to'   => $newRoom->name,
                'by'   => $by?->id,
            ]);
        });
    }

}
