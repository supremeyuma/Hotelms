<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins GET /api/sync/reference, the initial reference pull a freshly
 * installed terminal makes. It sits behind sync.node like every other
 * node-to-node endpoint, and it serves exactly the tables declared in
 * config/sync_scope.php's reference_snapshot block — in that order, keyed by
 * column name (the payload crosses an engine boundary where column order is
 * not an agreement), and ordered by primary key so the applier in the next
 * task can upsert deterministically.
 */
class ReferenceSnapshotApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = 'reference-snapshot-'.bin2hex(random_bytes(8));
    }

    private function node(array $overrides = []): SyncNode
    {
        return SyncNode::create(array_merge([
            'node_id' => (string) Str::ulid(),
            'name' => 'Bar Terminal 1',
            'type' => 'terminal',
            'status' => 'active',
            'credential' => hash('sha256', $this->token),
        ], $overrides));
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'X-Sync-Node-Id' => SyncNode::sole()->node_id,
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_a_request_without_a_credential_is_rejected(): void
    {
        // No headers at all: the middleware never reaches the snapshot.
        $this->getJson('/api/sync/reference')->assertStatus(401);

        // Headers presented against a node whose credential was never stored:
        // there is nothing to compare the hash against.
        $this->node(['credential' => null]);

        $this->getJson('/api/sync/reference', $this->headers())->assertStatus(401);
    }

    public function test_an_inactive_node_is_rejected(): void
    {
        $this->node(['status' => 'revoked']);

        $this->getJson('/api/sync/reference', $this->headers())->assertStatus(403);
    }

    public function test_an_authenticated_node_gets_every_declared_table_in_declared_order(): void
    {
        $this->node();

        $response = $this->getJson('/api/sync/reference', $this->headers());
        $response->assertOk();

        $json = $response->json();

        $this->assertArrayHasKey('generated_at', $json);
        $this->assertNotEmpty($json['generated_at']);

        // Declared order, no more, no fewer: config/sync_scope.php's
        // reference_snapshot block is the single source of truth.
        $this->assertSame(
            array_keys(config('sync_scope.reference_snapshot', [])),
            array_keys($json['tables'])
        );
    }

    /**
     * The applier in the next task writes rows by column name, never by
     * position, so every row must expose the same named key set.
     */
    public function test_rows_are_keyed_by_column_name_and_uniform_within_a_table(): void
    {
        User::factory()->create();
        $this->node();

        $tables = $this->getJson('/api/sync/reference', $this->headers())
            ->assertOk()
            ->json('tables');

        $rows = $tables['users'];
        $this->assertNotEmpty($rows);

        $keys = array_keys($rows[0]);
        $this->assertContains('id', $keys);
        $this->assertContains('password', $keys);

        foreach ($rows as $row) {
            $this->assertSame($keys, array_keys($row));
        }
    }

    public function test_rows_are_ordered_by_primary_key(): void
    {
        User::factory()->createMany([[], [], []]);
        $this->node();

        $rows = $this->getJson('/api/sync/reference', $this->headers())
            ->assertOk()
            ->json('tables.users');

        $ids = array_column($rows, 'id');
        $sorted = $ids;
        sort($sorted);

        $this->assertSame($sorted, $ids);
    }
}
