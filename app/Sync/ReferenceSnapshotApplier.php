<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Writes a reference snapshot payload into this node's database.
 *
 * The payload is the exact body served by GET /api/sync/reference:
 * ['generated_at' => string, 'tables' => [table => list<row>]]. `generated_at`
 * is not stored or validated here; it belongs to the pull, not the write.
 *
 * Rows are written through the query builder, never through Eloquent, for two
 * concrete reasons:
 *
 *  - A model save is captured by the sync change log, and every table the
 *    snapshot carries is read-only on a terminal. Applying the snapshot must
 *    not mint local changes that the terminal then pushes back at the node it
 *    pulled from.
 *  - User::setPasswordAttribute re-hashes any password assigned to it. The
 *    payload carries hashes that were computed at the source; routing the
 *    write through the model would hash them again and lock every imported
 *    account out of the terminal.
 *
 * Each table is written in its own transaction. A table that cannot be
 * written - menu_inventory_recipes depends on inventory items that arrive by
 * sync, not by snapshot - rolls back alone and is reported in `failed`, while
 * the remaining tables (every account, role and menu item) still land. One
 * transaction around the whole payload would turn a deferred dependency into a
 * failed install.
 *
 * Tables are visited in config('sync_scope.reference_snapshot') order, never
 * in the payload's order. That declaration is dependency order (SQLite
 * enforces foreign keys) and is also the security boundary: a table the
 * payload carries but the config does not name is never written. A declared
 * table the payload omits is left untouched - absence says nothing about the
 * local copy; only an explicit $prune deletes rows.
 */
class ReferenceSnapshotApplier
{
    /**
     * @param  array{generated_at?: string, tables?: array<string, list<array<string, mixed>>>}  $payload
     * @return array{applied: array<string, int>, failed: array<string, string>}
     */
    public function apply(array $payload, bool $prune = false): array
    {
        $tables = $payload['tables'] ?? [];

        if (! is_array($tables)) {
            $tables = [];
        }

        $applied = [];
        $failed = [];

        foreach (array_keys(config('sync_scope.reference_snapshot', [])) as $table) {
            if (! array_key_exists($table, $tables)) {
                continue;
            }

            if (! is_array($tables[$table])) {
                $failed[$table] = "Payload for [{$table}] is not a list of rows.";

                continue;
            }

            try {
                $applied[$table] = DB::transaction(
                    fn (): int => $this->write($table, array_values($tables[$table]), $prune),
                );
            } catch (Throwable $exception) {
                $failed[$table] = $exception->getMessage();
            }
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return ['applied' => $applied, 'failed' => $failed];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function write(string $table, array $rows, bool $prune): int
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException(
                    "Payload row for [{$table}] is not keyed by column name.",
                );
            }
        }

        $keys = TableKeys::primary($table);
        $columns = $this->columnsFor($table, $rows);

        if ($rows !== [] && $columns !== []) {
            $records = array_map(
                fn (array $row): array => $this->rekey($row, $columns),
                $rows,
            );

            $update = array_values(array_diff($columns, $keys));

            if ($update === []) {
                // Spatie's model_has_roles / role_has_permissions rows are
                // entirely primary key. Laravel's upsert falls back to a plain
                // insert for an empty update list, which would fail on the
                // second pull; updating every column writes the same values
                // and stays idempotent.
                $update = $columns;
            }

            DB::table($table)->upsert($records, $keys, $update);
        }

        if ($prune) {
            $this->prune($table, $rows, $keys);
        }

        return count($rows);
    }

    /**
     * The columns this node can write: local schema columns that the payload's
     * rows actually carry. Rows arrive from one SELECT, so their key sets
     * already agree; intersecting drops columns the local schema lacks.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function columnsFor(string $table, array $rows): array
    {
        $first = $rows[0] ?? null;

        if (! is_array($first)) {
            return [];
        }

        return array_values(array_intersect(
            Schema::getColumnListing($table),
            array_keys($first),
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function rekey(array $row, array $columns): array
    {
        $record = [];

        foreach ($columns as $column) {
            $record[$column] = $row[$column] ?? null;
        }

        return $record;
    }

    /**
     * Delete local rows whose key signature the payload does not carry. Runs
     * in the same transaction as the table's upsert, and only when the caller
     * explicitly asked for it.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     */
    private function prune(string $table, array $rows, array $keys): void
    {
        $present = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $present[$this->keySignature($row, $keys)] = true;
            }
        }

        foreach (DB::table($table)->get($keys) as $local) {
            $local = (array) $local;

            if (isset($present[$this->keySignature($local, $keys)])) {
                continue;
            }

            $query = DB::table($table);

            foreach ($keys as $key) {
                $query->where($key, $local[$key]);
            }

            $query->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function keySignature(array $row, array $keys): string
    {
        $parts = [];

        foreach ($keys as $key) {
            $parts[] = (string) ($row[$key] ?? '');
        }

        return implode("\0", $parts);
    }
}
