<?php

namespace App\Services;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\User;
use App\Services\RoomAvailabilityService;
use App\Services\AuditLoggerService;
use App\Services\RoomCheckoutService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            if (empty($data['selected_room_ids']) && ! empty($data['room_id'])) {
                $selectedRoom = Room::findOrFail($data['room_id']);
                $data['selected_room_ids'] = [$selectedRoom->id];
                $data['room_type_id'] = $data['room_type_id'] ?? $selectedRoom->room_type_id;
                $data['quantity'] = $data['quantity'] ?? 1;
            }

            $selectedRoomIds = array_values(array_unique(array_map('intval', $data['selected_room_ids'] ?? [])));
            $quantity = $data['quantity'] ?? max(count($selectedRoomIds), 1);

            // Inventory-level availability check (NO rooms yet)
            $available = $this->availability->checkAvailability(
                $data['room_type_id'],
                $data['check_in'],
                $data['check_out'],
                $quantity
            );

            if (! $available) {
                throw new \Exception('Not enough rooms available for the selected dates.');
            }

            if (count($selectedRoomIds) !== $quantity) {
                throw new \Exception('Please choose the exact room' . ($quantity > 1 ? 's' : '') . ' you would like to reserve.');
            }

            $selectedRooms = $this->availability->getAvailableRoomsByIds(
                $selectedRoomIds,
                $data['check_in'],
                $data['check_out']
            );

            if ($selectedRooms->count() !== $quantity) {
                throw new \Exception('One or more selected rooms are no longer available.');
            }

            if ($selectedRooms->pluck('room_type_id')->unique()->count() !== 1
                || (int) $selectedRooms->first()->room_type_id !== (int) $data['room_type_id']) {
                throw new \Exception('Selected rooms must belong to the chosen room type.');
            }

            $booking = Booking::create([
                'property_id'  => $data['property_id'] ?? 1,
                'room_id'      => $selectedRooms->first()->id,
                'user_id'      => $data['user_id'] ?? null,
                'booking_code' => strtoupper('BKG-' . uniqid()),
                'check_in'     => $data['check_in'],
                'check_out'    => $data['check_out'],
                'room_type_id' => $data['room_type_id'],
                'quantity'     => $quantity,
                'adults'       => $data['adults'] ?? 1,
                'children'     => $data['children'] ?? 0,
                'nightly_rate' => $data['nightly_rate'] ?? ($selectedRooms->first()->roomType->base_price ?? 0),
                'total_amount' => $data['total_amount'] ?? (($selectedRooms->first()->roomType->base_price ?? 0) * $quantity),
                'status'       => 'pending_payment',
                'expires_at'   => now()->addMinutes(45),
                'guest_name'   => $data['guest_name'],
                'guest_email'  => $data['guest_email'],
                'guest_phone'  => $data['guest_phone'],
                'payment_status' => $data['payment_status'] ?? 'pending',
                'special_requests' => $data['special_requests'] ?? null,
                'details' => $data['details'] ?? null,
            ]);

            $booking->rooms()->attach(
                $selectedRooms->pluck('id')->mapWithKeys(fn ($roomId) => [
                    $roomId => ['status' => 'reserved'],
                ])->all()
            );

            $this->audit->log(
                'booking_created',
                $booking,
                $booking->id,
                ['quantity' => $booking->quantity, 'rooms' => $selectedRooms->pluck('name')->all()]
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

        app(DiscountCodeService::class)->markAppliedForBooking($booking);
        $this->sendBookingConfirmationToGuest($booking->fresh(['roomType', 'rooms']));

        $this->audit->log('booking_confirmed', $booking, $booking->id);

        return $booking;
    }

    public function markPaidAndConfirm(Booking $booking, string $paymentMethod): Booking
    {
        $wasPendingPayment = $booking->status === 'pending_payment';

        $booking->update([
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'expires_at' => null,
            ...($wasPendingPayment ? ['status' => 'confirmed'] : []),
        ]);

        if ($wasPendingPayment) {
            app(DiscountCodeService::class)->markAppliedForBooking($booking);
            $this->sendBookingConfirmationToGuest($booking->fresh(['roomType', 'rooms']));
            $this->audit->log('booking_confirmed', $booking, $booking->id);
        }

        return $booking->fresh(['roomType', 'rooms', 'payments']);
    }

    public function reconcilePaidBookingStates(): void
    {
        Booking::query()
            ->where('status', 'pending_payment')
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhereHas('payments', function ($paymentQuery) {
                        $paymentQuery->whereIn('status', ['completed', 'successful']);
                    });
            })
            ->with(['payments' => function ($query) {
                $query->whereIn('status', ['completed', 'successful'])
                    ->latest('paid_at')
                    ->latest('id');
            }])
            ->chunkById(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $latestSuccessfulPayment = $booking->payments->first();
                    $resolvedPaymentMethod = $latestSuccessfulPayment?->provider
                        ?: $latestSuccessfulPayment?->method
                        ?: $booking->payment_method;

                    $booking->update([
                        'status' => 'confirmed',
                        'expires_at' => null,
                        'payment_status' => $latestSuccessfulPayment ? 'paid' : ($booking->payment_status ?: 'paid'),
                        'payment_method' => $resolvedPaymentMethod,
                    ]);
                }
            });
    }

    protected function sendBookingConfirmationToGuest(Booking $booking): void
    {
        if (! $booking->guest_email) {
            return;
        }

        $details = $booking->details ?? [];
        $notifications = $details['notifications'] ?? [];

        if (! empty($notifications['booking_confirmation_sent_at'])) {
            return;
        }

        try {
            Mail::to($booking->guest_email)->send(new BookingConfirmationMail(
                $booking->fresh(['roomType', 'rooms'])
            ));

            $notifications['booking_confirmation_sent_at'] = now()->toIso8601String();
            $details['notifications'] = $notifications;

            $booking->forceFill(['details' => $details])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }


    /**
     * Cancel expired pending bookings.
     */
    public function cancelExpiredBookings(): void
    {
        Booking::where('status', 'pending_payment')
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (Booking $booking) {
                $booking->update(['status' => 'cancelled']);
                app(DiscountCodeService::class)->releaseForBooking($booking);
            });
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
            if (empty($data['selected_room_ids']) && ! empty($data['room_id'])) {
                $selectedRoom = Room::findOrFail($data['room_id']);
                $data['selected_room_ids'] = [$selectedRoom->id];
                $data['room_type_id'] = $data['room_type_id'] ?? $selectedRoom->room_type_id;
                $data['quantity'] = $data['quantity'] ?? 1;
            }

            $before = $booking->load('rooms')->toArray();

            $checkIn  = $data['check_in']  ?? $booking->check_in;
            $checkOut = $data['check_out'] ?? $booking->check_out;

            // If dates change, verify current rooms.
            if (isset($data['check_in']) || isset($data['check_out'])) {
                foreach ($booking->rooms as $room) {
                    if (! $this->availability->isRoomAvailable(
                        $room->id,
                        $checkIn,
                        $checkOut,
                        $booking->id
                    )) {
                        throw new \Exception(
                            "Room {$room->name} is not available for the new dates."
                        );
                    }
                }
            }

            // If quantity or room type changes, reallocate rooms.
            if (
                isset($data['quantity']) ||
                isset($data['room_type_id']) ||
                isset($data['selected_room_ids'])
            ) {
                $quantity   = $data['quantity'] ?? $booking->quantity;
                $roomTypeId = $data['room_type_id'] ?? $booking->room_type_id;
                $selectedRoomIds = array_values(array_unique(array_map('intval', $data['selected_room_ids'] ?? $booking->rooms->pluck('id')->all())));

                if (count($selectedRoomIds) !== (int) $quantity) {
                    throw new \Exception('Select the exact number of rooms for this booking.');
                }

                $rooms = $this->availability->getAvailableRoomsByIds(
                    $selectedRoomIds,
                    $checkIn,
                    $checkOut,
                    $booking->id
                );

                if ($rooms->count() < $quantity) {
                    throw new \Exception('One or more selected rooms are no longer available.');
                }

                if ($rooms->pluck('room_type_id')->unique()->count() !== 1
                    || (int) $rooms->first()->room_type_id !== (int) $roomTypeId) {
                    throw new \Exception('Selected rooms must match the booking room type.');
                }

                $booking->rooms()->sync(
                    $rooms->pluck('id')->mapWithKeys(function ($roomId) use ($booking) {
                        $existing = $booking->rooms->firstWhere('id', $roomId);

                        return [
                            $roomId => [
                                'status' => $existing?->pivot?->status ?? 'reserved',
                                'checked_in_at' => $existing?->pivot?->checked_in_at,
                                'checked_out_at' => $existing?->pivot?->checked_out_at,
                                'rate_override' => $existing?->pivot?->rate_override,
                            ],
                        ];
                    })->all()
                );

                $data['room_id'] = $rooms->first()->id;
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
            app(DiscountCodeService::class)->releaseForBooking($booking);
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

            $reservedRooms = $booking->rooms()->get();
            $alreadyCheckedIn = $reservedRooms->filter(fn ($room) => ! is_null($room->pivot->checked_in_at))->count();
            $remaining = $booking->quantity - $alreadyCheckedIn;

            $roomsToCheckIn ??= $remaining;

            if ($roomsToCheckIn > $remaining) {
                throw new \Exception('Exceeds remaining rooms.');
            }

            $rooms = $reservedRooms
                ->filter(fn ($room) => is_null($room->pivot->checked_in_at))
                ->take($roomsToCheckIn)
                ->values();

            if ($rooms->count() < $roomsToCheckIn) {
                $rooms = $this->availability->lockRoomsForCheckIn(
                    $booking->room_type_id,
                    $booking->check_in,
                    $booking->check_out,
                    $roomsToCheckIn
                );
            }

            foreach ($rooms as $room) {
                if ($booking->rooms()->where('rooms.id', $room->id)->exists()) {
                    $booking->rooms()->updateExistingPivot($room->id, [
                        'status'        => 'active',
                        'checked_in_at' => now(),
                        'checked_out_at' => null,
                    ]);
                } else {
                    $booking->rooms()->attach($room->id, [
                        'status'        => 'active',
                        'checked_in_at' => now(),
                    ]);
                }

                // Mark room as occupied.
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
