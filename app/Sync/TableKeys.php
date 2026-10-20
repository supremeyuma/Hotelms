<?php

namespace App\Sync;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TableKeys
{
    /** @return list<string> */
    public static function primary(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            // SQLite reports `primary` from `origin = 'pk'` as 0/1, the other
            // grammars as a boolean: this is a truthy check on purpose.
            if (! ($index['primary'] ?? false)) {
                continue;
            }

            return array_values($index['columns']);
        }

        throw new RuntimeException(
            "Table [{$table}] declares no primary key; a snapshot row cannot be upserted by id."
        );
    }
}
