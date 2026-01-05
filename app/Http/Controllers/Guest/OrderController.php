<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RoomAccessToken;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Charge;

class OrderController extends Controller
{
    public function store(Request $request, string $token)
    {
        $data = $request->validate([
            'department' => 'required|in:kitchen,bar',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($data, $token) {

                $access = RoomAccessToken::with(['room', 'booking'])
                    ->where('token', $token)
                    ->firstOrFail();

                $order = Order::create([
                    'booking_id'   => $access->booking_id,
                    'room_id'      => $access->room_id,
                    'service_area' => $data['department'],
                    'status'       => OrderStatus::PENDING,
                    'notes'        => $data['notes'] ?? null,
                    'order_code'   => strtoupper('ORD-' . uniqid()),
                    'total'        => 0,
                ]);

                dd($order);

                $total = 0;

                foreach ($data['items'] as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_name'=> $item['name'],
                        'price'    => $item['price'],
                        'qty'      => $item['quantity'],
                    ]);

                    $total += $item['price'] * $item['quantity'];
                }

                $order->update(['total' => $total]);
            });

            return redirect()->back()->with('success', 'Order placed successfully');

        } catch (\Throwable $e) {

            report($e);

            return redirect()->back()->with(
                'error',
                'Failed to place order. Please try again.'
            );
        }
    }

}
