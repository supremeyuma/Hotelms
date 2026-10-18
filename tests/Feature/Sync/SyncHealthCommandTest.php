<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncConflict;
use App\Models\Sync\SyncCursor;
use App\Models\Sync\SyncNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `sync:health` is the operator's read of the sync subsystem: one table of
 * counts and at most two warnings. It is deliberately output-only (no public
 * endpoint - see the Phase 8 plan interfaces note) and deliberately not
 * scheduled, because this deployment has no monitoring to alert on it.
 */
class SyncHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    private function healthOutput(): string
    {
        $exit = Artisan::call('sync:health');
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);

        return $output;
    }

    private function changeRow(int $sequence, ?\DateTimeInterface $createdAt = null): SyncChange
    {
        return SyncChange::create([
            'table_name' => 'inventory_items',
            'row_id' => '01J8Z00000000000000000'.$sequence,
            'operation' => 'create',
            'node_id' => 'node-a',
            'hlc' => '001:000000000'.$sequence.':0000000000:node-a',
            'sequence' => $sequence,
            'payload' => ['sync_uid' => '01J8Z00000000000000000'.$sequence],
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_a_clean_node_reports_every_metric_at_zero_without_warnings(): void
    {
        $output = $this->healthOutput();

        $this->assertMatchesRegularExpression('/\|\s*active nodes\s*\|\s*0\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*inactive nodes\s*\|\s*0\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*changes recorded\s*\|\s*0\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*changes older than 24h\s*\|\s*0\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*unresolved conflicts\s*\|\s*0\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*cursors\s*\|\s*0\s*\|/', $output);

        $this->assertStringNotContainsString('require a human', $output);
        $this->assertStringNotContainsString('are not active', $output);
    }

    public function test_seeded_state_renders_the_right_counts_and_both_warnings(): void
    {
        $this->changeRow(1);
        $this->changeRow(2, now()->subHours(25));
        $this->changeRow(3);

        SyncConflict::create([
            'table_name' => 'bookings',
            'row_id' => '01J8Z00000000000000000C1',
            'status' => 'open',
            'reason' => 'overlapping_reservation',
        ]);
        SyncConflict::create([
            'table_name' => 'bookings',
            'row_id' => '01J8Z00000000000000000C2',
            'status' => 'open',
            'reason' => 'captured_then_refunded',
        ]);
        // Resolved work must not count towards the warning.
        SyncConflict::create([
            'table_name' => 'bookings',
            'row_id' => '01J8Z00000000000000000C3',
            'status' => 'resolved',
            'reason' => 'overlapping_reservation',
            'resolved_at' => now(),
        ]);

        SyncNode::create(['node_id' => 'node-a', 'status' => 'active', 'last_seen_at' => now()]);
        SyncNode::create(['node_id' => 'node-b', 'status' => 'active', 'last_seen_at' => now()]);
        SyncNode::create(['node_id' => 'node-c', 'status' => 'offline', 'last_seen_at' => now()->subDay()]);

        SyncCursor::create([
            'node_id' => 'node-a',
            'target_node_id' => 'node-b',
            'direction' => 'out',
            'last_applied_change_id' => 3,
            'last_synced_at' => now(),
        ]);

        $output = $this->healthOutput();

        $this->assertMatchesRegularExpression('/\|\s*active nodes\s*\|\s*2\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*inactive nodes\s*\|\s*1\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*changes recorded\s*\|\s*3\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*changes older than 24h\s*\|\s*1\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*unresolved conflicts\s*\|\s*2\s*\|/', $output);
        $this->assertMatchesRegularExpression('/\|\s*cursors\s*\|\s*1\s*\|/', $output);

        $this->assertStringContainsString('2 unresolved conflict(s) require a human', $output);
        $this->assertStringContainsString('1 node(s) are not active', $output);
    }
}
