<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class NightlyRoomRevenueAudit extends Command
{
    protected $signature = 'hotel:night-audit';
    protected $description = 'Recognize room revenue per room per night';

    public function handle(BookingAccountingService $service)
    {
        $today = Carbon::today();

        Booking::where('status', 'checked_in')
            ->whereDate('check_in', '<=', $today)
            ->whereDate('check_out', '>', $today)
            ->each(fn ($booking) =>
                $service->handleNightAudit($booking, $today)
            );

        $this->info('Night audit completed');
    }
}
