<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

/**
 * Spec §13: the golden sync fixture is a committed, engine-independent JSON
 * scenario. Every engine (SQLite today, Postgres via Phase 1R) replays the
 * exact same operations and must converge to the exact same business state,
 * so the engines cannot drift. The file is the contract; this test only
 * reads the file.
 */
class GoldenFixtureTest extends TestCase
{
    public function test_the_golden_scenario_replays_and_converges_on_every_engine(): void
    {
        $scenario = json_decode(
            (string) file_get_contents(__DIR__ . '/../../Fixtures/sync/golden-scenario.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $harness = TwoNodeSyncHarness::make();

        try {
            $harness->migrateBoth();

            foreach ($scenario['operations'] as $operation) {
                $node = $operation['node'];

                $harness->use($node === 'node-a' ? 'a' : 'b');
                config(['sync.enabled' => true, 'sync.node_id' => $node]);

                $row = $operation['row'];

                if ($operation['action'] === 'create') {
                    InventoryItem::create($this->withoutId($row));
                } else {
                    // The update arrives on the node that created the row, but
                    // that is a property of the scenario, not of the integer
                    // id - which is node-local. The sku is the stable business
                    // key for the whole fixture.
                    InventoryItem::where('sku', $row['sku'])->firstOrFail()->update(
                        $this->withoutId($row),
                    );
                }
            }

            $harness->exchange('a', 'b');
            $harness->exchange('b', 'a');

            // The two nodes must end byte-identical on every business column,
            // sync_uid and timestamps. The snapshot strips id and the sync
            // metadata that legitimately differs per node.
            $this->assertSame($harness->snapshot('a'), $harness->snapshot('b'));

            foreach (['a', 'b'] as $letter) {
                $harness->use($letter);

                foreach ($scenario['assert']['expected_rows'] as $row) {
                    $this->assertDatabaseHas('inventory_items', [
                        'name' => $row['name'],
                        'sku'  => $row['sku'],
                        'unit' => $row['unit'],
                    ]);
                }
            }
        } finally {
            $harness->cleanup();
        }
    }

    /**
     * The fixture keeps a node-local id for human readability; the model's
     * own auto-increment assigns the real value and sync_uid identifies the
     * row across engines.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutId(array $row): array
    {
        unset($row['id']);

        return $row;
    }
}