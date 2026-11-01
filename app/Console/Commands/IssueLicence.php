<?php

namespace App\Console\Commands;

use App\Models\Licence;
use App\Models\LicencePlan;
use App\Services\Licensing\LicenceCheckoutService;
use App\Services\Licensing\LicenceService;
use Illuminate\Console\Command;

class IssueLicence extends Command
{
    protected $signature = 'licence:issue
        {--property= : Property name the licence covers}
        {--rooms= : Number of rooms, which sets the price tier}
        {--currency=NGN : NGN or USD}
        {--months= : Term in months (defaults to config)}
        {--name= : Customer name}
        {--email= : Customer email}
        {--payment= : Payment reference from the gateway or bank}
        {--notes= : Internal note}';

    protected $description = 'Issue a licence key for a property (run after payment is received)';

    public function handle(LicenceService $licences, LicenceCheckoutService $checkout): int
    {
        $property = trim((string) $this->option('property'));

        if ($property === '') {
            $this->error('--property is required: the licence is per property.');

            return self::FAILURE;
        }

        $months = $this->option('months');
        $months = $months === null ? null : (int) $months;

        if ($months !== null && $months < 1) {
            $this->error('--months must be a positive whole number.');

            return self::FAILURE;
        }

        $currency = strtoupper((string) $this->option('currency'));
        $rooms = $this->option('rooms');
        $rooms = $rooms === null ? null : (int) $rooms;

        if ($rooms !== null && $rooms < 1) {
            $this->error('--rooms must be a positive whole number.');

            return self::FAILURE;
        }

        // With a room count the sale is priced exactly like an online one, so the
        // licence carries the same tier and amount for reporting.
        $sale = [];
        $term = $months ?? (int) config('licensing.term_months', 12);

        if ($rooms !== null) {
            try {
                $quote = $checkout->quote($rooms, $currency, $term);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $sale = [
                'plan' => $quote['plan'],
                'rooms' => $rooms,
                'currency' => $quote['currency'],
                'monthly_amount' => $quote['monthly'],
                'amount' => $quote['amount'],
            ];

            $term = $quote['months'];
        }

        $issued = $licences->issue(
            propertyName: $property,
            customerName: $this->option('name'),
            customerEmail: $this->option('email'),
            termMonths: $term,
            paymentReference: $this->option('payment'),
            notes: $this->option('notes'),
            sale: $sale,
        );

        /** @var Licence $licence */
        $licence = $issued['licence'];

        $this->info('Licence issued.');
        $this->line('Key:       ' . $issued['key']);
        $this->line('Property:  ' . $licence->property_name);

        if (isset($sale['plan'])) {
            /** @var LicencePlan $plan */
            $plan = $sale['plan'];
            $this->line('Tier:      ' . $plan->name . ' (' . $plan->bandLabel() . ')');
            $this->line('Price:     ' . $sale['currency'] . ' ' . number_format((float) $sale['amount'], 2));
        }

        $this->line('Term:      ' . $licence->term_months . ' months from activation');
        $this->line('Licence #: ' . $licence->id);
        $this->warn('The key is shown once; only its hash is stored. Send it to the customer now.');

        return self::SUCCESS;
    }
}
