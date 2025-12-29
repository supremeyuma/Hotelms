<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:staff|manager|md']);
    }

    /**
     * List active orders for staff queue
     */
    public function listOrders(Request $request)
    {
        $orders = Order::with([
                'items',
                'booking',
                'room',
                'guestRequest',
            ])
            ->whereIn('status', ['pending', 'in_progress', 'ready'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Staff/OrdersQueue', [
            'orders' => $orders,
        ]);
    }

    /**
     * Update order status (ALL services)
     */
    public function updateOrderStatus(
        Request $request,
        Order $order,
        OrderWorkflowService $service
    ) {
        $this->authorize('update', $order);

        $request->validate([
            'status' => ['required', 'string'],
        ]);

        $service->updateStatus(
            $order,
            $request->status,
            $request->user()->id
        );

        return back()->with('success', 'Order status updated.');
    }

    /**
     * Laundry pickup shortcut
     */
    public function pickupLaundry(
        Request $request,
        Order $order,
        OrderWorkflowService $service
    ) {
        $this->authorize('update', $order);

        $service->updateStatus(
            $order,
            'in_progress',
            $request->user()->id,
            'laundry_picked_up'
        );

        return back()->with('success', 'Laundry picked up.');
    }

    /**
     * Laundry delivery shortcut
     */
    public function deliverLaundry(
        Request $request,
        Order $order,
        OrderWorkflowService $service
    ) {
        $this->authorize('update', $order);

        $service->updateStatus(
            $order,
            'delivered',
            $request->user()->id,
            'laundry_delivered'
        );

        return back()->with('success', 'Laundry delivered.');
    }

    /**
     * Kitchen order ready shortcut
     */
    public function kitchenOrderReady(
        Request $request,
        Order $order,
        OrderWorkflowService $service
    ) {
        $this->authorize('update', $order);

        $service->updateStatus(
            $order,
            'ready',
            $request->user()->id,
            'kitchen_ready'
        );

        return back()->with('success', 'Order marked ready.');
    }
}
