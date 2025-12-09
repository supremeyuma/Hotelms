<?php
// ========================================================
// RoomServiceController.php
// Namespace: App\Http\Controllers
// ========================================================
namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\AuditLogger;
use App\Http\Requests\OrderRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomServiceController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Show menu for a particular room (QR links)
     */
    public function showMenuForRoom(Room $room)
    {
        $room->load(['roomType','property']);
        // menu items could be fetched from settings or DB - pass basic details
        $menu = [
            'kitchen' => [['name'=>'Sandwich','price'=>1200], ['name'=>'Burger','price'=>1800]],
            'laundry' => [['name'=>'Shirt Wash','price'=>500]],
            'housekeeping' => [['name'=>'Extra Towels','price'=>0]],
            'maintenance' => [['name'=>'Fix Faucet','price'=>0]],
        ];

        return Inertia::render('RoomService/RoomService', [
            'room' => $room,
            'menu' => $menu,
        ]);
    }

    /**
     * Place order for room service
     */
    public function placeOrder(OrderRequest $request, Room $room)
    {
        $payload = $request->validated();
        $payload['booking_id'] = $payload['booking_id'] ?? null;
        $payload['user_id'] = auth()->id() ?? $payload['user_id'] ?? null;
        $payload['metadata'] = [
            'room_id' => $room->id
        ];

        $order = $this->orderService->createOrder(array_merge($payload, [
            'order_code' => 'ORD-' . strtoupper(uniqid()),
        ]));

        AuditLogger::log('order_placed', 'Order', $order->id, [
            'room_id' => $room->id,
            'placed_by' => $payload['user_id']
        ]);

        return redirect()->route('room.service', ['room' => $room->id])
            ->with('success', 'Order placed successfully.');
    }

    /**
     * Track order by order_code (public)
     */
    public function trackOrder(string $order_code)
    {
        $order = Order::with('items','booking','user')->where('order_code', $order_code)->firstOrFail();
        return Inertia::render('RoomService/Track', ['order' => $order]);
    }

    /**
     * List available services (kitchen, laundry etc.)
     */
    public function listServices()
    {
        // Could be pulled from settings table
        $services = ['kitchen','laundry','housekeeping','maintenance'];
        return response()->json(['services' => $services]);
    }
}
