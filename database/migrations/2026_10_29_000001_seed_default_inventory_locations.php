<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every installation needs the default inventory locations.
 *
 * Two core workflows look one of these up with `firstOrFail()` and answer 404
 * when it is missing: housekeeping deducts consumables from the main store when
 * a room is finished, and the kitchen consumes stock when an order starts
 * preparation. The rows were only ever described by a migration-shaped file
 * that sat in `database/seeders/` and never ran, so a fresh install had none —
 * a clean room and a started order were both impossible, and the failure looked
 * like a broken URL rather than missing reference data.
 *
 * Idempotent by type, so it is safe on an install that already has some of the
 * locations (and safe to re-run on `migrate`).
 */
return new class extends Migration
{
    /** @var list<array{name: string, type: string}> */
    private array $locations = [
        ['name' => 'Main Store', 'type' => 'main_store'],
        ['name' => 'Kitchen Store', 'type' => 'kitchen'],
        ['name' => 'Bar Store', 'type' => 'bar'],
        ['name' => 'Laundry Store', 'type' => 'laundry'],
    ];

    public function up(): void
    {
        foreach ($this->locations as $location) {
            $exists = DB::table('inventory_locations')
                ->where('type', $location['type'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('inventory_locations')->insert([
                'name' => $location['name'],
                'type' => $location['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reference data other records point at; removing it would orphan
        // stock and movement rows. Deliberately left in place.
    }
};
