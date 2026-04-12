<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Payment;
use App\Reporting\Projectors\BookingProjector;
use App\Services\BookingService;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment)
    {
        $this->syncBookingPaymentState($payment);

        if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
            $booking = Booking::find($payment->payable_id);
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
        $this->syncBookingPaymentState($payment);

        if ($payment->isDirty('status') && $payment->status === 'completed') {
            if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
                $booking = Booking::find($payment->payable_id);
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
        $this->syncBookingPaymentState($payment);

        if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
            $booking = Booking::find($payment->payable_id);
            if ($booking) {
                BookingProjector::projectFinancialTransaction(
                    $booking,
                    'payment_reversed',
                    -$payment->amount
                );
            }
        }
    }

    protected function syncBookingPaymentState(Payment $payment): void
    {
        $booking = $payment->booking;

        if (! $booking && $payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
            $booking = Booking::find($payment->payable_id);
        }

        if (! $booking) {
            return;
        }

        app(BookingService::class)->syncBookingPaymentState($booking);
    }
}
