<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RoomAccessToken;
use App\Models\Charge;
use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request, string $token)
    {
        $data = $request->validate([
            'department'          => 'required|in:kitchen,bar',
            'items'               => 'required|array|min:1',
            'items.*.name'        => 'required|string',
            'items.*.price'       => 'required|numeric|min:0',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.note'        => 'nullable|string|max:255',
            'notes'               => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($data, $token) {

                $access = RoomAccessToken::with(['room', 'booking'])
                    ->where('token', $token)
                    ->firstOrFail();

                $order = Order::create([
                    'booking_id'        => $access->booking_id,
                    'room_id'           => $access->room_id,
                    'service_area'      => $data['department'],
                    'status'            => OrderStatus::PENDING,
                    'cancelable_until'  => now()->addMinutes(5),
                    'notes'             => $data['notes'] ?? null,
                    'order_code'        => strtoupper('ORD-' . uniqid()),
                    'total'             => 0,
                ]);

                $total = 0;

                foreach ($data['items'] as $item) {
                    OrderItem::create([
                        'order_id'  => $order->id,
                        'item_name' => $item['name'],
                        'price'     => $item['price'],
                        'qty'       => $item['quantity'],
                        'note'      => $item['note'] ?? null,
                    ]);

                    $total += $item['price'] * $item['quantity'];
                }

                $order->update(['total' => $total]);

                // Create charge for the room / booking
                Charge::create([
                    'booking_id'  => $access->booking_id,
                    'room_id'     => $access->room_id,
                    'amount'      => $total,
                    'description' => ucfirst($data['department']) . " Order ({$order->order_code})",
                ]);

                event(new OrderCreated($order));
            });

            return back()->with('success', 'Order placed successfully.');

        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to place order. Please try again.'
            );
        }
    }

    public function cancel(string $token, Order $order)
    {

        if (! $order->can_be_cancelled) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $order->update([
            'status' => OrderStatus::CANCELLED,
        ]);

        event(new OrderStatusUpdated($order));

        return back()->with('success', 'Order cancelled.');
    }
}
