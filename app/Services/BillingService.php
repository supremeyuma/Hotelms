<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Payment;

class BillingService
{
    public function addCharge(Booking $booking, string $description, float $amount): Charge
    {
        return Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'description' => $description,
            'amount' => $amount,
        ]);
    }

    public function payBill(Booking $booking, float $amount): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'amount' => $amount,
        ]);
    }

    public function calculateOutstanding(Booking $booking): float
    {
        $charges = $booking->charges()->sum('amount');
        $payments = $booking->payments()->sum('amount');

        return max($charges - $payments, 0);
    }

    public function canCheckout(Booking $booking): bool
    {
        return $this->calculateOutstanding($booking) <= 0;
    }
}
