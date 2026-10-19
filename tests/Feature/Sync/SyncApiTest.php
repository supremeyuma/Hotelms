<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use App\Models\Sync\SyncNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private const CALLER = 'node-caller';

    private const TOKEN = 'caller-secret';

    private function authenticate(string $nodeId = self::CALLER, string $token = self::TOKEN, string $status = 'active'): void
    {
        SyncNode::create([
            'node_id' => $nodeId,
            'name' => $nodeId,
            'type' => 'terminal',
            'status' => $status,
            'credential' => hash('sha256', $token),
        ]);
    }

    private function headers(): array
    {
        return [
            'X-Sync-Node-Id' => self::CALLER,
            'Authorization' => 'Bearer '.self::TOKEN,
        ];
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/sync/changes?since=0')->assertStatus(401);
    }

    public function test_a_bad_credential_is_rejected(): void
    {
        $this->authenticate();

        $this->getJson('/api/sync/changes?since=0', [
            'X-Sync-Node-Id' => self::CALLER,
            'Authorization' => 'Bearer wrong',
        ])->assertStatus(401);
    }

    public function test_an_inactive_node_is_forbidden(): void
    {
        $this->authenticate(status: 'disabled');

        $this->getJson('/api/sync/changes?since=0', $this->headers())->assertStatus(403);
    }

    public function test_a_valid_push_applies_changes_and_advances_the_inbound_cursor(): void
    {
        config(['sync.node_id' => 'node-self']);
        $this->authenticate();

        $this->postJson('/api/sync/changes', [
            'changes' => [[
                'change_id' => 1,
                'table_name' => 'inventory_items',
                'row_id' => '01J8Z000000000000000000PA',
                'operation' => 'create',
                'node_id' => self::CALLER,
                'hlc' => '001:0000000001:0000000000:'.self::CALLER,
                'payload' => [
                    'sync_uid' => '01J8Z000000000000000000PA',
                    'name' => 'Rice',
                    'sku' => 'R-1',
                    'unit' => 'kg',
                ],
            ]],
        ], $this->headers())->assertOk()->assertJson(['accepted' => 1]);

        $this->assertTrue(InventoryItem::where('sync_uid', '01J8Z000000000000000000PA')->exists());

        $this->assertSame(1, SyncCursor::where('node_id', 'node-self')
            ->where('target_node_id', self::CALLER)
            ->where('direction', 'in')
            ->value('last_applied_change_id'));
    }

    public function test_pull_returns_changes_past_the_cursor_and_honours_limit(): void
    {
        config(['sync.node_id' => 'node-self']);
        $this->authenticate();

        $ids = [];
        foreach ([1, 2, 3] as $sequence) {
            $change = SyncChange::create([
                'table_name' => 'inventory_items',
                'row_id' => '01J8Z0000000000000000000'.$sequence,
                'operation' => 'create',
                'node_id' => 'node-self',
                'hlc' => '001:000000000'.$sequence.':0000000000:node-self',
                'sequence' => $sequence,
                'payload' => ['sync_uid' => '01J8Z0000000000000000000'.$sequence],
            ]);
            $ids[] = $change->id;
        }

        $first = $this->getJson('/api/sync/changes?since=0&limit=2', $this->headers())
            ->assertOk()
            ->assertJson(['has_more' => true])
            ->json();

        $this->assertCount(2, $first['changes']);
        $this->assertSame($ids[1], $first['cursor']);

        $second = $this->getJson('/api/sync/changes?since='.$first['cursor'].'&limit=2', $this->headers())
            ->assertOk()
            ->assertJson(['has_more' => false])
            ->json();

        $this->assertCount(1, $second['changes']);
        $this->assertSame($ids[2], $second['cursor']);
    }

    public function test_pull_does_not_serve_another_nodes_changes(): void
    {
        config(['sync.node_id' => 'node-self']);
        $this->authenticate();

        SyncChange::create([
            'table_name' => 'inventory_items',
            'row_id' => 'other',
            'operation' => 'create',
            'node_id' => 'node-other',
            'hlc' => '001:0000000001:0000000000:node-other',
            'sequence' => 1,
            'payload' => ['sync_uid' => 'other'],
        ]);

        $this->getJson('/api/sync/changes?since=0', $this->headers())
            ->assertOk()
            ->assertJson(['changes' => [], 'has_more' => false]);
    }

    public function test_an_unregistered_table_is_rejected_with_a_reason_and_rolls_the_batch_back(): void
    {
        config(['sync.node_id' => 'node-self']);
        $this->authenticate();

        $validUid = '01J8Z000000000000000000RA';

        $response = $this->postJson('/api/sync/changes', [
            'changes' => [
                [
                    'change_id' => 1,
                    'table_name' => 'inventory_items',
                    'row_id' => $validUid,
                    'operation' => 'create',
                    'node_id' => self::CALLER,
                    'hlc' => '001:0000000001:0000000000:'.self::CALLER,
                    'payload' => [
                        'sync_uid' => $validUid,
                        'name' => 'Rollback Rice',
                        'sku' => 'R-ROLLBACK',
                        'unit' => 'kg',
                    ],
                ],
                [
                    'change_id' => 2,
                    'table_name' => 'definitely_not_a_registered_table',
                    'row_id' => '01J8Z000000000000000000RB',
                    'operation' => 'create',
                    'node_id' => self::CALLER,
                    'hlc' => '001:0000000002:0000000000:'.self::CALLER,
                    'payload' => ['anything' => true],
                ],
            ],
        ], $this->headers());

        $response->assertStatus(422)
            ->assertJsonPath('table', 'definitely_not_a_registered_table');

        $this->assertStringContainsString(
            'definitely_not_a_registered_table',
            (string) $response->json('reason'),
            'The rejection reason must name the offending table.',
        );

        $this->assertSame(0, InventoryItem::count(), 'The whole batch must roll back.');

        $this->assertFalse(
            SyncCursor::where('node_id', 'node-self')
                ->where('target_node_id', self::CALLER)
                ->where('direction', 'in')
                ->exists(),
            'A rejected batch must not create the inbound cursor row.'
        );

        // The receiver is not poisoned: a later legitimate push still applies.
        $laterUid = '01J8Z000000000000000000RC';

        $this->postJson('/api/sync/changes', [
            'changes' => [[
                'change_id' => 3,
                'table_name' => 'inventory_items',
                'row_id' => $laterUid,
                'operation' => 'create',
                'node_id' => self::CALLER,
                'hlc' => '001:0000000003:0000000000:'.self::CALLER,
                'payload' => [
                    'sync_uid' => $laterUid,
                    'name' => 'Later Rice',
                    'sku' => 'R-LATER',
                    'unit' => 'kg',
                ],
            ]],
        ], $this->headers())->assertOk()->assertJson(['accepted' => 1]);

        $this->assertTrue(InventoryItem::where('sync_uid', $laterUid)->exists());
    }
}
