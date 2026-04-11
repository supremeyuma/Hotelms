<?php

return [
    /**
     * VAT (Value Added Tax) rate as a decimal
     * Default: 7.5% (0.075)
     */
    'vat_rate' => env('TAX_VAT_RATE', 0.0),

    /**
     * Service Charge rate as a decimal
     * Default: 10% (0.10)
     */
    'service_charge_rate' => env('TAX_SERVICE_CHARGE_RATE', 0.0),

    /**
     * Tax accounts configuration
     * Codes used for posting tax entries in the accounting system
     */
    'accounts' => [
        'vat_payable' => '2001',           // Liability account
        'sales_tax_expense' => '5001',     // Expense account
        'service_charge_receivable' => '1002',  // Asset account
        'service_charge_revenue' => '4001',     // Revenue account
    ],

    /**
     * Enable/disable tax calculations
     * Useful for testing or disabling taxes temporarily
     */
    'enabled' => env('TAX_ENABLED', false),
];
