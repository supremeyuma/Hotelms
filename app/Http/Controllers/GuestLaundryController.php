<?php
// app/Http/Controllers/GuestLaundryController.php

namespace App\Http\Controllers;

use App\Enums\LaundryStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Services\LaundryOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\LaundryItem;
use Illuminate\Support\Facades\DB;

class GuestLaundryController extends Controller
{
    protected LaundryOrderService $service;

    public function __construct(LaundryOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Show laundry modal (items + prices)
     */
    public function show(Request $request)
    {
        $room = $request->get('guestRoom');
        $booking = $request->get('guestBooking');
        // Fetch all laundry items
        $items = LaundryItem::all(); // or filter by property if needed

        return Inertia::render('Guest/LaundryModal', [
            'room' => $room,
            'bookingId' => $booking->id,
            'items' => $items,
        ]);
    }

    

    /**
     * Submit laundry order
     */
    public function store(Request $request, Room $room)
    {   
        $room = $request->get('guestRoom');
        $booking = $request->get('guestBooking');
        //dd($room, $booking);
        //$this->authorize('requestLaundry', $room);

        $data = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'items' => 'required|array|min:1',
            'items.*.laundry_item_id' => 'required|exists:laundry_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'images.*' => 'nullable|file|image|max:2048',
        ]);

        // Handle image uploads
        $uploadedPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $uploadedPaths[] = $file->store('laundry', 'public');
            }
        }

        $order = $this->service->createOrder(
            $room->id,
            $data['booking_id'],
            $data['items'],
            $uploadedPaths,
            $request->user()->id
        );

        return redirect()->back()->with('success', "Laundry order {$order->order_code} submitted!");
    }
}
