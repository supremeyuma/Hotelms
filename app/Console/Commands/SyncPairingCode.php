<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mint the single-use, time-limited code a node exchanges for its credential
 * at POST /api/sync/register.
 *
 * No admin UI exists for this yet (Phase 8 keeps it operator-only on
 * purpose), and without a minter the exchange endpoint cannot be exercised
 * end to end.
 */
class SyncPairingCode extends Command
{
    protected $signature = 'sync:pairing-code
        {--property= : Property id this code is bound to}
        {--ttl=15 : Minutes until the code expires}';

    protected $description = 'Mint a single-use pairing code a node exchanges for its credential';

    public function handle(): int
    {
        $propertyOption = trim((string) ($this->option('property') ?? ''));
        $ttl = trim((string) $this->option('ttl'));

        if (! ctype_digit($propertyOption)) {
            $this->error('Option --property must be a property id.');

            return self::FAILURE;
        }

        $property = Property::find((int) $propertyOption);

        if ($property === null) {
            $this->error("Property {$propertyOption} does not exist.");

            return self::FAILURE;
        }

        if (! ctype_digit($ttl) || (int) $ttl < 1) {
            $this->error('Option --ttl must be a whole number of minutes (at least 1).');

            return self::FAILURE;
        }

        // Uppercase alphanumeric only: this code is read aloud and typed by
        // a human on a machine that may have no keyboard layout to paste
        // into. 12 characters of [A-Z0-9] keeps guessing infeasible next to
        // the endpoint throttle, single use and the expiry below.
        $code = Str::upper(Str::random(12));
        $expiresAt = now()->addMinutes((int) $ttl);

        DB::table('sync_pairing_codes')->insert([
            'property_id' => $property->id,
            'code' => $code,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->warn('Single use: the first node to redeem this code receives the credential.');
        $this->line('Pairing code: '.$code);
        $this->line('Property:     '.$property->id.' ('.$property->name.')');
        $this->line('Expires at:   '.$expiresAt->toDateTimeString());

        return self::SUCCESS;
    }
}
