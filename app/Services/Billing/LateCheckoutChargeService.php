<?php

namespace App\Services\Billing;

use App\Models\Room;
use App\Models\Booking;
use App\Models\Charge;
use Carbon\Carbon;
use App\Events\RoomBillingUpdated;

class LateCheckoutChargeService
{
    public function apply(Room $room, Booking $booking, Carbon $checkoutTime)
    {
        $standard = Carbon::parse(
            $checkoutTime->toDateString() . ' ' . config('billing.checkout_time')
        );

        $hoursLate = $standard->diffInHours($checkoutTime, false);
        if ($hoursLate <= config('billing.late_checkout.grace_hours')) return;

        $rate = app(RoomRateResolver::class)->nightly($room, $booking);

        if ($hoursLate >= config('billing.late_checkout.full_day_after')) {
            $amount = $rate;
            $label = 'Late checkout (full day)';
        } else {
            $amount = $rate * config('billing.late_checkout.half_day_rate');
            $label = 'Late checkout (half day)';
        }

        Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'type' => 'late_checkout',
            'description' => $label,
            'amount' => $amount,
            'charge_date' => now()->toDateString(),
        ]);

        event(new RoomBillingUpdated($room));
    }
}
