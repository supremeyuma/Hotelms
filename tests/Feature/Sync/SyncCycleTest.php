<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use App\Sync\SyncCycleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncCycleTest extends TestCase
{
    use RefreshDatabase;

    private function localChange(int $sequence): SyncChange
    {
        return SyncChange::create([
            'table_name' => 'inventory_items',
            'row_id'     => '01J8Z00000000000000000' . $sequence,
            'operation'  => 'create',
            'node_id'    => 'node-a',
            'hlc'        => '001:000000000' . $sequence . ':0000000000:node-a',
            'sequence'   => $sequence,
            'payload'    => ['sync_uid' => '01J8Z00000000000000000' . $sequence, 'name' => 'Row ' . $sequence],
        ]);
    }

    public function test_an_unreachable_peer_does_not_stop_the_others(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'lan-server.test')) {
                throw new ConnectionException('refused');
            }

            return Http::response(['accepted' => 0, 'changes' => [], 'cursor' => 0]);
        });

        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
            'sync.peers'   => ['http://lan-server.test', 'http://cloud.test'],
        ]);

        $result = app(SyncCycleRunner::class)->run();

        $this->assertSame(2, $result['peers']);
        $this->assertSame(['http://lan-server.test'], $result['failed']);
    }

    public function test_a_pull_applies_changes_and_advances_the_inbound_cursor(): void
    {
        Http::fake([
            '*' => Http::response([
                'accepted' => 0,
                'changes'  => [[
                    'table_name' => 'inventory_items',
                    'row_id'     => '01J8Z0000000000000000000A',
                    'operation'  => 'create',
                    'node_id'    => 'node-b',
                    'hlc'        => '001:0000000001:0000000000:node-b',
                    'payload'    => [
                        'sync_uid' => '01J8Z0000000000000000000A',
                        'name'     => 'Rice',
                        'sku'      => 'R-1',
                        'unit'     => 'kg',
                    ],
                ]],
                'cursor'   => 1,
                'has_more' => false,
            ]),
        ]);

        // Peers are configured by node id plus base_url. The id is what the
        // cursor table records, so a peer that changes address keeps its
        // delivery history.
        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
            'sync.peers'   => ['node-b' => ['base_url' => 'http://peer']],
        ]);

        $result = app(SyncCycleRunner::class)->run();

        $this->assertSame(1, $result['pulled']);

        $this->assertSame(1, SyncCursor::where('node_id', 'node-a')
            ->where('target_node_id', 'node-b')
            ->where('direction', 'in')
            ->value('last_applied_change_id'));

        $this->assertDatabaseHas('inventory_items', ['sync_uid' => '01J8Z0000000000000000000A']);
    }

    public function test_push_advances_the_outbound_cursor_so_a_second_cycle_sends_nothing(): void
    {
        config([
            'sync.enabled'    => true,
            'sync.node_id'    => 'node-a',
            'sync.batch_size' => 500,
            'sync.peers'      => ['node-b' => ['base_url' => 'http://peer']],
        ]);

        $first = $this->localChange(1);
        $second = $this->localChange(2);

        Http::fake(function (Request $request) {
            return $request->method() === 'POST'
                ? Http::response(['accepted' => 2], 200)
                : Http::response(['changes' => [], 'cursor' => 0], 200);
        });

        $result = app(SyncCycleRunner::class)->run();

        $this->assertSame(2, $result['pushed']);

        $this->assertSame((int) $second->id, SyncCursor::where('node_id', 'node-a')
            ->where('target_node_id', 'node-b')
            ->where('direction', 'out')
            ->value('last_applied_change_id'));

        // Nothing left past the outbound cursor, so the next cycle pushes 0.
        Http::fake([
            '*' => Http::response(['accepted' => 0, 'changes' => [], 'cursor' => 0]),
        ]);

        $again = app(SyncCycleRunner::class)->run();

        $this->assertSame(0, $again['pushed']);

        // The cursor is unchanged: an empty push must not advance it.
        $this->assertSame((int) $second->id, SyncCursor::where('node_id', 'node-a')
            ->where('target_node_id', 'node-b')
            ->where('direction', 'out')
            ->value('last_applied_change_id'));

        $this->assertGreaterThan(0, (int) $first->id);
    }

    public function test_push_pages_through_more_than_one_batch(): void
    {
        config([
            'sync.enabled'    => true,
            'sync.node_id'    => 'node-a',
            'sync.batch_size' => 1,
            'sync.peers'      => ['node-b' => ['base_url' => 'http://peer']],
        ]);

        $this->localChange(1);
        $last = $this->localChange(2);

        Http::fake(function (Request $request) {
            return $request->method() === 'POST'
                ? Http::response(['accepted' => 1], 200)
                : Http::response(['changes' => [], 'cursor' => 0], 200);
        });

        $result = app(SyncCycleRunner::class)->run();

        $this->assertSame(2, $result['pushed']);

        $this->assertSame((int) $last->id, SyncCursor::where('node_id', 'node-a')
            ->where('target_node_id', 'node-b')
            ->where('direction', 'out')
            ->value('last_applied_change_id'));
    }
}
