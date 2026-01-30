<?php

namespace App\Services;

use App\Models\Payment;
use App\Services\Ledgers\PaymentLedger;
use Illuminate\Support\Facades\Log;

class PaymentAccountingService
{
    public function __construct(
        protected PaymentLedger $ledger
    ) {}

    /**
     * Call when payment becomes SUCCESSFUL
     */
    public function handleSuccessful(Payment $payment): void
    {
        if ($payment->amount <= 0) {
            return;
        }

        try {
            $this->ledger->postPayment($payment);
        } catch (\Throwable $e) {
            Log::error('Payment accounting failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Call when a successful payment is refunded/reversed
     */
    public function handleReversal(Payment $payment): void
    {
        if ($payment->amount <= 0) {
            return;
        }

        try {
            $this->ledger->reversePayment($payment);
        } catch (\Throwable $e) {
            Log::error('Payment reversal accounting failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
