<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Payment;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class StaffChargeController extends Controller
{
    public function markAsPaid(Request $request, Charge $charge)
    {
        // Prevent double settlement
        if ($charge->status === 'paid') {
            return back()->with('error', 'Charge is already marked as paid.');
        }

        $request->validate([
            'method' => 'required|in:cash,pos,transfer',
        ]);

        // 1️⃣ Mark charge as paid
        $charge->update([
            'status' => 'paid',
        ]);

        // 2️⃣ Create payment record
        $payment = Payment::create([
            'booking_id' => $charge->booking_id,
            'room_id'    => $charge->room_id,
            'amount'     => $charge->amount,
            'currency'   => 'NGN',
            'status'     => 'successful',
            'reference'  => 'MANUAL-' . strtoupper(uniqid()),
            'paid_at'    => now(),
        ]);

        // 3️⃣ Audit
        /*AuditLogger::log(
            'charge_marked_paid',
            'Charge',
            $charge->id,
            [
                'payment_id' => $payment->id,
                'method' => $request->method,
                'amount' => $charge->amount,
            ]
        );*/

        return back()->with('success', 'Charge marked as paid.');
    }
}
