<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use App\Sync\SyncLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeLogCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
        ]);
    }

    public function test_writing_a_syncable_row_stamps_an_hlc(): void
    {
        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertNotNull($item->fresh()->sync_hlc);
        $this->assertSame('node-a', $item->fresh()->sync_origin_node_id);
    }

    public function test_writing_a_syncable_row_appends_one_change_row(): void
    {
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(1, SyncChange::count());

        $change = SyncChange::first();

        $this->assertSame('create', $change->operation);
        $this->assertSame('inventory_items', $change->table_name);
        $this->assertSame('node-a', $change->node_id);
        $this->assertIsArray($change->payload);
        $this->assertSame(1, $change->sequence);
    }

    public function test_updating_appends_a_second_change_with_an_incremented_sequence(): void
    {
        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $item->update(['name' => 'Jollof Rice']);

        $this->assertSame(2, SyncChange::count());
        $this->assertSame([1, 2], SyncChange::orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_capture_is_suppressed_while_applying_a_remote_change(): void
    {
        SyncLogger::applyingRemote(function (): void {
            InventoryItem::create(['name' => 'Remote', 'sku' => 'R-9', 'unit' => 'kg']);
        });

        $this->assertSame(0, SyncChange::count());
        $this->assertFalse(SyncLogger::isApplying());
    }

    public function test_capture_is_disabled_when_sync_is_off(): void
    {
        config(['sync.enabled' => false]);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(0, SyncChange::count());
        $this->assertNull($item->fresh()->sync_hlc);
    }

    public function test_stamping_the_hlc_does_not_append_a_second_change(): void
    {
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(1, SyncChange::count());
    }

    /**
     * Regression: capture must address the row by sync_uid. The integer id is
     * local to one engine and diverges across nodes, so logging it would make
     * every remote apply miss the row it was written for.
     */
    public function test_row_id_is_the_sync_uid_not_the_integer_primary_key(): void
    {
        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertNotNull($item->sync_uid);
        $this->assertSame(26, strlen($item->sync_uid));

        $change = SyncChange::first();

        $this->assertSame($item->sync_uid, $change->row_id);
        $this->assertNotSame((string) $item->id, $change->row_id);
    }

    /**
     * Regression: a row written while sync is off still has to be addressable
     * the moment sync is turned on, otherwise its first captured change points
     * at a ULID that exists nowhere.
     */
    public function test_sync_uid_is_minted_on_create_even_when_sync_is_off(): void
    {
        config(['sync.enabled' => false]);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertNotNull($item->fresh()->sync_uid);
        $this->assertSame(26, strlen($item->fresh()->sync_uid));
    }

    /**
     * Regression: with no node identity the HLC would carry an empty node id
     * and the change would be logged under node_id "", which interleaves with
     * every other unconfigured node's sequence and corrupts the cursor.
     */
    public function test_capture_is_skipped_when_the_node_has_no_identity(): void
    {
        config(['sync.node_id' => '']);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(0, SyncChange::count());
        $this->assertNull($item->fresh()->sync_hlc);
    }

    /**
     * Regression: SyncChange uses the Syncable trait but has no sync_hlc,
     * sync_origin_node_id or sync_uid column. Stamping it would put those
     * columns in the UPDATE statement and fail on every captured change, so
     * the trait has to scope itself to catalogue tables.
     */
    public function test_change_rows_are_not_stamped_with_domain_sync_fields(): void
    {
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $change = SyncChange::first();

        $this->assertNotNull($change);
        $this->assertSame('inventory_items', $change->table_name);
        $this->assertNull($change->sync_uid ?? null);
    }
}
