<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Services\AccountingService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class TaxService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Get VAT rate from config
     */
    protected function getVatRate(): float
    {
        if (! $this->isEnabled()) {
            return 0.0;
        }

        return config('tax.vat_rate', 0.075);
    }

    /**
     * Get service charge rate from config
     */
    protected function getServiceChargeRate(): float
    {
        if (! $this->isEnabled()) {
            return 0.0;
        }

        return config('tax.service_charge_rate', 0.10);
    }

    protected function isEnabled(): bool
    {
        return (bool) config('tax.enabled', false);
    }

    public function calculateVAT(float $amount): float
    {
        return round($amount * $this->getVatRate(), 2);
    }

    public function calculateServiceCharge(float $amount): float
    {
        return round($amount * $this->getServiceChargeRate(), 2);
    }

    public function calculateTotalTaxes(float $amount): array
    {
        return [
            'vat' => $this->calculateVAT($amount),
            'service_charge' => $this->calculateServiceCharge($amount),
            'total' => $this->calculateVAT($amount) + $this->calculateServiceCharge($amount),
        ];
    }

    public function postVAT(
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        ?int $userId = null,
        ?Carbon $date = null
    ): void {
        $vatAmount = $this->calculateVAT($amount);

        if ($vatAmount <= 0) {
            return;
        }

        $vatPayableCode = config('tax.accounts.vat_payable', '2001');
        $salesTaxExpenseCode = config('tax.accounts.sales_tax_expense', '5001');

        $vatPayableAccount = $this->getAccountByCode($vatPayableCode);
        $salesTaxAccount = $this->getAccountByCode($salesTaxExpenseCode);

        if (!$vatPayableAccount || !$salesTaxAccount) {
            throw new Exception("Tax accounts not configured. Please create accounts with codes {$vatPayableCode} (VAT Payable) and {$salesTaxExpenseCode} (Sales Tax Expense).");
        }

        $lines = [
            [
                'account_id' => $salesTaxAccount->id,
                'debit' => $vatAmount,
                'credit' => 0,
            ],
            [
                'account_id' => $vatPayableAccount->id,
                'debit' => 0,
                'credit' => $vatAmount,
            ],
        ];

        $this->accountingService->postEntry(
            $referenceType,
            $referenceId,
            "VAT: {$description}",
            $lines,
            $userId,
            $date
        );
    }

    public function postServiceCharge(
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        ?int $userId = null,
        ?Carbon $date = null
    ): void {
        $serviceChargeAmount = $this->calculateServiceCharge($amount);

        if ($serviceChargeAmount <= 0) {
            return;
        }

        $serviceChargeReceivableCode = config('tax.accounts.service_charge_receivable', '1002');
        $serviceChargeRevenueCode = config('tax.accounts.service_charge_revenue', '4001');

        $serviceChargeReceivableAccount = $this->getAccountByCode($serviceChargeReceivableCode);
        $serviceChargeRevenueAccount = $this->getAccountByCode($serviceChargeRevenueCode);

        if (!$serviceChargeReceivableAccount || !$serviceChargeRevenueAccount) {
            throw new Exception("Service charge accounts not configured. Please create accounts with codes {$serviceChargeReceivableCode} (Service Charge Receivable) and {$serviceChargeRevenueCode} (Service Charge Revenue).");
        }

        $lines = [
            [
                'account_id' => $serviceChargeReceivableAccount->id,
                'debit' => $serviceChargeAmount,
                'credit' => 0,
            ],
            [
                'account_id' => $serviceChargeRevenueAccount->id,
                'debit' => 0,
                'credit' => $serviceChargeAmount,
            ],
        ];

        $this->accountingService->postEntry(
            $referenceType,
            $referenceId,
            "Service Charge: {$description}",
            $lines,
            $userId,
            $date
        );
    }

    public function postAllTaxes(
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description,
        ?int $userId = null,
        ?Carbon $date = null
    ): void {
        DB::transaction(function () use ($amount, $referenceType, $referenceId, $description, $userId, $date) {
            $this->postVAT($amount, $referenceType, $referenceId, $description, $userId, $date);
            $this->postServiceCharge($amount, $referenceType, $referenceId, $description, $userId, $date);
        });
    }

    protected function getAccountByCode(string $code): ?Account
    {
        return Account::where('code', $code)->first();
    }

    public function createDefaultTaxAccounts(): void
    {
        $accounts = [
            [
                'code' => config('tax.accounts.vat_payable', '2001'),
                'name' => 'VAT Payable',
                'type' => 'liability',
                'is_system' => true,
            ],
            [
                'code' => config('tax.accounts.service_charge_receivable', '1002'),
                'name' => 'Service Charge Receivable',
                'type' => 'asset',
                'is_system' => true,
            ],
            [
                'code' => config('tax.accounts.service_charge_revenue', '4001'),
                'name' => 'Service Charge Revenue',
                'type' => 'revenue',
                'is_system' => true,
            ],
            [
                'code' => config('tax.accounts.sales_tax_expense', '5001'),
                'name' => 'Sales Tax Expense',
                'type' => 'expense',
                'is_system' => true,
            ],
        ];

        foreach ($accounts as $accountData) {
            Account::firstOrCreate(
                ['code' => $accountData['code']],
                $accountData
            );
        }
    }

    public function reverseTaxEntry(
        string $referenceType,
        int $referenceId,
        string $reason,
        ?int $userId = null
    ): void {
        // Find the original tax journal entries
        $entries = DB::table('journal_entries')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('description', 'like', 'VAT:%')
            ->orWhere('description', 'like', 'Service Charge:%')
            ->get();

        foreach ($entries as $entry) {
            // Create reversal lines
            $lines = DB::table('journal_lines')
                ->where('journal_entry_id', $entry->id)
                ->get()
                ->map(function ($line) {
                    return [
                        'account_id' => $line->account_id,
                        'debit' => $line->credit, // Reverse debit/credit
                        'credit' => $line->debit,
                    ];
                })
                ->toArray();

            if (!empty($lines)) {
                $this->accountingService->postEntry(
                    $referenceType,
                    $referenceId,
                    "Reversal: {$entry->description} - {$reason}",
                    $lines,
                    $userId,
                    now()
                );
            }
        }
    }

    public function getTaxLiabilityBalance(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        $vatPayableCode = config('tax.accounts.vat_payable', '2001');
        $serviceChargeReceivableCode = config('tax.accounts.service_charge_receivable', '1002');

        $vatPayable = $this->getAccountBalance($vatPayableCode, $asOf);
        $serviceChargeReceivable = $this->getAccountBalance($serviceChargeReceivableCode, $asOf);

        return [
            'vat_payable' => abs($vatPayable),
            'service_charge_receivable' => abs($serviceChargeReceivable),
            'total_tax_liability' => abs($vatPayable) + abs($serviceChargeReceivable),
        ];
    }

    protected function getAccountBalance(string $code, Carbon $asOf): float
    {
        $account = $this->getAccountByCode($code);
        if (!$account) {
            return 0;
        }

        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->sum(DB::raw('journal_lines.debit - journal_lines.credit'));
    }
}
