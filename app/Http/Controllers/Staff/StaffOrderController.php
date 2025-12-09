<?php
// ========================================================
// Staff\StaffOrderController.php
// Namespace: App\Http\Controllers\Staff
// ========================================================
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:staff|manager|md']);
    }

    /**
     * List queue of orders for staff
     */
    public function listOrders(Request $request)
    {
        $queue = Order::with('items','booking','user')->whereIn('status',['pending','in_progress'])->paginate(20);
        return Inertia::render('Staff/OrdersQueue', ['orders' => $queue]);
    }

    /**
     * Update order status (kitchen -> preparing -> ready -> delivered -> completed)
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $request->validate([
            'status' => 'required|string|in:pending,in_progress,ready,delivered,completed,cancelled'
        ]);

        $old = $order->status;
        $order->update(['status' => $request->status]);

        $order->events()->create([
            'staff_id' => $request->user()->id,
            'event' => 'status_changed',
            'meta' => ['from' => $old, 'to' => $order->status]
        ]);

        AuditLogger::log('order_status_updated', 'Order', $order->id, [
            'from' => $old, 'to' => $order->status
        ]);

        return back()->with('success','Order status updated.');
    }

    /**
     * Laundry pickup
     */
    public function pickupLaundry(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $order->update(['status' => 'in_progress']);
        $order->events()->create(['event' => 'laundry_picked_up','staff_id'=>$request->user()->id]);

        AuditLogger::log('laundry_picked', 'Order', $order->id, ['staff' => $request->user()->id]);

        return back()->with('success','Laundry picked up.');
    }

    /**
     * Deliver laundry
     */
    public function deliverLaundry(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $order->update(['status' => 'delivered']);
        $order->events()->create(['event' => 'laundry_delivered','staff_id'=>$request->user()->id]);

        AuditLogger::log('laundry_delivered', 'Order', $order->id, ['staff' => $request->user()->id]);

        return back()->with('success','Laundry delivered.');
    }

    /**
     * Kitchen order ready
     */
    public function kitchenOrderReady(Request $request, Order $order)
    {
        $this->authorize('update', $order);
        $order->update(['status' => 'ready']);
        $order->events()->create(['event' => 'kitchen_ready','staff_id'=>$request->user()->id]);

        AuditLogger::log('kitchen_ready', 'Order', $order->id, ['staff' => $request->user()->id]);

        return back()->with('success','Order marked ready.');
    }
}
