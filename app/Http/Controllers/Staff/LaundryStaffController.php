<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LaundryItem;
use App\Models\LaundryOrder;
use App\Models\Room;
use App\Enums\LaundryStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\LaundryOrderService;

class LaundryStaffController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $orders = LaundryOrder::with([
            'room',
            'items.item',
            'images',
            'statusHistories.changer',
            'charge',
        ])
        ->when($status, fn ($q) => $q->where('status', $status))
        ->latest()
        ->get();

        return Inertia::render('Staff/Laundry/Dashboard', [
            'orders' => $orders,
            'statuses' => LaundryStatus::cases(),
            'activeStatus' => $status,
            'rooms' => $this->occupiedRooms(),
            'items' => LaundryItem::query()
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'description']),
        ]);
    }

    public function show(LaundryOrder $order)
    {
        $order->load([
            'room',
            'items.item',
            'images',
            'statusHistories.changer',
            'charge',
        ]);

        return Inertia::render('Staff/Laundry/Show', [
            'order' => $order,
            'statuses' => collect(LaundryStatus::cases())
                ->map(fn ($s) => $s->value)
                ->values(),
        ]);
    }

    public function updateStatus(
        Request $request,
        LaundryOrder $order,
        LaundryOrderService $service
    ) {
        $request->validate([
            'status' => ['required', 'string'],
        ]);

        // 🔒 HARD BACKEND GUARD
        if (
            $order->charge &&
            $order->charge->payment_mode === 'prepaid' &&
            $order->charge->status === 'unpaid'
        ) {
            return back()->with(
                'error',
                'Laundry order cannot be processed until payment is completed.'
            );
        }

        $newStatus = LaundryStatus::from($request->status);

        $service->updateStatus(
            $order,
            $newStatus,
            auth()->id()
        );

        return back();
    }

    public function store(Request $request, LaundryOrderService $service)
    {
        $data = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.laundry_item_id' => ['required', 'exists:laundry_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'images.*' => ['nullable', 'file', 'image', 'max:2048'],
            'payment_mode' => ['required', 'in:postpaid'],
        ]);

        $room = Room::query()
            ->select(['id', 'name'])
            ->whereKey($data['room_id'])
            ->with([
                'bookings' => fn ($query) => $query
                    ->select(['bookings.id', 'bookings.booking_code', 'bookings.guest_name'])
                    ->whereIn('bookings.status', ['active', 'checked_in'])
                    ->latest('check_in'),
            ])
            ->firstOrFail();

        $booking = $room->bookings->first();

        if (! $booking) {
            return back()->withErrors([
                'room_id' => 'The selected room does not have an active in-house booking.',
            ]);
        }

        $uploadedPaths = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $uploadedPaths[] = $file->store('laundry', 'public');
            }
        }

        $order = $service->createOrder(
            $room->id,
            $booking->id,
            $data['items'],
            $uploadedPaths,
            $request->user()?->id,
            $data['payment_mode'],
        );

        return redirect()->route('staff.laundry.show', $order)
            ->with('success', "Laundry order {$order->order_code} created successfully.");
    }
    public function cancel(
        LaundryOrder $order,
        LaundryOrderService $service
    ) {
        abort_unless(
            in_array(
                LaundryStatus::CANCELLED,
                LaundryStatus::allowedTransitions($order->status),
                true
            ),
            403,
            'Invalid cancellation'
        );

        $service->updateStatus(
            $order,
            LaundryStatus::CANCELLED,
            auth()->id()
        );

        return back();
    }

    public function addImages(Request $request, LaundryOrder $order)
    {
        $request->validate([
            'images.*' => 'required|image|max:2048',
        ]);

        foreach ($request->file('images') as $file) {
            $order->images()->create([
                'path' => $file->store('laundry', 'public'),
            ]);
        }

        event(new \App\Events\LaundryOrderUpdated($order->fresh()));

        return back();
    }

    public function print(LaundryOrder $order)
    {
        $order->load(['room', 'items.item']);

        return Inertia::render('Staff/Laundry/Print', [
            'order' => $order,
        ]);
    }

    protected function occupiedRooms()
    {
        return Room::query()
            ->select(['id', 'name'])
            ->whereHas('bookings', fn ($query) => $query->whereIn('bookings.status', ['active', 'checked_in']))
            ->with([
                'bookings' => fn ($query) => $query
                    ->select(['bookings.id', 'bookings.booking_code', 'bookings.guest_name'])
                    ->whereIn('bookings.status', ['active', 'checked_in'])
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
}
