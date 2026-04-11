<?php

namespace App\Http\Controllers\Staff;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Room;
use App\Services\OrderChargeService;
use App\Services\StaffRoomOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BarOrderController extends Controller
{
    public function index()
    {
        $this->authorizeAreaAccess();

        return Inertia::render('Staff/Bar/Orders', [
            'orders' => $this->activeOrders(),
            'rooms' => $this->occupiedRooms(),
            'menuItems' => $this->menuItems(),
            'paymentOptions' => StaffRoomOrderService::PAYMENT_METHODS,
            'paymentStatuses' => StaffRoomOrderService::PAYMENT_STATUSES,
        ]);
    }

    public function store(Request $request, StaffRoomOrderService $staffRoomOrderService)
    {
        $this->authorizeAreaAccess();

        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $staffRoomOrderService->createManualOrder('bar', $data, $request->user());

        return back()->with('success', 'Bar order created for the room.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeAreaAccess();
        abort_unless($order->service_area === 'bar', 404);

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

        DB::transaction(function () use ($data, $order) {
            if ($data['status'] === 'preparing' && $order->status !== 'preparing') {
                app(OrderChargeService::class)->post($order);
            }

            $order->update(['status' => $data['status']]);
        });

        broadcast(new OrderStatusUpdated($order->fresh(['charge'])))->toOthers();

        return back()->with('success', 'Order status updated.');
    }

    public function updatePayment(Request $request, Order $order, StaffRoomOrderService $staffRoomOrderService)
    {
        $this->authorizeAreaAccess();
        abort_unless($order->service_area === 'bar', 404);

        $data = $request->validate([
            'payment_status' => ['required', 'in:' . implode(',', StaffRoomOrderService::PAYMENT_STATUSES)],
            'payment_method' => ['nullable', 'in:' . implode(',', StaffRoomOrderService::PAYMENT_METHODS)],
        ]);

        $staffRoomOrderService->updatePayment($order, $data);

        return back()->with('success', 'Order payment updated.');
    }

    public function history()
    {
        $this->authorizeAreaAccess();

        $orders = Order::with([
            'room:id,name',
            'booking:id,booking_code,guest_name',
            'items.menuItem.category',
            'items.menuItem.subcategory',
            'charge',
        ])
            ->where('service_area', 'bar')
            ->whereIn('status', ['delivered', 'cancelled'])
            ->latest()
            ->paginate(30);

        return Inertia::render('Staff/Bar/OrderHistory', [
            'orders' => $orders
        ]);
    }

    protected function activeOrders()
    {
        return Order::with([
            'room:id,name',
            'booking:id,booking_code,guest_name',
            'items.menuItem.category',
            'items.menuItem.subcategory',
            'charge',
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
    }

    protected function occupiedRooms()
    {
        return Room::query()
            ->select(['id', 'name'])
            ->whereHas('bookings', fn ($query) => $query->active())
            ->with([
                'bookings' => fn ($query) => $query
                    ->select(['bookings.id', 'bookings.booking_code', 'bookings.guest_name'])
                    ->active()
                    ->latest('check_in'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'active_booking' => optional($room->bookings->first(), fn ($booking) => [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'guest_name' => $booking->guest_name,
                ]),
            ])
            ->values();
    }

    protected function menuItems()
    {
        return MenuItem::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->where('service_area', 'bar')
            ->where('is_available', true)
            ->orderBy('name')
            ->get(['id', 'menu_category_id', 'menu_subcategory_id', 'name', 'price', 'service_area']);
    }

    protected function authorizeAreaAccess(): void
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['md', 'manager', 'frontdesk', 'bar'])) {
            return;
        }

        abort(403);
    }
}
