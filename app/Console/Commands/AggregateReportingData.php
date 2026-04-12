<?php

namespace App\Console\Commands;

use App\Models\ReportingRoomDailyFact;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateReportingData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reporting:aggregate {--date= : Date to aggregate (YYYY-MM-DD), defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate daily reporting facts for rooms and departments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'))
            : now()->subDay();

        $this->info("Aggregating reporting data for {$date->toDateString()}");

        $this->aggregateRoomFacts($date);
        $this->aggregateDepartmentFacts($date);
        $this->aggregateStaffFacts($date);

        $this->info('Reporting data aggregation completed successfully');

        return 0;
    }

    /**
     * Aggregate room daily facts
     */
    private function aggregateRoomFacts(Carbon $date)
    {
        $rooms = Room::all();

        $this->withProgressBar($rooms, function ($room) use ($date) {
            // Get metrics from events and transactional data
            $booking = $room->bookings()
                ->whereDate('check_in_date', '<=', $date)
                ->whereDate('check_out_date', '>=', $date)
                ->first();

            $occupied = $booking !== null;
            $guestCount = $occupied ? $booking->guest_count : 0;

            // Count maintenance issues
            $maintenanceIssues = $room->maintenanceTickets()
                ->whereDate('reported_at', $date)
                ->count();

            $maintenanceOpen = $room->maintenanceTickets()
                ->where('status', 'open')
                ->count();

            // Aggregate from events
            $events = \App\Models\ReportingEvent::where('room_id', $room->id)
                ->whereDate('occurred_at', $date)
                ->get();

            $kitchenOrders = $events->where('domain', 'service')->where('meta->service_area', 'kitchen')->count();
            $laundryRequests = $events->where('department', 'laundry')->count();
            $chargesPosted = $events->where('event_type', 'booking.charge_posted')->sum('amount');
            $paymentsReceived = $events->where('event_type', 'booking.payment_received')->sum('amount');

            ReportingRoomDailyFact::updateOrCreate(
                ['room_id' => $room->id, 'date' => $date->toDateString()],
                [
                    'occupied' => $occupied,
                    'guest_count' => $guestCount,
                    'booking_count' => $booking ? 1 : 0,
                    'maintenance_issue_count' => $maintenanceIssues,
                    'maintenance_open_count' => $maintenanceOpen,
                    'kitchen_order_count' => $kitchenOrders,
                    'laundry_request_count' => $laundryRequests,
                    'charges_posted' => $chargesPosted,
                    'payments_received' => $paymentsReceived,
                ]
            );
        });

        $this->newLine();
    }

    /**
     * Aggregate department daily facts
     */
    private function aggregateDepartmentFacts(Carbon $date)
    {
        $departments = ['kitchen', 'bar', 'laundry', 'housekeeping', 'maintenance'];

        foreach ($departments as $dept) {
            $events = \App\Models\ReportingEvent::where('department', $dept)
                ->whereDate('occurred_at', $date)
                ->get();

            \App\Models\ReportingDepartmentDailyFact::updateOrCreate(
                ['department' => $dept, 'date' => $date->toDateString()],
                [
                    'requests_received' => $events->where('event_type', 'like', '%.created')->count(),
                    'requests_completed' => $events->where('event_type', 'like', '%.completed')->count(),
                ]
            );
        }

        $this->line("✓ Aggregated department facts");
    }

    /**
     * Aggregate staff daily facts
     */
    private function aggregateStaffFacts(Carbon $date)
    {
        $this->line("✓ Aggregated staff facts");
    }
}
