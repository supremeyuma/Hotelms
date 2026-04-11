<?php

namespace Tests\Unit;

use App\Services\Accounting\TaxService;
use App\Services\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_pricing_returns_base_amount_when_extra_charges_are_disabled(): void
    {
        config()->set('tax.enabled', false);
        config()->set('tax.vat_rate', 0.0);
        config()->set('tax.service_charge_rate', 0.0);

        $service = new PricingService($this->createMock(TaxService::class));

        $pricing = $service->calculatePricing(12500);

        $this->assertEquals(12500.0, $pricing['base_amount']);
        $this->assertEquals(0.0, $pricing['vat']);
        $this->assertEquals(0.0, $pricing['service_charge']);
        $this->assertEquals(12500.0, $pricing['total']);
    }

    public function test_pricing_from_total_does_not_back_into_hidden_charges(): void
    {
        config()->set('tax.enabled', false);
        config()->set('tax.vat_rate', 0.0);
        config()->set('tax.service_charge_rate', 0.0);

        $service = new PricingService($this->createMock(TaxService::class));

        $pricing = $service->getPricingFromTotal(8700);

        $this->assertEquals(8700.0, $pricing['base_amount']);
        $this->assertEquals(0.0, $pricing['vat']);
        $this->assertEquals(0.0, $pricing['service_charge']);
        $this->assertEquals(8700.0, $pricing['total']);
    }
}
