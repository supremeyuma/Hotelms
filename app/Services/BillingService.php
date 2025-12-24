<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Events\RoomBillingUpdated;

class BillingService
{
    public function addCharge(Booking $booking, string $description, float $amount): Charge
    {
        $charge = Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'description' => $description,
            'amount' => $amount,
        ]);

        // dispatch event with the related room if available, otherwise pass the booking
        $subject = $booking->room ?? $booking;
        event(new RoomBillingUpdated($subject));

        return $charge;
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

    public function addPayment(Booking $booking, float $amount, string $method, string $notes = null): Payment
    {
        if ($amount > $this->calculateOutstanding($booking)) {
            throw new \InvalidArgumentException('Payment exceeds outstanding amount.');
        }

        /*if ($amount > $this->calculateOutstanding($booking)) {
            throw new \InvalidArgumentException('Payment exceeds outstanding amount.');
        }*/

        return DB::transaction(function () use ($booking, $amount, $method, $notes) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'method' => $method,
                'notes' => $notes,
            ]);

            // Optional: trigger event for updated outstanding
            event(new \App\Events\BillingUpdated($booking));

            return $payment;
        });
    }

    public function settleFullAmount(Booking $booking, string $method): Payment
    {
        $outstanding = $this->calculateOutstanding($booking);
        if ($outstanding <= 0) {
            throw new \Exception('No outstanding balance to settle.');
        }

        return $this->addPayment($booking, $outstanding, $method, 'Full settlement');
    }

    public function getBillingHistory(Booking $booking)
    {
        return [
            'charges' => $booking->charges()->orderBy('created_at', 'desc')->get(),
            'payments' => $booking->payments()->orderBy('created_at', 'desc')->get(),
            'outstanding' => $this->calculateOutstanding($booking),
        ];
    }
}
