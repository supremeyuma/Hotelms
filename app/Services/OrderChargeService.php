<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Charge;

class OrderChargeService
{
    public function post(Order $order): void
    {
        if ($order->order_type !== 'room_service') {
            return;
        }

        Charge::create([
            'booking_id' => $order->booking_id,
            'room_id' => $order->room_id,
            'source_type' => 'order',
            'source_id' => $order->id,
            'description' => 'Room Service Order #' . $order->id,
            'amount' => $order->total,
            'status' => 'pending'
        ]);
    }
}
