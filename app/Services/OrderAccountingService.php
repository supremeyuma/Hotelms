<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Ledgers\ServiceRevenueLedger;

class OrderAccountingService
{
    public function __construct(
        protected ServiceRevenueLedger $ledger
    ) {}

    /**
     * Call when order status becomes COMPLETED
     */
    public function handleCompleted(Order $order): void
    {
        if ($order->total <= 0) {
            return; // complimentary handled elsewhere
        }

        $this->ledger->postCharge(
            service: $order->type, // kitchen | bar
            bookingId: $order->booking_id,
            roomId: $order->room_id,
            amount: $order->total,
            referenceType: 'order',
            referenceId: $order->id
        );
    }

    /**
     * Call when order is CANCELLED after completion
     */
    public function handleCancelled(Order $order): void
    {
        if ($order->total <= 0) {
            return;
        }

        $this->ledger->reverseCharge(
            service: $order->type,
            bookingId: $order->booking_id,
            roomId: $order->room_id,
            amount: $order->total,
            referenceType: 'order',
            referenceId: $order->id
        );
    }
}
