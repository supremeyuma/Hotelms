<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Events\OrderStatusUpdated;
use App\Services\OrderChargeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KitchenOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Staff/Kitchen/Orders', [
            'orders' => Order::with('items.menuItem')
                ->where('service_area', 'kitchen')
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

        $order->update(['status' => $request->status]);

        broadcast(new OrderStatusUpdated($order))->toOthers();

        if ($request->status === 'delivered') {
            app(OrderChargeService::class)->post($order);
        }

        return back();
    }
}
