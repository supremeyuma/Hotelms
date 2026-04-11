<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Accounting\TaxService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_pricing_uses_saved_settings_rates_when_enabled(): void
    {
        config()->set('tax.enabled', false);
        config()->set('tax.vat_rate', 0.0);
        config()->set('tax.service_charge_rate', 0.0);

        Setting::set('tax_enabled', true);
        Setting::set('tax_rate', 0.075);
        Setting::set('service_charge_enabled', true);
        Setting::set('service_charge_rate', 0.1);

        $service = new PricingService($this->createMock(TaxService::class));

        $pricing = $service->calculatePricing(10000);

        $this->assertEquals(10000.0, $pricing['base_amount']);
        $this->assertEquals(750.0, $pricing['vat']);
        $this->assertEquals(1000.0, $pricing['service_charge']);
        $this->assertEquals(11750.0, $pricing['total']);
    }
}
