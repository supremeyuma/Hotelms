<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `sync:status` must answer "how much local work has this peer not yet
 * acknowledged?", computed against that peer's outbound cursor.
 *
 * Pending is deliberately per peer and never a `sent` column on
 * `sync_changes`: one change can be delivered to the LAN server and still be
 * pending for the cloud, so a single boolean would be wrong on the first day
 * of a mesh. A missing cursor row means nothing has ever been acknowledged,
 * so the baseline is 0 — the safe reading.
 */
class SyncStatusPendingTest extends TestCase
{
    use RefreshDatabase;

    private function configureTerminal(array $peers): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
            'sync.peers' => $peers,
        ]);
    }

    private function localChange(int $sequence, string $nodeId = 'node-a'): SyncChange
    {
        return SyncChange::create([
            'table_name' => 'inventory_items',
            'row_id' => '01J8Z00000000000000000'.$sequence,
            'operation' => 'create',
            'node_id' => $nodeId,
            'hlc' => '001:000000000'.$sequence.':0000000000:'.$nodeId,
            'sequence' => $sequence,
            'payload' => ['sync_uid' => '01J8Z00000000000000000'.$sequence, 'name' => 'Row '.$sequence],
        ]);
    }

    private function setOutboundCursor(string $peerId, int $changeId): void
    {
        SyncCursor::create([
            'node_id' => 'node-a',
            'target_node_id' => $peerId,
            'direction' => 'out',
            'last_applied_change_id' => $changeId,
            'last_synced_at' => now(),
        ]);
    }

    private function setInboundCursor(string $peerId, int $changeId): void
    {
        SyncCursor::create([
            'node_id' => 'node-a',
            'target_node_id' => $peerId,
            'direction' => 'in',
            'last_applied_change_id' => $changeId,
            'last_synced_at' => now(),
        ]);
    }

    private function statusOutput(): string
    {
        $exit = Artisan::call('sync:status');
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);

        return $output;
    }

    public function test_every_local_change_is_pending_when_the_peer_cursor_is_missing(): void
    {
        $this->configureTerminal(['node-b' => ['base_url' => 'http://peer']]);

        $this->localChange(1);
        $this->localChange(2);
        $this->localChange(3);

        $output = $this->statusOutput();

        $this->assertMatchesRegularExpression('/\|\s*pending\s*\|\s*3\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*node-b\s*\|\s*3\s*\|/', $output);
    }

    public function test_only_changes_past_the_outbound_cursor_are_pending(): void
    {
        $this->configureTerminal(['node-b' => ['base_url' => 'http://peer']]);

        $this->localChange(1);
        $second = $this->localChange(2);
        $this->localChange(3);

        $this->setOutboundCursor('node-b', (int) $second->id);

        $output = $this->statusOutput();

        $this->assertMatchesRegularExpression('/\|\s*pending\s*\|\s*1\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*node-b\s*\|\s*1\s*\|/', $output);
    }

    public function test_the_inbound_cursor_never_hides_pending_outbound_work(): void
    {
        $this->configureTerminal(['node-b' => ['base_url' => 'http://peer']]);

        $this->localChange(1);
        $this->localChange(2);

        // The inbound cursor indexes the peer's log, not ours, so a high
        // inbound position must not be read as "the peer has our changes".
        $this->setInboundCursor('node-b', 99);

        $output = $this->statusOutput();

        $this->assertMatchesRegularExpression('/\|\s*pending\s*\|\s*2\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*node-b\s*\|\s*2\s*\|/', $output);
    }

    public function test_changes_written_by_another_node_are_not_this_nodes_pending_work(): void
    {
        $this->configureTerminal(['node-b' => ['base_url' => 'http://peer']]);

        $this->localChange(1);
        $this->localChange(2);
        $this->localChange(3, 'node-z');

        $output = $this->statusOutput();

        $this->assertMatchesRegularExpression('/\|\s*pending\s*\|\s*2\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*node-b\s*\|\s*2\s*\|/', $output);
    }

    public function test_each_peer_reports_its_own_pending_count(): void
    {
        $this->configureTerminal([
            'node-b' => ['base_url' => 'http://lan'],
            'node-cloud' => ['base_url' => 'http://cloud'],
        ]);

        $first = $this->localChange(1);
        $this->localChange(2);

        $this->setOutboundCursor('node-b', (int) $first->id);

        $output = $this->statusOutput();

        // node-b acknowledged one change; node-cloud has never acknowledged
        // anything. The summary is the work at least one peer still lacks.
        $this->assertMatchesRegularExpression('/\|\s*pending\s*\|\s*2\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*node-b\s*\|\s*1\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*node-cloud\s*\|\s*2\s*\|/', $output);
    }

    public function test_pending_is_zero_when_no_peers_are_configured(): void
    {
        $this->configureTerminal([]);

        $this->localChange(1);
        $this->localChange(2);

        $output = $this->statusOutput();

        $this->assertMatchesRegularExpression('/\|\s*pending\s*\|\s*0\s*\|/', $output);
    }
}
