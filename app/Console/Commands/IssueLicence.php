<?php

namespace App\Console\Commands;

use App\Models\Licence;
use App\Services\Licensing\LicenceService;
use Illuminate\Console\Command;

class IssueLicence extends Command
{
    protected $signature = 'licence:issue
        {--property= : Property name the licence covers}
        {--name= : Customer name}
        {--email= : Customer email}
        {--months= : Term in months (defaults to config)}
        {--payment= : Payment reference from the gateway}
        {--notes= : Internal note}';

    protected $description = 'Issue a licence key for a property (run after payment is received)';

    public function handle(LicenceService $licences): int
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

        $issued = $licences->issue(
            propertyName: $property,
            customerName: $this->option('name'),
            customerEmail: $this->option('email'),
            termMonths: $months,
            paymentReference: $this->option('payment'),
            notes: $this->option('notes'),
        );

        /** @var Licence $licence */
        $licence = $issued['licence'];

        $this->info('Licence issued.');
        $this->line('Key:       ' . $issued['key']);
        $this->line('Property:  ' . $licence->property_name);
        $this->line('Term:      ' . $licence->term_months . ' months from activation');
        $this->line('Licence #: ' . $licence->id);
        $this->warn('The key is shown once; only its hash is stored. Send it to the customer now.');

        return self::SUCCESS;
    }
}
