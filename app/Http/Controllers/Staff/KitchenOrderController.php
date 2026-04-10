<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Events\OrderStatusUpdated;
use App\Exceptions\InsufficientInventoryException;
use App\Services\OrderChargeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\KitchenInventoryService;
use Illuminate\Support\Facades\DB;

class KitchenOrderController extends Controller
{
    public function index()
    {
        $orders = Order::with([
            'items.menuItem.category',
            'items.menuItem.subcategory', 'charge',
        ])
        ->where('service_area', 'kitchen')
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

        return Inertia::render('Staff/Kitchen/Orders', [
            'orders' => $orders
        ]);
    }


    public function updateStatus(Request $request, Order $order, KitchenInventoryService $inventory)
    {
        $data = $request->validate([
            'status' => 'required|in:preparing,ready,delivered'
        ]);

        if (
            $data['status'] === 'preparing' &&
            $order->charge &&
            $order->charge->payment_mode === 'prepaid' &&
            $order->charge->status === 'unpaid'
        ) {
            return back()->with('error', 'Order cannot be prepared until payment is completed.');
        }

        try {
            DB::transaction(function () use ($data, $order, $inventory) {
                $previousStatus = $order->status;

                if ($data['status'] === 'preparing' && $previousStatus !== 'preparing') {
                    app(OrderChargeService::class)->post($order);
                    $inventory->consumeForOrder($order->loadMissing('items.menuItem'));
                }

                $order->update(['status' => $data['status']]);
            });
        } catch (InsufficientInventoryException $e) {
            return back()->with('error', "Kitchen stock is insufficient. Available quantity in source location: {$e->available}.");
        }

        broadcast(new OrderStatusUpdated($order->fresh()))->toOthers();

        return back();
    }

    public function history()
    {
        $orders = Order::with([
            'items.menuItem.category',
            'items.menuItem.subcategory', 'charge',
        ])
        ->where('service_area', 'kitchen')
        ->whereIn('status', ['delivered', 'cancelled'])
        ->latest()
        ->paginate(30);

        return Inertia::render('Staff/Kitchen/OrderHistory', [
            'orders' => $orders
        ]);
    }

}
