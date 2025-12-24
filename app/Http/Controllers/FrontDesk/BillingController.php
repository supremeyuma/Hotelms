<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\BillingService;

class BillingController extends Controller
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function viewBill(Booking $booking)
    {
        $billing = $this->billingService->getBillingHistory($booking);

        return Inertia::render('FrontDesk/Billing/Show', [
            'booking' => $booking,
            'billing' => $billing,
        ]);
    }

    public function acceptPayment(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        $this->billingService->addPayment($booking, $data['amount'], $data['method'], $data['notes'] ?? null);

        return back()->with('success', 'Payment recorded successfully.');
    }

    public function settleFull(Booking $booking)
    {
        $this->billingService->settleFullAmount($booking, 'Cash'); // or method passed from request

        return back()->with('success', 'Outstanding balance fully settled.');
    }
}
