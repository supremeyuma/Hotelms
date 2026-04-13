<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Payment;
use App\Services\PaymentAccountingService;
use Illuminate\Http\Request;

class StaffChargeController extends Controller
{
    public function __construct(
        protected PaymentAccountingService $paymentAccountingService,
    ) {}

    public function markAsPaid(Request $request, Charge $charge)
    {
        if ($charge->status === 'paid') {
            return back()->with('error', 'Charge is already marked as paid.');
        }

        $request->validate([
            'method' => 'required|in:cash,pos,transfer',
        ]);

        $charge->update([
            'status' => 'paid',
        ]);

        $reference = 'MANUAL-' . strtoupper(uniqid());

        $payment = Payment::create([
            'booking_id' => $charge->booking_id,
            'room_id' => $charge->room_id,
            'amount' => $charge->amount,
            'amount_paid' => $charge->amount,
            'currency' => 'NGN',
            'method' => $request->method,
            'status' => 'successful',
            'reference' => $reference,
            'payment_reference' => $reference,
            'transaction_ref' => $reference,
            'provider' => 'manual',
            'payment_type' => 'charge',
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        $this->paymentAccountingService->handleSuccessful($payment);

        return back()->with('success', 'Charge marked as paid.');
    }
}
