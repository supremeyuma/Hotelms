<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Room;
use App\Services\Billing\NightlyRoomChargeService;

class ChargeOccupiedRooms extends Command
{
    protected $signature = 'billing:charge-rooms';
    protected $description = 'Auto charge nightly room rates';

    public function handle(NightlyRoomChargeService $service)
    {
        $date = now()->startOfDay();

        $rooms = Room::where('status', 'occupied')
            ->with([
                'roomType',
                'bookings' => fn ($q) => $q->whereIn('status', ['active', 'checked_in'])
            ])
            ->get();

        foreach ($rooms as $room) {
            $booking = $room->bookings->first();
            if (! $booking) {
                continue;
            }

            $service->charge($room, $booking->id, $date);
        }
    }
}
