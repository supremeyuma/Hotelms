<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\BillingService;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService
    ) {}

    public function viewBill(Booking $booking)
    {
        $booking->loadMissing(['rooms.roomType']);

        return Inertia::render('FrontDesk/Billing/Show', [
            'booking' => [
                'id' => $booking->id,
                'guest_name' => $booking->guest_name,
                'room_id' => $booking->room_id,
                'assigned_room_options' => $booking->rooms->map(fn ($room) => [
                    'id' => $room->id,
                    'label' => trim(collect([
                        $room->roomType?->title,
                        $room->name ?: $room->room_number,
                    ])->filter()->implode(' - ')),
                ])->values(),
                'has_multiple_rooms' => $booking->rooms->count() > 1,
            ],
            'billing' => $this->billingService->getBillingHistory($booking),
        ]);
    }

    public function acceptPayment(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $roomId = $this->resolveRoomId($booking, $data['room_id'] ?? null);

        if (! $roomId) {
            return back()->withErrors([
                'room_id' => $booking->rooms()->count() > 1
                    ? 'Select the specific room for this payment.'
                    : 'Assign a room to this booking before recording payment.',
            ]);
        }

        $this->billingService->addPayment(
            booking: $booking,
            roomId: $roomId,
            amount: $data['amount'],
            method: $data['method'],
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null
        );

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function addCharge(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'nullable|integer',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $roomId = $this->resolveRoomId($booking, $data['room_id'] ?? null);

        if (! $roomId) {
            return back()->withErrors([
                'room_id' => $booking->rooms()->count() > 1
                    ? 'Select the specific room for this charge.'
                    : 'Assign a room to this booking before posting a charge.',
            ]);
        }

        $this->billingService->addCharge(
            booking: $booking,
            roomId: $roomId,
            description: $data['description'],
            amount: (float) $data['amount'],
        );

        return back()->with('success', 'Charge added successfully.');
    }

    protected function resolveRoomId(Booking $booking, ?int $roomId): ?int
    {
        if ($roomId) {
            return $roomId;
        }

        $assignedRoomIds = $booking->rooms()->pluck('rooms.id');

        if ($assignedRoomIds->count() === 1) {
            return (int) $assignedRoomIds->first();
        }

        if ($assignedRoomIds->count() > 1) {
            return null;
        }

        return $booking->room_id ? (int) $booking->room_id : null;
    }
}
