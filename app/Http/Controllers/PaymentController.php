<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Services\AuditLogger;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Booking $booking)
    {
        $this->authorize('create', Payment::class);

        $payment = $booking->payments()->create($request->validated());

        AuditLogger::log('payment_recorded', 'Payment', $payment->id, [
            'booking_id' => $booking->id
        ]);

        return back()->with('success', 'Payment recorded successfully.');
    }
}
