<?php

namespace App\Services\Ledgers;

use App\Models\Account;
use App\Models\Payment;
use App\Services\AccountingService;

class PaymentLedger
{
    public function __construct(
        protected AccountingService $accounting
    ) {}

    /**
     * Record successful payment (AR -> Cash/Bank)
     */
    public function postPayment(Payment $payment): void
    {
        $assetAccount = $this->resolveAssetAccount($payment);

        $this->accounting->postEntry(
            referenceType: 'payment',
            referenceId: $payment->id,
            description: "Payment received (Room {$payment->room_id}, Booking {$payment->booking_id})",
            lines: [
                [
                    'account_id' => $assetAccount,
                    'debit' => $payment->amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->account('Accounts Receivable'),
                    'debit' => 0,
                    'credit' => $payment->amount,
                ],
            ],
            userId: auth()->id(),
            date: $payment->paid_at
        );
    }

    /**
     * Reverse payment (refund / chargeback)
     */
    public function reversePayment(Payment $payment): void
    {
        $assetAccount = $this->resolveAssetAccount($payment);

        $this->accounting->postEntry(
            referenceType: 'payment-reversal',
            referenceId: $payment->id,
            description: "Payment reversal (Room {$payment->room_id}, Booking {$payment->booking_id})",
            lines: [
                [
                    'account_id' => $this->account('Accounts Receivable'),
                    'debit' => $payment->amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $assetAccount,
                    'debit' => 0,
                    'credit' => $payment->amount,
                ],
            ],
            userId: auth()->id()
        );
    }

    protected function resolveAssetAccount(Payment $payment): int
    {
        if ($payment->flutterwave_tx_id) {
            return $this->account('Bank');
        }

        return $this->account('Cash');
    }

    protected function account(string $name): int
    {
        return Account::where('name', $name)->value('id');
    }
}
