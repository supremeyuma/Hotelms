<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);

            // OrderItems creation
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $order->items()->create($item);
                }
            }

            return $order;
        });
    }

    public function updateOrder(Order $order, array $data)
    {
        $order->update($data);
    }

    public function cancelOrder(Order $order)
    {
        $order->update(['status' => 'cancelled']);
    }
}
