<?php

namespace App\Services;

use App\Models\LaundryOrder;
use App\Services\Ledgers\ServiceRevenueLedger;
use Illuminate\Support\Facades\Log;

class LaundryAccountingService
{
    public function __construct(
        protected ServiceRevenueLedger $ledger,
        protected BookingResolverService $bookingResolver
    ) {}

    /**
     * Call ONLY when laundry status becomes COMPLETED
     */
    public function handleCompleted(LaundryOrder $order): void
    {
        if ($order->total_amount <= 0) {
            return;
        }

        try {
            $booking = $this->bookingResolver
                ->resolveActiveBookingForRoom($order->room_id);

            $this->ledger->postCharge(
                service: 'laundry',
                bookingId: $booking->id,
                roomId: $order->room_id,
                amount: $order->total_amount,
                referenceType: 'laundry',
                referenceId: $order->id
            );
        } catch (\Throwable $e) {
            Log::error('Laundry accounting failed', [
                'laundry_order_id' => $order->id,
                'room_id' => $order->room_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Optional: reversal if a COMPLETED laundry is later cancelled/refunded
     */
    public function handleReversal(LaundryOrder $order): void
    {
        if ($order->total_amount <= 0) {
            return;
        }

        try {
            $booking = $this->bookingResolver
                ->resolveActiveBookingForRoom($order->room_id);

            $this->ledger->reverseCharge(
                service: 'laundry',
                bookingId: $booking->id,
                roomId: $order->room_id,
                amount: $order->total_amount,
                referenceType: 'laundry',
                referenceId: $order->id
            );
        } catch (\Throwable $e) {
            Log::error('Laundry accounting reversal failed', [
                'laundry_order_id' => $order->id,
                'room_id' => $order->room_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
