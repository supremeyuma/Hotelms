<?php

namespace Tests\Feature\Sync;

use App\Models\Payment;
use App\Models\Sync\SyncConflict;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

class ConflictPersistenceTest extends TestCase
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

    public function test_a_blocked_conflict_is_recorded_and_neither_side_is_written(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        $payment = Payment::create(['amount' => 100.0, 'method' => 'card']);
        $uid = $payment->sync_uid;
        $this->harness->exchange('a', 'b');

        $this->harness->use('a');
        Payment::where('sync_uid', $uid)->first()->update(['status' => 'paid']);

        $this->harness->use('b');
        Payment::where('sync_uid', $uid)->first()->update(['status' => 'refunded']);

        // Each node pushes its own edit. Both directions must be stopped: the
        // paid-and-refunded pair is a money disagreement, not a merge.
        $this->harness->exchange('b', 'a');
        $this->harness->exchange('a', 'b');

        $this->harness->use('a');
        $this->assertSame('paid', Payment::where('sync_uid', $uid)->first()->status);
        $this->assertSame(1, SyncConflict::where('table_name', 'payments')->where('status', 'open')->count());
        $this->assertSame('captured_then_refunded', SyncConflict::first()->reason);

        $this->harness->use('b');
        $this->assertSame('refunded', Payment::where('sync_uid', $uid)->first()->status);
        $this->assertSame(1, SyncConflict::where('table_name', 'payments')->where('status', 'open')->count());
        $this->assertSame('captured_then_refunded', SyncConflict::first()->reason);
    }

    public function test_replaying_a_blocked_change_does_not_pile_up_conflicts(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        $payment = Payment::create(['amount' => 100.0, 'method' => 'card']);
        $uid = $payment->sync_uid;
        $this->harness->exchange('a', 'b');

        $this->harness->use('a');
        Payment::where('sync_uid', $uid)->first()->update(['status' => 'paid']);

        $this->harness->use('b');
        Payment::where('sync_uid', $uid)->first()->update(['status' => 'refunded']);

        $this->harness->exchange('b', 'a');

        $this->harness->use('a');
        $this->assertSame(1, SyncConflict::count());

        $this->harness->exchange('b', 'a');
        $this->harness->exchange('b', 'a');

        $this->harness->use('a');
        $this->assertSame(1, SyncConflict::count(), 'replaying the change must not duplicate the conflict');
    }
}