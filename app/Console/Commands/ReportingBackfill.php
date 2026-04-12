<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\LaundryOrder;
use App\Models\MaintenanceTicket;
use App\Models\Order;
use App\Models\Payment;
use App\Reporting\Projectors\BookingProjector;
use App\Reporting\Projectors\LaundryProjector;
use App\Reporting\Projectors\MaintenanceProjector;
use App\Reporting\Projectors\OrderProjector;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReportingBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reporting:backfill {--days=90 : Number of days to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill reporting facts from existing production data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $startDate = now()->subDays($days);

        $this->info("Backfilling reporting data for past {$days} days...");
        $this->newLine();

        try {
            $this->backfillBookings($startDate);
            $this->backfillOrders($startDate);
            $this->backfillLaundry($startDate);
            $this->backfillMaintenance($startDate);
            $this->backfillCharges($startDate);
            $this->backfillPayments($startDate);

            $this->info('✓ Backfill completed successfully');

            return 0;
        } catch (\Exception $e) {
            $this->error('Backfill failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Backfill booking facts
     */
    private function backfillBookings(Carbon $startDate)
    {
        $bookings = Booking::where('created_at', '>=', $startDate)->get();

        $this->withProgressBar($bookings, function ($booking) {
            BookingProjector::project($booking);
        });

        $this->newLine();
        $this->line("✓ Backfilled {$bookings->count()} bookings");
    }

    /**
     * Backfill order facts
     */
    private function backfillOrders(Carbon $startDate)
    {
        $orders = Order::where('created_at', '>=', $startDate)->get();

        $this->withProgressBar($orders, function ($order) {
            OrderProjector::project($order);
        });

        $this->newLine();
        $this->line("✓ Backfilled {$orders->count()} orders");
    }

    /**
     * Backfill laundry facts
     */
    private function backfillLaundry(Carbon $startDate)
    {
        $laundry = LaundryOrder::where('created_at', '>=', $startDate)->get();

        $this->withProgressBar($laundry, function ($item) {
            LaundryProjector::project($item);
        });

        $this->newLine();
        $this->line("✓ Backfilled {$laundry->count()} laundry orders");
    }

    /**
     * Backfill maintenance facts
     */
    private function backfillMaintenance(Carbon $startDate)
    {
        $maintenance = MaintenanceTicket::where('created_at', '>=', $startDate)->get();

        $this->withProgressBar($maintenance, function ($ticket) {
            MaintenanceProjector::project($ticket);
        });

        $this->newLine();
        $this->line("✓ Backfilled {$maintenance->count()} maintenance tickets");
    }

    /**
     * Backfill charge facts
     */
    private function backfillCharges(Carbon $startDate)
    {
        $charges = Charge::where('created_at', '>=', $startDate)->get();

        $this->withProgressBar($charges, function ($charge) {
            if ($charge->chargeable_type === 'App\Models\Booking' && $charge->chargeable_id) {
                $booking = Booking::find($charge->chargeable_id);
                if ($booking) {
                    BookingProjector::projectFinancialTransaction(
                        $booking,
                        'charge_posted',
                        $charge->amount
                    );
                }
            }
        });

        $this->newLine();
        $this->line("✓ Backfilled {$charges->count()} charges");
    }

    /**
     * Backfill payment facts
     */
    private function backfillPayments(Carbon $startDate)
    {
        $payments = Payment::where('created_at', '>=', $startDate)->get();

        $this->withProgressBar($payments, function ($payment) {
            if ($payment->payable_type === 'App\Models\Booking' && $payment->payable_id) {
                $booking = Booking::find($payment->payable_id);
                if ($booking) {
                    BookingProjector::projectFinancialTransaction(
                        $booking,
                        'payment_received',
                        $payment->amount
                    );
                }
            }
        });

        $this->newLine();
        $this->line("✓ Backfilled {$payments->count()} payments");
    }
}
