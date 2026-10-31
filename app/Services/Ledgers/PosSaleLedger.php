<?php

namespace App\Services\Ledgers;

use App\Models\Account;
use App\Models\PosDocket;
use App\Services\AccountingService;
use Illuminate\Support\Facades\Log;

/**
 * Post a settled Club POS docket to the ledger.
 *
 * A cash sale is money in and revenue earned: debit the asset that received the
 * cash (or the bank for card/mobile money), credit the revenue accounts the sold
 * items belong to, and credit VAT and service charge where they apply. Room
 * charges are deliberately skipped — `BillingService::addCharge()` already posts
 * them to the guest's folio, and posting here as well would double the revenue.
 *
 * Accounting never blocks a sale. If the chart of accounts is incomplete the
 * sale still commits; the failure is logged for the finance team instead.
 */
class PosSaleLedger
{
    public function __construct(
        protected AccountingService $accounting,
    ) {
    }

    /**
     * @param array<int, array{method: string, amount: numeric}> $payments
     */
    public function postSale(PosDocket $docket, array $payments): void
    {
        $total = round((float) $docket->total, 2);

        if ($total <= 0) {
            return;
        }

        try {
            // --- Debits: what actually came in, per payment method -----------
            $debits = [];
            foreach ($payments as $payment) {
                $method = (string) ($payment['method'] ?? '');
                $amount = round((float) ($payment['amount'] ?? 0), 2);

                if ($amount <= 0 || $method === 'room_charge') {
                    continue;
                }

                $accountName = in_array($method, ['cash', 'stock_consumption'], true) ? 'Cash' : 'Bank';
                $accountId = $this->accountId($accountName);

                if ($accountId === null) {
                    Log::warning('POS accounting failed: missing account', [
                        'docket' => $docket->docket_number,
                        'account' => $accountName,
                    ]);
                    return;
                }

                $debits[$accountId] = round(($debits[$accountId] ?? 0) + $amount, 2);
            }

            if ($debits === []) {
                return;
            }

            $debitTotal = round(array_sum($debits), 2);

            // --- Credits: revenue by service area, then tax ------------------
            $revenueTotal = round((float) $docket->subtotal - (float) $docket->discount_amount, 2);
            $serviceAreas = $this->revenueWeights($docket);

            $vat = round((float) $docket->vat, 2);
            $serviceCharge = round((float) $docket->service_charge, 2);

            $vatAccount = $vat > 0 ? $this->accountId('VAT Payable') : null;
            $serviceAccount = $serviceCharge > 0 ? $this->accountId('Service Charge Revenue') : null;

            // Tax accounts are optional (created by the tax-account action); if
            // one is absent its amount stays in revenue so the entry balances.
            $revenueTotal += ($vat > 0 && $vatAccount === null) ? $vat : 0;
            $revenueTotal += ($serviceCharge > 0 && $serviceAccount === null) ? $serviceCharge : 0;

            $credits = $this->splitRevenue($revenueTotal, $serviceAreas);
            if ($vatAccount !== null) {
                $credits[$vatAccount] = round(($credits[$vatAccount] ?? 0) + $vat, 2);
            }
            if ($serviceAccount !== null) {
                $credits[$serviceAccount] = round(($credits[$serviceAccount] ?? 0) + $serviceCharge, 2);
            }

            // Keep the entry balanced after rounding: push the difference onto
            // the largest revenue line.
            $creditTotal = round(array_sum($credits), 2);
            $difference = round($debitTotal - $creditTotal, 2);
            if ($difference !== 0.0) {
                $largest = array_keys($credits)[0] ?? null;
                if ($largest === null) {
                    return;
                }
                $credits[$largest] = round($credits[$largest] + $difference, 2);
            }

            $lines = [];
            foreach ($debits as $accountId => $amount) {
                $lines[] = ['account_id' => $accountId, 'debit' => $amount, 'credit' => 0];
            }
            foreach ($credits as $accountId => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => $amount];
            }

            $this->accounting->postEntry(
                referenceType: 'pos_docket',
                referenceId: (int) $docket->docket_number,
                description: "Club POS sale (docket #{$docket->docket_number})",
                lines: $lines,
                userId: $docket->closed_by ?? $docket->staff_id,
                date: $docket->closed_at ?? now(),
            );
        } catch (\Throwable $e) {
            Log::warning('POS accounting failed', [
                'docket' => $docket->docket_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Revenue credit per service area, weighted by the value of the items sold.
     *
     * @return array<string, float>
     */
    private function revenueWeights(PosDocket $docket): array
    {
        $weights = [];

        foreach ($docket->items as $item) {
            $area = $item->menuItem?->service_area === 'kitchen' ? 'kitchen' : 'bar';
            $weights[$area] = ($weights[$area] ?? 0) + ((float) $item->subtotal);
        }

        if ($weights === []) {
            return ['bar' => 1.0];
        }

        return $weights;
    }

    /**
     * @param array<string, float> $weights
     * @return array<int, float> account id => amount
     */
    private function splitRevenue(float $revenueTotal, array $weights): array
    {
        $credits = [];
        $weightTotal = array_sum($weights) ?: 1.0;
        $remaining = $revenueTotal;
        $areas = array_keys($weights);
        $last = end($areas);

        foreach ($areas as $area) {
            $accountName = $area === 'kitchen' ? 'Food Revenue' : 'Beverage Revenue';
            $accountId = $this->accountId($accountName);

            if ($accountId === null) {
                Log::warning('POS accounting failed: missing account', ['account' => $accountName]);
                return [];
            }

            $amount = $area === $last
                ? $remaining
                : round($revenueTotal * ($weights[$area] / $weightTotal), 2);

            $remaining = round($remaining - $amount, 2);
            $credits[$accountId] = round(($credits[$accountId] ?? 0) + $amount, 2);
        }

        return $credits;
    }

    private function accountId(string $name): ?int
    {
        $id = Account::where('name', $name)->value('id');

        return $id === null ? null : (int) $id;
    }
}
