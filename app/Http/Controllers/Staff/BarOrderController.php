<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Events\OrderStatusUpdated;
use App\Services\OrderChargeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Charge;

class BarOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with([
            'items.menuItem.category',
            'items.menuItem.subcategory', 'charge',
        ])
        ->where('service_area', 'bar')
        ->whereIn('status', ['pending', 'preparing', 'confirmed'])
        ->where(function ($q) {
            $q->whereDoesntHave('charge')
            ->orWhereHas('charge', function ($c) {
                $c->where('payment_mode', '!=', 'prepaid')
                    ->orWhere('status', 'paid');
            });
        })
        ->latest()
        ->get();

        return Inertia::render('Staff/Bar/Orders', [
            'orders' => $orders
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:preparing,ready,delivered'
        ]);

        //dd($order);

        $order->update(['status' => $request->status]);

        broadcast(new OrderStatusUpdated($order))->toOthers();

        if (
            $request->status === 'preparing' &&
            $order->charge &&
            $order->charge->payment_mode === 'prepaid' &&
            $order->charge->status === 'unpaid'
        ) {
            return back()->with('error', 'Order cannot be prepared until payment is completed.');
        }


        if ($request->status === 'preparing') {
            app(OrderChargeService::class)->post($order);
            
            /*Charge::create([
                'booking_id' => $order->booking_id,
                'room_id' => $order->room_id,
                'amount' => $order->total,
                'description' => "Bar Order: {$order->order_code}",
            ]);*/
        }

        return back();
    }

    public function history()
    {
        $orders = Order::with([
            'items.menuItem.category',
            'items.menuItem.subcategory'
        ])
        ->where('service_area', 'bar')
        ->whereIn('status', ['delivered', 'cancelled'])
        ->latest()
        ->paginate(30);

        return Inertia::render('Staff/Bar/OrderHistory', [
            'orders' => $orders
        ]);
    }
}
