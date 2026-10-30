<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every installation needs a minimal chart of accounts.
 *
 * Payments post through `PaymentLedger`, which looks accounts up **by name**
 * ("Cash", "Bank", "Accounts Receivable") and throws when one is missing;
 * service charges post to "Food Revenue", "Beverage Revenue" and "Laundry
 * Revenue". Without these rows every payment logged "Payment accounting failed"
 * and the profit & loss / balance sheet reports could only ever show zero.
 *
 * Codes avoid the tax-service codes (1002 service-charge receivable, 2001 VAT
 * payable, 4001 service-charge revenue). Idempotent by code, so an install that
 * already defined its own chart keeps it.
 */
return new class extends Migration
{
    /** @var list<array{code: string, name: string, type: string}> */
    private array $accounts = [
        ['code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
        ['code' => '1010', 'name' => 'Bank', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
        ['code' => '4000', 'name' => 'Room Revenue', 'type' => 'revenue'],
        ['code' => '4010', 'name' => 'Food Revenue', 'type' => 'revenue'],
        ['code' => '4020', 'name' => 'Beverage Revenue', 'type' => 'revenue'],
        ['code' => '4030', 'name' => 'Laundry Revenue', 'type' => 'revenue'],
        ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense'],
    ];

    public function up(): void
    {
        foreach ($this->accounts as $account) {
            $exists = DB::table('accounts')->where('code', $account['code'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('accounts')->insert([
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reference data journal lines point at; removing it would orphan them.
    }
};
