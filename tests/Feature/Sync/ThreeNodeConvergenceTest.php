<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Sync\SyncCycleRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\ThreeNodeSyncHarness;
use Tests\TestCase;

/**
 * Three-process convergence over the real HTTP transport.
 *
 * The network is the one thing stubbed: `Http::fake` intercepts the outbound
 * call and drives the peer's actual ChangeLogController against that peer's
 * own database, so the transport's batching, headers and JSON handling, the
 * controller's transaction, the applier and the cursors all really run. Only
 * the socket is imagined.
 *
 * Peering is a full mesh. The applier does not re-log a remote change, so
 * nothing relays: a change reaches exactly the nodes directly peered with its
 * origin, and the demo must therefore connect every pair.
 */
class ThreeNodeConvergenceTest extends TestCase
{
    private ThreeNodeSyncHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled'    => true,
            'sync.batch_size' => 500,
            'sync.credential' => 'shared-secret',
        ]);

        $this->harness = ThreeNodeSyncHarness::make();
        $this->harness->migrateAll();
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();

        parent::tearDown();
    }

    public function test_three_nodes_converge_over_the_real_transport(): void
    {
        $this->serveTheMesh();

        $this->seedItem('a', 'A-1');
        $this->seedItem('b', 'B-1');
        $this->seedItem('c', 'C-1');

        $this->runEveryNode();

        $a = $this->harness->snapshot('a');
        $b = $this->harness->snapshot('b');
        $c = $this->harness->snapshot('c');

        $this->assertCount(3, $a);
        $this->assertEqualsCanonicalizing($a, $b);
        $this->assertEqualsCanonicalizing($a, $c);
    }

    public function test_a_dead_peer_does_not_block_the_rest(): void
    {
        // The LAN server is down; the terminal still has the cloud. The dead
        // peer is reported, and reconciliation continues against the live one.
        $this->serveTheMesh(deadHost: 'node-c.test');

        $this->seedItem('a', 'A-1');

        $this->harness->use('a');
        $result = app(SyncCycleRunner::class)->run();

        $this->assertSame(2, $result['peers']);
        $this->assertContains('node-c', $result['failed']);
        $this->assertNotContains('node-b', $result['failed']);
    }

    public function test_a_failed_page_rolls_back_and_does_not_advance_the_cursor(): void
    {
        // A page arrives holding one valid change and one that names a table
        // nobody registered. Applying the second throws, and the whole page -
        // the valid row AND the inbound cursor - must roll back together, so
        // the page is retried rather than silently skipped half-applied.
        $this->serveTheMesh(poison: true);

        $this->harness->use('a');

        try {
            app(SyncCycleRunner::class)->run();
            $this->fail('Expected the poisoned page to abort the cycle.');
        } catch (\Throwable) {
            // Expected: an unregistered entity is a bug, not a transport fault,
            // so it is not swallowed by the cycle runner.
        }

        $this->harness->use('a');

        $this->assertSame(0, InventoryItem::count(), 'The valid change must have rolled back with the page.');
        $this->assertSame(
            0,
            DB::table('sync_cursors')
                ->where('node_id', 'node-a')
                ->where('direction', 'in')
                ->count(),
            'The inbound cursor must not advance past a page that failed to apply.',
        );
    }

    private function seedItem(string $letter, string $sku): void
    {
        $this->harness->use($letter);

        InventoryItem::create(['name' => "Item {$sku}", 'sku' => $sku, 'unit' => 'kg']);
    }

    private function runEveryNode(int $rounds = 2): void
    {
        for ($i = 0; $i < $rounds; $i++) {
            foreach (['a', 'b', 'c'] as $letter) {
                $this->harness->use($letter);
                app(SyncCycleRunner::class)->run();
            }
        }
    }

    /**
     * Stand in for the network: route each outbound call to the peer's own
     * database and controller.
     */
    private function serveTheMesh(?string $deadHost = null, bool $poison = false): void
    {
        $hosts = $this->harness->hostMap();

        Http::fake(function (Request $request) use ($hosts, $deadHost, $poison) {
            $host = parse_url($request->url(), PHP_URL_HOST);

            if ($deadHost !== null && $host === $deadHost) {
                throw new ConnectionException("Connection to {$host} timed out.");
            }

            $target = $hosts[$host] ?? null;

            if ($target === null) {
                throw new ConnectionException("Unknown sync host [{$host}].");
            }

            if ($poison && $request->method() === 'GET') {
                return Http::response($this->poisonPage(), 200);
            }

            $sender = $request->header('X-Sync-Node-Id')[0] ?? null;

            return $this->harness->onNode($target, function () use ($request, $sender) {
                $controller = app(\App\Http\Controllers\Sync\ChangeLogController::class);

                if ($request->method() === 'POST') {
                    $http = HttpRequest::create('/api/sync/changes', 'POST', $request->data());
                    $http->attributes->set('sync_node', $this->peer($sender));

                    return Http::response($controller->store($http)->getData(true), 200);
                }

                $http = HttpRequest::create('/api/sync/changes', 'GET', $this->queryOf($request));

                return Http::response($controller->index($http)->getData(true), 200);
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function queryOf(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return array_merge($query, $request->data());
    }

    /**
     * A stand-in for the authenticated peer the middleware would have attached.
     */
    private function peer(?string $nodeId): object
    {
        return new class($nodeId)
        {
            public function __construct(public ?string $node_id)
            {
            }
        };
    }

    /**
     * A page carrying one applicable change and one that must fail.
     *
     * @return array<string, mixed>
     */
    private function poisonPage(): array
    {
        $uid = (string) Str::ulid();

        return [
            'changes' => [
                [
                    'change_id' => 1,
                    'table_name' => 'inventory_items',
                    'row_id'     => $uid,
                    'operation'  => 'create',
                    'node_id'    => 'node-b',
                    'hlc'        => '1000000000000-0-node-b',
                    'payload'    => [
                        'sync_uid'            => $uid,
                        'name'                => 'Poison',
                        'sku'                 => 'P-1',
                        'unit'                => 'kg',
                        'sync_hlc'            => '1000000000000-0-node-b',
                        'sync_origin_node_id' => 'node-b',
                        'created_at'          => now()->toIso8601String(),
                        'updated_at'          => now()->toIso8601String(),
                    ],
                ],
                [
                    'change_id' => 2,
                    'table_name' => 'definitely_not_a_registered_table',
                    'row_id'     => (string) Str::ulid(),
                    'operation'  => 'create',
                    'node_id'    => 'node-b',
                    'hlc'        => '1000000000000-1-node-b',
                    'payload'    => ['anything' => true],
                ],
            ],
            'cursor'   => 2,
            'has_more' => false,
        ];
    }
}
