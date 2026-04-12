<?php

namespace App\Reporting\Projectors;

use App\Models\Booking;
use App\Models\ReportingBookingFact;
use App\Models\ReportingEvent;
use App\Models\Room;
use Carbon\Carbon;

class BookingProjector
{
    /**
     * Project booking into fact table
     */
    public static function project(Booking $booking)
    {
        $fact = ReportingBookingFact::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'booking_source' => $booking->source ?? 'direct',
                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,
                'actual_check_in' => $booking->checked_in_at?->toDateString(),
                'actual_check_out' => $booking->checked_out_at?->toDateString(),
                'room_nights' => $booking->bookingRooms()->count(),
                'guest_count' => $booking->guest_count ?? 0,
                'room_count' => $booking->bookingRooms()->count(),
                'room_revenue' => $booking->total_room_charges ?? 0,
                'ancillary_revenue' => $booking->ancillary_charges ?? 0,
                'total_charges' => $booking->total_charge ?? 0,
                'total_payments' => $booking->payments()->where('status', 'completed')->sum('amount') ?? 0,
                'outstanding_balance' => $booking->outstanding_balance ?? 0,
                'complaints_count' => $booking->complaints_count ?? 0,
                'service_requests_count' => $booking->service_requests_count ?? 0,
                'checkout_delay' => $booking->checkout_delay ?? false,
                'checkin_delay' => $booking->checkin_delay ?? false,
                'requires_follow_up' => $booking->status === 'complaint_pending',
            ]
        );

        // Record event
        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'booking.projected',
            'domain' => 'operations',
            'booking_id' => $booking->id,
            'reference_type' => 'Booking',
            'reference_id' => $booking->id,
            'meta' => [
                'outstanding_balance' => $booking->outstanding_balance,
            ],
        ]);

        return $fact;
    }

    /**
     * Batch project all bookings for a date range
     */
    public static function projectRange($startDate, $endDate)
    {
        Booking::whereBetween('check_in_date', [$startDate, $endDate])
            ->orWhereBetween('check_out_date', [$startDate, $endDate])
            ->each(fn (Booking $booking) => self::project($booking));
    }

    /**
     * Project booking on status change
     */
    public static function projectOnStatusChange(Booking $booking, $oldStatus)
    {
        self::project($booking);

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'booking.status_changed',
            'domain' => 'operations',
            'booking_id' => $booking->id,
            'status_before' => $oldStatus,
            'status_after' => $booking->status,
            'reference_type' => 'Booking',
            'reference_id' => $booking->id,
        ]);
    }

    /**
     * Project booking financial transactions
     */
    public static function projectFinancialTransaction(Booking $booking, $transactionType, $amount)
    {
        self::project($booking);

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => "booking.{$transactionType}",
            'domain' => 'finance',
            'booking_id' => $booking->id,
            'amount' => $amount,
            'currency' => 'USD',
            'reference_type' => 'Booking',
            'reference_id' => $booking->id,
            'meta' => [
                'transaction_type' => $transactionType,
            ],
        ]);
    }
}
