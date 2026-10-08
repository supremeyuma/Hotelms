<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncChange;
use App\Sync\Hlc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_hlc_round_trips(): void
    {
        $hlc = Hlc::now('node1');

        $this->assertSame($hlc->nodeId, Hlc::fromString($hlc->toString())->nodeId);
        $this->assertSame($hlc->toString(), Hlc::fromString($hlc->toString())->toString());
    }

    public function test_hlc_is_strictly_increasing_within_a_node(): void
    {
        $a = Hlc::now('node1');
        $b = $a->increment();

        // Same instant, higher counter: the property that stops two writes
        // inside one millisecond from sharing a clock value.
        $this->assertSame($a->timestamp, $b->timestamp);
        $this->assertSame($a->counter + 1, $b->counter);
        $this->assertSame('node1', $b->nodeId);
        $this->assertGreaterThan($a->toString(), $b->toString());
    }

    public function test_hlc_max_breaks_ties_by_node_id(): void
    {
        // Equal timestamp, equal counter. Only the node id separates them, so
        // whichever way round the two are passed the winner must be stable -
        // both nodes have to reach the same answer from the same pair.
        $a = Hlc::fromString('1000000000001-0-aaa');
        $b = Hlc::fromString('1000000000001-0-bbb');

        $this->assertSame('bbb', Hlc::max($a, $b)->nodeId);
        $this->assertSame('bbb', Hlc::max($b, $a)->nodeId);
    }

    public function test_hlc_max_prefers_the_larger_timestamp(): void
    {
        $older = Hlc::fromString('1000000000001-9-zzz');
        $newer = Hlc::fromString('1000000000002-0-aaa');

        $this->assertSame('aaa', Hlc::max($older, $newer)->nodeId);
        $this->assertSame('aaa', Hlc::max($newer, $older)->nodeId);
    }

    public function test_syncable_trait_on_sync_change(): void
    {
        $change = SyncChange::create([
            'table_name' => 'test',
            'row_id'     => 1,
            'operation'  => 'create',
            'node_id'    => 'n1',
            'hlc'        => Hlc::now('n1')->toString(),
        ]);

        $this->assertEquals('sync_changes', $change->getSyncTable());
        $this->assertNotEmpty($change->getSyncId());
    }
}
