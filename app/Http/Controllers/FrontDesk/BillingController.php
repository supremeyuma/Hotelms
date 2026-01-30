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
        return Inertia::render('FrontDesk/Billing/Show', [
            'booking' => $booking,
            'billing' => $this->billingService->getBillingHistory($booking),
        ]);
    }

    public function acceptPayment(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $this->billingService->addPayment(
            booking: $booking,
            roomId: $data['room_id'],
            amount: $data['amount'],
            method: $data['method'],
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null
        );

        return back()->with('success', 'Payment recorded successfully.');
    }
}
