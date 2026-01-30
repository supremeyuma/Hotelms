<?php

namespace App\Services\Ledgers;

use App\Models\Account;
use App\Services\AccountingService;

class ServiceRevenueLedger
{
    public function __construct(
        protected AccountingService $accounting
    ) {}

    /**
     * Post service charge (order/laundry) to AR + Revenue
     */
    public function postCharge(
        string $service,          // kitchen | bar | laundry
        int $bookingId,
        int $roomId,
        float $amount,
        string $referenceType,
        int $referenceId
    ): void {
        $revenueAccount = match ($service) {
            'kitchen' => 'Food Revenue',
            'bar'     => 'Beverage Revenue',
            'laundry' => 'Laundry Revenue',
            default   => throw new \InvalidArgumentException('Invalid service'),
        };

        $this->accounting->postEntry(
            referenceType: $referenceType,
            referenceId: $referenceId,
            description: strtoupper($service) . " charge (Room {$roomId}, Booking {$bookingId})",
            lines: [
                [
                    'account_id' => $this->account('Accounts Receivable'),
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->account($revenueAccount),
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
            userId: auth()->id()
        );
    }

    /**
     * Reverse a previously posted charge (cancellation)
     */
    public function reverseCharge(
        string $service,
        int $bookingId,
        int $roomId,
        float $amount,
        string $referenceType,
        int $referenceId
    ): void {
        $revenueAccount = match ($service) {
            'kitchen' => 'Food Revenue',
            'bar'     => 'Beverage Revenue',
            'laundry' => 'Laundry Revenue',
            default   => throw new \InvalidArgumentException('Invalid service'),
        };

        $this->accounting->postEntry(
            referenceType: $referenceType . '-reversal',
            referenceId: $referenceId,
            description: strtoupper($service) . " reversal (Room {$roomId}, Booking {$bookingId})",
            lines: [
                [
                    'account_id' => $this->account($revenueAccount),
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->account('Accounts Receivable'),
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
            userId: auth()->id()
        );
    }

    protected function account(string $name): int
    {
        return Account::where('name', $name)->value('id');
    }
}
