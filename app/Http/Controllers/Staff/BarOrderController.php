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
        return Inertia::render('Staff/Bar/Orders', [
            'orders' => Order::with('items.menuItem')
                ->where('service_area', 'bar')
                ->whereIn('status', ['pending','preparing','ready'])
                ->latest()
                ->get()
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
}
