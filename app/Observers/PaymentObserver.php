<?php

namespace App\Observers;

use App\Models\Payment;
use App\Reporting\Projectors\BookingProjector;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment)
    {
        // Track payment in booking's financial facts
        if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
            $booking = \App\Models\Booking::find($payment->payable_id);
            if ($booking) {
                BookingProjector::projectFinancialTransaction(
                    $booking,
                    'payment_received',
                    $payment->amount
                );
            }
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment)
    {
        // Track status changes (pending → completed → failed)
        if ($payment->isDirty('status') && $payment->status === 'completed') {
            if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
                $booking = \App\Models\Booking::find($payment->payable_id);
                if ($booking) {
                    BookingProjector::project($booking);
                }
            }
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment)
    {
        // Track payment reversal
        if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
            $booking = \App\Models\Booking::find($payment->payable_id);
            if ($booking) {
                BookingProjector::projectFinancialTransaction(
                    $booking,
                    'payment_reversed',
                    -$payment->amount
                );
            }
        }
    }
}
