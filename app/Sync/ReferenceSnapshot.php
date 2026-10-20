<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * The initial reference payload a freshly installed terminal pulls.
 *
 * Rows are column-named, not positional arrays, because the payload crosses
 * an engine boundary (Postgres LAN server to SQLite terminal) where column
 * order is not an agreement. Rows are ordered by every primary-key column so
 * the pulling applier can write them deterministically, parents before
 * children as declared in config/sync_scope.php.
 */
class ReferenceSnapshot
{
    /**
     * @return array{generated_at: string, tables: array<string, list<array<string, mixed>>>}
     */
    public function build(): array
    {
        $tables = [];

        foreach ($this->tables() as $table) {
            $tables[$table] = $this->rowsFor($table);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables,
        ];
    }

    /** @return list<string> */
    private function tables(): array
    {
        return array_keys(config('sync_scope.reference_snapshot', []));
    }

    /** @return list<array<string, mixed>> */
    private function rowsFor(string $table): array
    {
        $query = DB::table($table);

        foreach (TableKeys::primary($table) as $column) {
            $query->orderBy($column);
        }

        return array_map(static fn ($row) => (array) $row, $query->get()->all());
    }
}
