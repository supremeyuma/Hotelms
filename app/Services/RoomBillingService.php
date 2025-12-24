<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomPayment;
use App\Events\RoomBillingUpdated;
use Illuminate\Support\Facades\DB;

class RoomBillingService
{
    public function outstanding(Room $room): float
    {
        $charges = $room->charges()->sum('amount');
        $payments = $room->payments()->sum('amount');

        return max($charges - $payments, 0);
    }

    public function history(Room $room): array
    {
        return [
            'charges' => $room->charges()->latest()->get(),
            'payments' => $room->payments()->latest()->get(),
            'outstanding' => $this->outstanding($room),
        ];
    }
    public function pay(Room $room, Booking $booking, float $amount, string $method, ?string $notes = null)
    {
        if ($amount > $this->outstanding($room)) {
            throw new \Exception('Payment exceeds outstanding balance.');
        }

        $payment = RoomPayment::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => $amount,
            'method' => $method,
            'notes' => $notes,
        ]);

        event(new RoomBillingUpdated($room));

        return $payment;
    }

    public function fullyPaid(Room $room): bool
    {
        return $this->outstanding($room) === 0.0;
    }
}
