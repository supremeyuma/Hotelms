<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Booking;
use App\Models\RoomPayment;
use Illuminate\Support\Facades\DB;
use App\Events\RoomBillingUpdated;
use Exception;

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
            'charges' => $room->charges()
                ->select('id','description','amount','created_at')
                ->orderBy('created_at')
                ->get(),

            'payments' => $room->payments()
                ->select('id','amount','method','created_at')
                ->orderBy('created_at')
                ->get(),

            'outstanding' => $this->outstanding($room),
            'currency' => 'NGN',
        ];
    }

    public function pay(
        Room $room,
        Booking $booking,
        float $amount,
        string $method,
        ?string $notes = null
    ): RoomPayment {
        return DB::transaction(function () use (
            $room,
            $booking,
            $amount,
            $method,
            $notes
        ) {
            $outstanding = $this->outstanding($room);

            if ($amount <= 0) {
                throw new Exception('Invalid payment amount.');
            }

            if ($amount > $outstanding) {
                throw new Exception('Payment exceeds outstanding balance.');
            }

            $payment = RoomPayment::create([
                'booking_id' => $booking->id,
                'room_id'    => $room->id,
                'amount'     => $amount,
                'method'     => $method,
                'notes'      => $notes,
            ]);

            event(new RoomBillingUpdated($room));

            return $payment;
        });
    }

    public function fullyPaid(Room $room): bool
    {
        return $this->outstanding($room) === 0.0;
    }
}
