<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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
            'payment_mode'        => 'required|in:prepaid,pay_on_delivery',
        ]);

        if ($data['payment_mode'] === 'prepaid') {
            return back()->with(
                'error',
                'Online payment for in-room menu orders is not available yet. Please use pay on delivery.'
            );
        }

        try {
            $order = DB::transaction(function () use ($data, $request) {
                $access = $request->attributes->get('roomAccessToken');

                $order = Order::create([
                    'booking_id' => $access->booking_id,
                    'room_id'    => $access->room_id,
                    'service_area' => $data['department'],
                    'status' => $data['payment_mode'] === 'prepaid'
                        ? OrderStatus::PENDING
                        : OrderStatus::CONFIRMED,
                    'cancelable_until' => now()->addMinutes(5),
                    'notes' => $data['notes'] ?? null,
                    'order_code' => strtoupper('ORD-' . uniqid()),
                    'total' => 0,
                ]);

                $total = 0;

                foreach ($data['items'] as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_name' => $item['name'],
                        'price' => $item['price'],
                        'qty' => $item['quantity'],
                        'note' => $item['note'] ?? null,
                    ]);

                    $total += $item['price'] * $item['quantity'];
                }

                $order->update(['total' => $total]);

                $order->charge()->create([
                    'booking_id'   => $access->booking_id,
                    'room_id'      => $access->room_id,
                    'amount'       => $total,
                    'status'       => 'unpaid',
                    'payment_mode' => $data['payment_mode'], // prepaid | pay_on_delivery
                    'charge_date'  => now(),
                    'type'         => $data['department'],
                    'description'  => ucfirst($data['department']) . " Order ({$order->order_code})",
                ]);


                event(new OrderCreated($order));

                return $order; // ✅ return data, not response
            });

            // ✅ NOW decide response (outside transaction)

            return back()->with('success', 'Order confirmed. Pay on delivery.');

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
        $access = request()->attributes->get('roomAccessToken');

        abort_unless(
            $access
            && (int) $order->booking_id === (int) $access->booking_id
            && (int) $order->room_id === (int) $access->room_id,
            403,
            'Order does not belong to this room access token.'
        );

        if (! $order->can_be_cancelled) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => OrderStatus::CANCELLED,
            ]);

            if ($order->charge && $order->charge->status !== 'paid') {
                $order->charge->update([
                    'status' => 'cancelled',
                ]);
            }
        });

        event(new OrderStatusUpdated($order));

        return back()->with('success', 'Order cancelled.');
    }
}
