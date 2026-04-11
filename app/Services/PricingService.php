<?php

namespace App\Services;

use App\Services\Accounting\TaxService;

/**
 * PricingService: Reusable pricing helper for tax-aware calculations
 *
 * Single source of truth for calculating base amount, VAT, service charge, and totals.
 * Used by EventService, BookingService, and payment processors.
 */
class PricingService
{
    protected TaxService $taxService;

    public function __construct(TaxService $taxService)
    {
        $this->taxService = $taxService;
    }

    /**
     * Calculate complete pricing breakdown for a given base amount
     *
     * @param float $baseAmount The amount before taxes
     * @param float|null $vatRate Optional VAT rate, defaults to config
     * @param float|null $serviceChargeRate Optional service charge rate, defaults to config
     * @return array {
     *     'base_amount' => float,
     *     'vat' => float,
     *     'service_charge' => float,
     *     'total' => float
     * }
     */
    public function calculatePricing(float $baseAmount, ?float $vatRate = null, ?float $serviceChargeRate = null): array
    {
        if (!config('tax.enabled', true)) {
            return [
                'base_amount' => round($baseAmount, 2),
                'vat' => 0,
                'service_charge' => 0,
                'total' => round($baseAmount, 2),
            ];
        }

        // Use provided rates or fall back to config
        $vatRate = $vatRate ?? config('tax.vat_rate', 0.0);
        $serviceChargeRate = $serviceChargeRate ?? config('tax.service_charge_rate', 0.0);

        $vat = round($baseAmount * $vatRate, 2);
        $serviceCharge = round($baseAmount * $serviceChargeRate, 2);
        $total = round($baseAmount + $vat + $serviceCharge, 2);

        return [
            'base_amount' => round($baseAmount, 2),
            'vat' => $vat,
            'service_charge' => $serviceCharge,
            'total' => $total,
        ];
    }

    /**
     * Get pricing breakdown from a previously calculated total
     * Useful for determining component parts when total is known
     *
     * @param float $total The total amount including taxes
     * @return array Same structure as calculatePricing()
     */
    public function getPricingFromTotal(float $total): array
    {
        if (!config('tax.enabled', true)) {
            return [
                'base_amount' => round($total, 2),
                'vat' => 0,
                'service_charge' => 0,
                'total' => round($total, 2),
            ];
        }

        $vatRate = config('tax.vat_rate', 0.0);
        $serviceChargeRate = config('tax.service_charge_rate', 0.0);
        $totalRate = 1 + $vatRate + $serviceChargeRate;

        $baseAmount = round($total / $totalRate, 2);
        $vat = round($baseAmount * $vatRate, 2);
        $serviceCharge = round($baseAmount * $serviceChargeRate, 2);

        return [
            'base_amount' => $baseAmount,
            'vat' => $vat,
            'service_charge' => $serviceCharge,
            'total' => round($baseAmount + $vat + $serviceCharge, 2),
        ];
    }

    /**
     * Get VAT rate from config
     */
    public function getVatRate(): float
    {
        return config('tax.vat_rate', 0.0);
    }

    /**
     * Get service charge rate from config
     */
    public function getServiceChargeRate(): float
    {
        return config('tax.service_charge_rate', 0.0);
    }

    /**
     * Format pricing for display
     */
    public function formatPricing(array $pricing): array
    {
        return [
            'base_amount' => number_format($pricing['base_amount'], 2),
            'vat' => number_format($pricing['vat'], 2),
            'service_charge' => number_format($pricing['service_charge'], 2),
            'total' => number_format($pricing['total'], 2),
        ];
    }
}
