<?php

namespace App\Observers;

use App\Models\Charge;
use App\Reporting\Projectors\BookingProjector;

class ChargeObserver
{
    /**
     * Handle the Charge "created" event.
     */
    public function created(Charge $charge)
    {
        // Track charge posting in booking's reservation facts
        if ($charge->billable_type === 'App\Models\Booking' && $charge->billable_id) {
            $booking = \App\Models\Booking::find($charge->billable_id);
            if ($booking) {
                BookingProjector::projectFinancialTransaction(
                    $booking,
                    'charge_posted',
                    $charge->amount
                );
            }
        }
    }

    /**
     * Handle the Charge "deleted" event.
     */
    public function deleted(Charge $charge)
    {
        // Track charge reversal if needed
        if ($charge->billable_type === 'App\Models\Booking' && $charge->billable_id) {
            $booking = \App\Models\Booking::find($charge->billable_id);
            if ($booking) {
                BookingProjector::projectFinancialTransaction(
                    $booking,
                    'charge_reversed',
                    -$charge->amount
                );
            }
        }
    }
}
