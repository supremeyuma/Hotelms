<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AccountingService;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\TaxService;
use App\Models\Account;
use Carbon\Carbon;

class TestAccountingSystem extends Command
{
    protected $signature = 'accounting:test';
    protected $description = 'Test accounting system functionality';

    public function __construct(
        protected AccountingService $accountingService,
        protected PeriodService $periodService,
        protected TaxService $taxService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Testing Accounting System...');

        try {
            // Test 1: Create test period
            $this->info('1. Checking accounting period...');
            $existingPeriod = $this->periodService->getCurrentPeriod();
            if ($existingPeriod) {
                $this->info("   ✓ Using existing period: {$existingPeriod->start_date} to {$existingPeriod->end_date}");
                $period = $existingPeriod;
            } else {
                $period = $this->periodService->createPeriod(
                    Carbon::now()->startOfMonth(),
                    Carbon::now()->endOfMonth()
                );
                $this->info("   ✓ Period created: {$period->start_date} to {$period->end_date}");
            }

            // Test 2: Create test accounts
            $this->info('2. Creating test accounts...');
            $accounts = [
                ['code' => '1001', 'name' => 'Cash', 'type' => 'asset'],
                ['code' => '4001', 'name' => 'Room Revenue', 'type' => 'revenue'],
            ];

            foreach ($accounts as $accountData) {
                $account = Account::firstOrCreate(
                    ['code' => $accountData['code']],
                    $accountData
                );
                $this->info("   ✓ Account: {$account->name} ({$account->code})");
            }

            // Test 3: Test tax calculations
            $this->info('3. Testing tax calculations...');
            $amount = 10000;
            $vat = $this->taxService->calculateVAT($amount);
            $serviceCharge = $this->taxService->calculateServiceCharge($amount);
            $this->info("   ✓ Amount: ₦{$amount}");
            $this->info("   ✓ VAT (7.5%): ₦{$vat}");
            $this->info("   ✓ Service Charge (10%): ₦{$serviceCharge}");

            // Test 4: Initialize tax accounts
            $this->info('4. Initializing tax accounts...');
            $this->taxService->createDefaultTaxAccounts();
            $this->info('   ✓ Tax accounts initialized');

            // Test 5: Test journal entry
            $this->info('5. Creating test journal entry...');
            $cashAccount = Account::where('code', '1001')->first();
            $revenueAccount = Account::where('code', '4001')->first();

            if ($cashAccount && $revenueAccount) {
                $entry = $this->accountingService->postEntry(
                    'test',
                    1,
                    'Test journal entry',
                    [
                        ['account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => $amount],
                    ],
                    1,
                    now()
                );
                $this->info("   ✓ Journal entry created: {$entry->description}");

                // Test 6: Test tax posting
                $this->info('6. Testing tax posting...');
                $this->taxService->postAllTaxes(
                    $amount,
                    'test',
                    1,
                    'Test tax posting',
                    1,
                    now()
                );
                $this->info('   ✓ Tax entries posted');
            }

            // Test 7: Test period status
            $this->info('7. Testing period status...');
            $isOpen = $this->periodService->isOpen(now());
            $this->info("   ✓ Current period is " . ($isOpen ? 'OPEN' : 'CLOSED'));

            $this->info('✅ All accounting system tests passed!');
            
        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
