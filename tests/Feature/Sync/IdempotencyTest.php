<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use App\Sync\SyncApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_the_entire_log_twice_leaves_identical_state(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);
        $item->update(['name' => 'Jollof Rice']);

        $changes = SyncChange::orderBy('sequence')->get();

        $this->assertGreaterThan(1, $changes->count());

        $before = InventoryItem::orderBy('id')->get()->toJson();

        $applier = app(SyncApplier::class);

        foreach ([1, 2] as $ignored) {
            foreach ($changes as $change) {
                $applier->apply($change);
            }
        }

        $this->assertSame($before, InventoryItem::orderBy('id')->get()->toJson());
        $this->assertCount(1, InventoryItem::all());
    }

    public function test_applying_a_change_does_not_append_a_new_change(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $count = SyncChange::count();

        app(SyncApplier::class)->apply(SyncChange::first());

        $this->assertSame($count, SyncChange::count());
    }

    /**
     * Regression: InventoryItem does not list `quantity` as fillable, so
     * fill() would silently discard it and a remote stock change would never
     * land. Convergence depends on writing the whole snapshot, not on the
     * origin happening to match this node's mass-assignment rules.
     */
    public function test_applying_a_change_overwrites_columns_the_model_does_not_fill(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $item->quantity = 5;
        $item->save();

        $change = SyncChange::orderByDesc('sequence')->first();

        config(['sync.enabled' => false]);
        $item->quantity = 99;
        $item->save();
        config(['sync.enabled' => true]);

        app(SyncApplier::class)->apply($change);

        $this->assertSame(5, (int) $item->fresh()->quantity);
    }

    /**
     * Regression: a row this node has never seen must be inserted, and the
     * origin's integer primary key in the payload must not be adopted - it
     * belongs to another engine and would collide with a local row.
     */
    public function test_applying_a_change_for_an_unknown_row_creates_it_without_the_origin_key(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $uid = (string) Str::ulid();

        SyncChange::create([
            'table_name' => 'inventory_items',
            'row_id'     => $uid,
            'operation'  => 'create',
            'node_id'    => 'node-b',
            'hlc'        => '1000000000001-0-node-b',
            'sequence'   => 1,
            'payload'    => [
                'id'       => 999999,
                'sync_uid' => $uid,
                'name'     => 'Remote',
                'sku'      => 'R-X',
                'unit'     => 'kg',
                'quantity' => 7,
            ],
        ]);

        app(SyncApplier::class)->apply(SyncChange::first());

        $item = InventoryItem::where('sync_uid', $uid)->first();

        $this->assertNotNull($item);
        $this->assertSame('Remote', $item->name);
        $this->assertSame(7, (int) $item->quantity);
        $this->assertNotSame(999999, (int) $item->id);
    }

    /**
     * Regression: stampSyncMetadata ran on every save, including saves made
     * by the applier, so applying a remote change replaced the origin's HLC
     * with a fresh local one. The row would then claim this node wrote it and
     * the next last-write-wins comparison would use the wrong clock.
     */
    public function test_applying_a_change_keeps_the_origins_hlc(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $change = SyncChange::orderBy('sequence')->first();
        $payload = $change->payload;
        $payload['sync_hlc'] = '9999999999999-9-node-b';
        $payload['sync_origin_node_id'] = 'node-b';
        $change->payload = $payload;
        $change->save();

        app(SyncApplier::class)->apply($change->fresh());

        $fresh = $item->fresh();

        $this->assertSame('9999999999999-9-node-b', $fresh->sync_hlc);
        $this->assertSame('node-b', $fresh->sync_origin_node_id);
    }

    public function test_apply_rejects_an_unregistered_entity_key(): void
    {
        $change = new SyncChange([
            'table_name' => 'users',
            'row_id'     => 'x',
            'operation'  => 'create',
            'node_id'    => 'node-b',
            'hlc'        => '1000000000001-0-node-b',
            'sequence'   => 1,
            'payload'    => [],
        ]);

        $this->expectException(\RuntimeException::class);

        app(SyncApplier::class)->apply($change);
    }
}
