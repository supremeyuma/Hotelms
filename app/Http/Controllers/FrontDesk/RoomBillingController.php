<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;

class RoomBillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
    ) {}

    public function show(Room $room): RedirectResponse
    {
        $booking = $this->resolveCurrentBooking($room);

        return redirect()->route('frontdesk.billing.show', $booking);
    }

    public function pay(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $booking = $this->resolveCurrentBooking($room);

        $this->billingService->addPayment(
            booking: $booking,
            roomId: $room->id,
            amount: (float) $data['amount'],
            method: $data['method'],
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return redirect()
            ->route('frontdesk.billing.show', $booking)
            ->with('success', 'Payment recorded successfully.');
    }

    protected function resolveCurrentBooking(Room $room): Booking
    {
        return Booking::query()
            ->whereHas('rooms', fn ($query) => $query->where('rooms.id', $room->id))
            ->whereIn('status', ['checked_in', 'confirmed', 'pending_payment'])
            ->orderByRaw("CASE status WHEN 'checked_in' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'pending_payment' THEN 2 ELSE 3 END")
            ->latest('check_in')
            ->latest('id')
            ->firstOrFail();
    }
}
