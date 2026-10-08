<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use Illuminate\Support\Facades\DB;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

class TwoNodeConvergenceTest extends TestCase
{
    private TwoNodeSyncHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sync.enabled' => true]);

        $this->harness = TwoNodeSyncHarness::make();
        $this->harness->migrateBoth();
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();

        parent::tearDown();
    }

    public function test_disconnected_writes_converge_after_bidirectional_sync(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);
        $first = InventoryItem::first();
        $first->update(['name' => 'Jollof Rice']);

        $this->harness->use('b');
        config(['sync.node_id' => 'node-b']);
        InventoryItem::create(['name' => 'Beans', 'sku' => 'B-1', 'unit' => 'kg']);

        $this->harness->exchange('a', 'b');
        $this->harness->exchange('b', 'a');

        $this->assertEqualsCanonicalizing(
            $this->harness->snapshot('a'),
            $this->harness->snapshot('b'),
        );
    }

    public function test_the_higher_hlc_wins_in_either_exchange_order(): void
    {
        // Both nodes held the same row, then both edited it offline. Node a's
        // edit carries the later clock, so it must survive on BOTH nodes
        // whichever node pushes its log first. Forcing the HLCs makes the
        // assertion about the algorithm instead of about the wall clock: the
        // winner is decided by the clock, never by who happened to send first.
        $this->seedDivergence('R-1', '9999999999999-0-node-a', '1000000000000-0-node-b');

        $this->harness->exchange('a', 'b');
        $this->harness->exchange('b', 'a');

        $this->assertWinner('R-1', 'Edited on A');

        // A second, independent divergence resolved in the reverse order must
        // land on the exact same winner.
        $this->seedDivergence('R-2', '9999999999999-0-node-a', '1000000000000-0-node-b');

        $this->harness->exchange('b', 'a');
        $this->harness->exchange('a', 'b');

        $this->assertWinner('R-2', 'Edited on A');
    }

    public function test_a_newer_local_edit_survives_a_stale_remote_change(): void
    {
        // This node edited the row after the change was captured, so its clock
        // is higher than the one coming over the wire. The stale remote must
        // lose to the newer local write - the reason the applier merges at all.
        $this->seedDivergence('R-3', '1000000000000-0-node-a', '9999999999999-0-node-b');

        $this->harness->exchange('b', 'a');

        $this->assertWinner('R-3', 'Edited on B');
    }

    public function test_sync_is_idempotent_when_run_twice(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->harness->exchange('a', 'b');
        $afterFirst = $this->harness->snapshot('b');

        $this->harness->exchange('a', 'b');
        $this->harness->exchange('a', 'b');

        $this->assertSame($afterFirst, $this->harness->snapshot('b'));
    }

    /**
     * Give both nodes the same row, then a different offline edit each, with
     * the given HLCs forced onto the row and its captured change so the merge
     * compares exactly the intended pair of clocks.
     */
    private function seedDivergence(string $sku, string $hlcA, string $hlcB): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        InventoryItem::create(['name' => 'Rice', 'sku' => $sku, 'unit' => 'kg']);
        $this->harness->exchange('a', 'b');

        $this->harness->use('a');
        InventoryItem::where('sku', $sku)->first()->update(['name' => 'Edited on A']);
        $this->forceClock('a', $sku, $hlcA);

        $this->harness->use('b');
        InventoryItem::where('sku', $sku)->first()->update(['name' => 'Edited on B']);
        $this->forceClock('b', $sku, $hlcB);
    }

    private function forceClock(string $node, string $sku, string $hlc): void
    {
        $this->harness->use($node);

        $uid = InventoryItem::where('sku', $sku)->value('sync_uid');

        // The merge compares the row's HLC against the payload's HLC, so both
        // sides of that pair must carry the forced clock.
        DB::table('inventory_items')->where('sku', $sku)->update(['sync_hlc' => $hlc]);

        $change = SyncChange::query()
            ->where('row_id', $uid)
            ->orderByDesc('sequence')
            ->first();

        $payload = $change->payload;
        $payload['sync_hlc'] = $hlc;
        $change->update(['payload' => $payload]);
    }

    private function assertWinner(string $sku, string $expected): void
    {
        $this->harness->use('a');
        $this->assertSame($expected, InventoryItem::where('sku', $sku)->first()->name);

        $this->harness->use('b');
        $this->assertSame($expected, InventoryItem::where('sku', $sku)->first()->name);
    }
}