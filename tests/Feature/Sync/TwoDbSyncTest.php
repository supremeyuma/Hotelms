<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

class TwoDbSyncTest extends TestCase
{
    private TwoNodeSyncHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = TwoNodeSyncHarness::make();
        $this->harness->migrateBoth();
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();

        parent::tearDown();
    }

    public function test_the_two_nodes_are_separate_databases(): void
    {
        $this->harness->use('a');
        InventoryItem::create(['name' => 'Only on A', 'sku' => 'A-1', 'unit' => 'kg']);

        $this->harness->use('b');
        $this->assertSame(0, InventoryItem::count());
    }

    public function test_a_row_written_on_one_node_reaches_the_other_after_sync(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $this->harness->use('a');
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->harness->exchange('a', 'b');

        $this->harness->use('b');
        $this->assertSame(1, InventoryItem::count());
        $this->assertSame('Rice', InventoryItem::first()->name);
    }

    public function test_applying_a_remote_change_does_not_re_log_it_on_the_receiver(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $this->harness->use('a');
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-9', 'unit' => 'kg']);
        $this->assertSame(1, SyncChange::count());

        $this->harness->exchange('a', 'b');

        // The write reached b, but b must not claim it. A second change row
        // here would be handed straight back to a on the next exchange, and
        // the two nodes would ping-pong the same row forever.
        $this->harness->use('b');
        $this->assertSame(1, InventoryItem::count());
        $this->assertSame(0, SyncChange::count());
    }
}
