<?php

namespace Tests\Feature\Sync;

use App\Sync\ReferenceSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The snapshot describes one moment, not several.
 *
 * Reading a table at a time outside a transaction lets a write land between
 * two reads, so a child row can arrive in the payload referencing a parent
 * the same payload never carried. The applier then fails that table on a
 * foreign key and the operator is shown a warning describing a race rather
 * than a real problem.
 */
class ReferenceSnapshotConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The build opens a transaction of its own.
     *
     * `RefreshDatabase` already holds an outer transaction, and the build runs
     * on a sibling connection, so the two are not nested — the level is the
     * absolute count on the snapshot's connection, which is 1 without the
     * wrapping transaction and 2 with it. This is what makes the assertion
     * discriminating rather than decorative.
     */
    public function test_the_payload_is_read_inside_a_transaction_of_its_own(): void
    {
        $levels = [];

        $snapshot = app(ReferenceSnapshot::class);

        DB::listen(function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $snapshot->build();

        $this->assertNotEmpty($levels, 'the snapshot should issue queries');
        $this->assertGreaterThan(
            1,
            min($levels),
            'the snapshot queries must run inside the wrapping transaction, not bare on the outer one',
        );
    }

    public function test_every_child_row_in_the_payload_has_its_parent_in_the_payload(): void
    {
        $payload = app(ReferenceSnapshot::class)->build();
        $tables = $payload['tables'];

        // Derived from the real schema, not a hand-listed pair: any foreign key
        // whose parent is also snapshotted must be satisfied inside the payload.
        // A torn read is what breaks this — the child row lands while its
        // parent does not.
        foreach (array_keys($tables) as $table) {
            foreach (DB::getSchemaBuilder()->getForeignKeys($table) as $foreign) {
                $parent = $foreign['foreign_table'];

                if (! array_key_exists($parent, $tables)) {
                    continue;
                }

                $parentValues = array_map(
                    static fn (array $row) => array_values(array_map(
                        static fn (string $c) => $row[$c] ?? null,
                        $foreign['foreign_columns'],
                    )),
                    $tables[$parent],
                );

                foreach ($tables[$table] as $row) {
                    $childValues = array_values(array_map(
                        static fn (string $c) => $row[$c] ?? null,
                        $foreign['columns'],
                    ));

                    if (in_array(null, $childValues, true)) {
                        continue;
                    }

                    $this->assertContains(
                        $childValues,
                        $parentValues,
                        "{$table} carried a row whose parent is missing from {$parent} in the same payload",
                    );
                }
            }
        }
    }
}
