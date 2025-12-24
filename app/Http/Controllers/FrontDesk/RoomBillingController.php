<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RoomBillingService;
use App\Models\Room;
class RoomBillingController extends Controller
{
    public function show(Room $room, RoomBillingService $billing)
    {
        return Inertia::render('FrontDesk/Rooms/Billing', [
            'room' => $room,
            'billing' => $billing->history($room),
        ]);
    }

    public function pay(Request $request, Room $room, RoomBillingService $billing)
    {
        $data = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $billing->pay(
            $room,
            Booking::findOrFail($data['booking_id']),
            $data['amount'],
            $data['method'],
            $data['notes']
        );

        return back()->with('success', 'Payment recorded');
    }
}
