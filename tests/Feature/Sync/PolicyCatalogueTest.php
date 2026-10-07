<?php

namespace Tests\Feature\Sync;

use App\Sync\ConflictResult;
use App\Sync\SyncScope;
use Tests\TestCase;

class PolicyCatalogueTest extends TestCase
{
    public function test_class1_keeps_both_rows_when_keys_differ(): void
    {
        $local  = ['sync_row_key' => 'a', 'amount' => 10, 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'b', 'amount' => 20, 'sync_hlc' => '1000000000001-0-n2'];

        $merged = SyncScope::policyFor('pos_dockets')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertCount(2, $merged);
        $this->assertSame(10, $merged[0]['amount']);
        $this->assertSame(20, $merged[1]['amount']);
    }

    public function test_class1_same_key_takes_the_newer_hlc(): void
    {
        $local  = ['sync_row_key' => 'a', 'amount' => 10, 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'a', 'amount' => 20, 'sync_hlc' => '1000000000002-0-n1'];

        $merged = SyncScope::policyFor('audit_logs')->merge($local, $remote);

        $this->assertSame(20, $merged['amount']);
    }

    public function test_class2_newer_hlc_wins(): void
    {
        $local  = ['name' => 'Old', 'sync_hlc' => '1000000000001-1-n1'];
        $remote = ['name' => 'New', 'sync_hlc' => '1000000000002-1-n1'];

        $merged = SyncScope::policyFor('rooms')->merge($local, $remote);

        $this->assertSame('New', $merged['name']);
    }

    public function test_class2_node_id_breaks_hlc_ties_deterministically(): void
    {
        $local  = ['name' => 'L', 'sync_hlc' => '1000000000001-0-aaa'];
        $remote = ['name' => 'R', 'sync_hlc' => '1000000000001-0-bbb'];

        $first  = SyncScope::policyFor('rooms')->merge($local, $remote);
        $second = SyncScope::policyFor('rooms')->merge($remote, $local);

        $this->assertSame($first['name'], $second['name']);
    }

    public function test_class3_drops_the_remote_balance(): void
    {
        $local  = ['quantity' => 5, 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['quantity' => 99, 'sync_hlc' => '1000000000002-0-n1'];

        $merged = SyncScope::policyFor('inventory_stocks')->merge($local, $remote);

        $this->assertArrayNotHasKey('quantity', $merged);
    }

    public function test_class4_blocks_on_overlapping_bookings(): void
    {
        $local  = ['sync_row_key' => 'a', 'check_in' => '2026-11-01', 'check_out' => '2026-11-05', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'b', 'check_in' => '2026-11-03', 'check_out' => '2026-11-07', 'sync_hlc' => '1000000000001-0-n2'];

        $result = SyncScope::policyFor('bookings')->merge($local, $remote);

        $this->assertInstanceOf(ConflictResult::class, $result);
        $this->assertSame('overlapping_reservation', $result->reason);
        $this->assertTrue($result->isBlocked());
    }

    public function test_class4_keeps_both_adjacent_bookings_that_do_not_overlap(): void
    {
        $local  = ['sync_row_key' => 'a', 'check_in' => '2026-11-01', 'check_out' => '2026-11-05', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'b', 'check_in' => '2026-11-05', 'check_out' => '2026-11-08', 'sync_hlc' => '1000000000001-0-n2'];

        $merged = SyncScope::policyFor('bookings')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertCount(2, $merged);
    }

    public function test_class4_blocks_two_sessions_on_one_device(): void
    {
        $local  = ['sync_row_key' => 's1', 'device_id' => 'd1', 'opened_at' => '2026-11-01 08:00:00', 'closed_at' => '2026-11-01 16:00:00', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 's2', 'device_id' => 'd1', 'opened_at' => '2026-11-01 15:00:00', 'closed_at' => '2026-11-01 23:00:00', 'sync_hlc' => '1000000000001-0-n2'];

        $result = SyncScope::policyFor('pos_sessions')->merge($local, $remote);

        $this->assertInstanceOf(ConflictResult::class, $result);
        $this->assertSame('device_already_in_use', $result->reason);
    }

    public function test_class4_blocks_two_sessions_that_are_both_still_open(): void
    {
        $local  = ['sync_row_key' => 's1', 'device_id' => 'd1', 'opened_at' => '2026-11-01 08:00:00', 'closed_at' => null, 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 's2', 'device_id' => 'd1', 'opened_at' => '2026-11-01 15:00:00', 'closed_at' => null, 'sync_hlc' => '1000000000001-0-n2'];

        $result = SyncScope::policyFor('pos_sessions')->merge($local, $remote);

        $this->assertInstanceOf(ConflictResult::class, $result);
        $this->assertSame('device_already_in_use', $result->reason);
    }

    public function test_class4_keeps_two_sequential_sessions_on_one_device(): void
    {
        $local  = ['sync_row_key' => 's1', 'device_id' => 'd1', 'opened_at' => '2026-11-01 08:00:00', 'closed_at' => '2026-11-01 12:00:00', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 's2', 'device_id' => 'd1', 'opened_at' => '2026-11-01 12:00:00', 'closed_at' => '2026-11-01 16:00:00', 'sync_hlc' => '1000000000001-0-n2'];

        $merged = SyncScope::policyFor('pos_sessions')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertCount(2, $merged);
    }

    public function test_class4_keeps_sessions_on_different_devices(): void
    {
        $local  = ['sync_row_key' => 's1', 'device_id' => 'd1', 'opened_at' => '2026-11-01 08:00:00', 'closed_at' => null, 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 's2', 'device_id' => 'd2', 'opened_at' => '2026-11-01 08:00:00', 'closed_at' => null, 'sync_hlc' => '1000000000001-0-n2'];

        $merged = SyncScope::policyFor('pos_sessions')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertCount(2, $merged);
    }

    public function test_class4_blocks_a_payment_captured_on_one_node_and_refunded_on_another(): void
    {
        $local  = ['sync_row_key' => 'p1', 'amount' => 5000, 'status' => 'paid', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'p1', 'amount' => 5000, 'status' => 'refunded', 'sync_hlc' => '1000000000002-0-n2'];

        $result = SyncScope::policyFor('payments')->merge($local, $remote);

        $this->assertInstanceOf(ConflictResult::class, $result);
        $this->assertSame('captured_then_refunded', $result->reason);
        $this->assertTrue($result->isBlocked());
    }

    public function test_class4_blocks_a_payment_that_succeeded_on_one_node_and_failed_on_another(): void
    {
        $local  = ['sync_row_key' => 'p1', 'status' => 'successful', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'p1', 'status' => 'failed', 'sync_hlc' => '1000000000001-0-n2'];

        $result = SyncScope::policyFor('payments')->merge($local, $remote);

        $this->assertInstanceOf(ConflictResult::class, $result);
        $this->assertSame('captured_then_refunded', $result->reason);
    }

    public function test_class4_does_not_block_a_normal_payment_progression(): void
    {
        $local  = ['sync_row_key' => 'p1', 'status' => 'pending', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'p1', 'status' => 'paid', 'sync_hlc' => '1000000000002-0-n2'];

        $merged = SyncScope::policyFor('payments')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertSame('paid', $merged['status']);
    }

    public function test_class4_keeps_two_distinct_payment_rows(): void
    {
        $local  = ['sync_row_key' => 'p1', 'status' => 'paid', 'sync_hlc' => '1000000000001-0-n1'];
        $remote = ['sync_row_key' => 'p2', 'status' => 'paid', 'sync_hlc' => '1000000000001-0-n2'];

        $merged = SyncScope::policyFor('payments')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertCount(2, $merged);
    }

    public function test_class5_takes_the_newer_value(): void
    {
        $local  = ['key' => 'checkin_cutoff', 'value' => 36, 'sync_hlc' => '1000000000001-0-cloud'];
        $remote = ['key' => 'checkin_cutoff', 'value' => 48, 'sync_hlc' => '1000000000002-0-cloud'];

        $merged = SyncScope::policyFor('settings')->merge($local, $remote);

        $this->assertSame(48, $merged['value']);
    }

    public function test_every_synced_table_has_a_registered_policy(): void
    {
        $synced = [
            'pos_dockets', 'pos_docket_items', 'pos_docket_payments', 'pos_print_logs',
            'pos_sessions', 'inventory_movements', 'inventory_items', 'inventory_locations',
            'drink_stock_movements', 'payments', 'audit_logs', 'room_types', 'rooms',
            'staff_profiles', 'bookings',
        ];

        foreach ($synced as $table) {
            $this->assertTrue(SyncScope::has($table), "No policy registered for synced table [{$table}].");
            $this->assertNotNull(SyncScope::policyFor($table));
        }
    }

    public function test_may_emit_is_true_until_phase_4s_supplies_the_read_only_list(): void
    {
        $this->assertTrue(SyncScope::mayEmit('settings'));
        $this->assertTrue(SyncScope::mayEmit('rooms'));
    }

    public function test_a_terminal_node_may_not_emit_read_only_configuration(): void
    {
        config([
            'sync.node_type' => 'terminal',
            'sync_scope.read_only' => ['settings', 'roles'],
        ]);

        $this->assertFalse(SyncScope::mayEmit('settings'));
        $this->assertTrue(SyncScope::mayEmit('rooms'));
    }

    public function test_unregistered_entity_key_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SyncScope::policyFor('not_a_syncable_table');
    }

    public function test_keys_lists_the_whole_catalogue(): void
    {
        $keys = SyncScope::keys();

        $this->assertContains('payments', $keys);
        $this->assertContains('bookings', $keys);
        $this->assertNotContains('charges', $keys);
        $this->assertSame($keys, array_values($keys));
    }
}
