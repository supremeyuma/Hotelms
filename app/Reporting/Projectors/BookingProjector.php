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
        // Calculate nights between check-in and check-out
        $nights = 1;
        if ($booking->check_in && $booking->check_out) {
            $nights = $booking->check_out->diffInDays($booking->check_in) ?: 1;
        }
        
        // Count booked rooms
        $roomCount = $booking->rooms()->count() ?: 1;
        
        // Calculate total payments from completed payments
        $totalPayments = $booking->payments()->where('status', 'completed')->sum('amount') ?? 0;
        
        $fact = ReportingBookingFact::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'booking_source' => 'direct',
                'check_in_date' => $booking->check_in,
                'check_out_date' => $booking->check_out,
                'actual_check_in' => null,
                'actual_check_out' => null,
                'room_nights' => $nights * $roomCount,
                'guest_count' => $booking->guests ?? 0,
                'room_count' => $roomCount,
                'room_revenue' => $booking->total_amount ?? 0,
                'ancillary_revenue' => $booking->charges()->sum('amount') ?? 0,
                'total_charges' => $booking->total_amount ?? 0,
                'total_payments' => $totalPayments,
                'outstanding_balance' => max(0, ($booking->total_amount ?? 0) - $totalPayments),
                'complaints_count' => 0,
                'service_requests_count' => $booking->orders()->count() ?? 0,
                'checkout_delay' => false,
                'checkin_delay' => false,
                'requires_follow_up' => false,
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
