<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function initialize(Request $request)
    {
        $request->validate([
            'booking_id' => 'nullable|exists:bookings,id',
            'room_id' => 'nullable|exists:rooms,id',
            'amount' => 'required|numeric|min:1',
            'tx_ref' => 'nullable|string|max:191',
            'description' => 'nullable|string|max:1000',
        ]);

        // Allow caller to pass an external tx_ref (e.g. event ticket QR code)
        $reference = $request->input('tx_ref') ?: ('PAY-' . Str::uuid());

        $payment = Payment::create([
            'booking_id' => $request->booking_id ?? null,
            'room_id' => $request->room_id ?? null,
            'amount' => $request->amount,
            'currency' => 'NGN',
            'reference' => $reference,
            'status' => 'pending',
            'raw_response' => null,
        ]);

        return response()->json([
            'public_key' => config('flutterwave.public_key'),
            'tx_ref' => $reference,
            'amount' => $payment->amount,
            'currency' => 'NGN',
            'description' => $request->input('description') ?? null,
            'customer' => [
                'email' => $request->user()->email ?? $request->input('customer_email') ?? 'guest@hotel.com',
                'name' => $request->user()->name ?? $request->input('customer_name') ?? 'Hotel Guest',
            ],
        ]);
    }


    public function verify(string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        $response = Http::withToken(config('flutterwave.secret_key'))
            ->get("https://api.flutterwave.com/v3/transactions/verify_by_reference", [
                'tx_ref' => $reference,
            ]);

        $data = $response->json();

        if (
            $data['status'] === 'success' &&
            $data['data']['status'] === 'successful' &&
            $data['data']['amount'] == $payment->amount &&
            $data['data']['currency'] === 'NGN'
        ) {
            $payment->update([
                'status' => 'successful',
                'flutterwave_tx_id' => $data['data']['id'],
                'raw_response' => $data,
                'paid_at' => now(),
            ]);

            // TODO: mark booking bill as paid / reduce outstanding balance
            try {
                resolve(\App\Services\PaymentAccountingService::class)->handleSuccessful($payment);
            } catch (\Exception $e) {
                // non-fatal, ensure payment remains recorded
            }

            return response()->json(['message' => 'Payment verified']);
        }

        $payment->update([
            'status' => 'failed',
            'raw_response' => $data,
        ]);

        return response()->json(['message' => 'Payment verification failed'], 422);
    }



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
