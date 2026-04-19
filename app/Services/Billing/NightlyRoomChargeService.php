<?php

namespace App\Services\Billing;

use App\Models\Room;
use App\Models\Charge;
use Carbon\Carbon;
use App\Events\RoomBillingUpdated;
use App\Models\Booking;

class NightlyRoomChargeService
{
    public function charge(Room $room, Booking $booking, Carbon $date): void
    {
        $exists = Charge::where('room_id', $room->id)
            ->where('type', 'nightly')
            ->where(function ($query) use ($date) {
                $query->whereDate('charge_date', $date)
                    ->orWhere(function ($legacyQuery) use ($date) {
                        $legacyQuery->whereNull('charge_date')
                            ->whereDate('created_at', $date);
                    });
            })
            ->exists();

        if ($exists) {
            return;
        }

        $amount = app(RoomRateResolver::class)
        ->nightly($room, $booking);

        Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'type' => 'nightly',
            'description' => 'Nightly room charge',
            'amount' => $amount,
            'charge_date' => $date->toDateString(),
        ]);

        event(new RoomBillingUpdated($room));
    }

public function finalize(Booking $booking, Room $room, bool $skipCheckoutDayCharge = false): void
    {
        $checkoutDay = Carbon::parse($booking->check_out)->startOfDay();
        $checkInDay = Carbon::parse($booking->check_in)->startOfDay();
        $today = now()->startOfDay();

        if ($skipCheckoutDayCharge && $checkoutDay->eq($today)) {
            return;
        }

        $chargeDate = $checkoutDay->copy()->subDay();

        if ($chargeDate->lt($checkInDay)) {
            $chargeDate = $checkInDay;
        }

        $this->charge($room, $booking, $chargeDate);
    }
}
