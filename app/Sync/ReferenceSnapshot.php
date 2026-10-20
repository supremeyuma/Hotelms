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
 *
 * The whole read runs in one transaction. Reading a table at a time without
 * one lets a write land between two reads, so a child row can be captured
 * referencing a parent the same payload does not carry — the applier then
 * fails that table on a foreign key and the operator sees a warning that
 * describes a race rather than a real problem. One snapshot should describe
 * one moment.
 */
class ReferenceSnapshot
{
    /**
     * @return array{generated_at: string, tables: array<string, list<array<string, mixed>>>}
     */
    public function build(): array
    {
        return DB::transaction(function (): array {
            $tables = [];

            foreach ($this->tables() as $table) {
                $tables[$table] = $this->rowsFor($table);
            }

            return [
                'generated_at' => now()->toIso8601String(),
                'tables' => $tables,
            ];
        });
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
