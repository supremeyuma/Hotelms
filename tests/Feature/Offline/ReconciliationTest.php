<?php

namespace Tests\Feature\Offline;

use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosDocketPayment;
use App\Models\PosSession;
use App\Models\Sync\SyncChange;
use App\Models\User;
use App\Services\ClubPosService;
use App\Services\DrinkStockService;
use App\Sync\SyncApplier;
use App\Sync\SyncCycleRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\ThreeNodeSyncHarness;
use Tests\TestCase;

/**
 * Task 4 of the offline terminal work: prove the handoff reconciles.
 *
 * Two genuinely separate databases (the terminal and the LAN server) exchange
 * changes over the real HTTP transport, the real ChangeLogController and the
 * real cursors; only the socket is imagined. The harness is the three-node one
 * from Phase 4R. Only nodes a and b are used - a is the terminal that sold
 * offline, b is the server it catches up to - so each node is peered with only
 * the other, and the third database is left inert rather than invited to
 * participate.
 *
 * What is proven: an offline sale lands exactly once and is not double-counted
 * on replay; reference data a terminal never authors is present on both nodes;
 * a balance reconciles to its movement ledger rather than to whichever side
 * wrote last; an oversell is shown rather than hidden; and a page that fails to
 * apply does not advance the cursor past it.
 */
class ReconciliationTest extends TestCase
{
    private ThreeNodeSyncHarness $harness;

    private User $staff;

    private PosDevice $device;

    private PosSession $session;

    private MenuItem $item;

    /**
     * Read at call time by the single stubbed transport closure. A per-run fake
     * would leave the first stub in place - Http::fake() appends rather than
     * replaces - so the flag is what turns the server on and off.
     */
    private bool $peersReachable = true;

    /**
     * Serve a poisoned page on the next pull until cleared, for the crash test.
     */
    private bool $poison = true;

    private string $uidOne = '';

    private string $uidTwo = '';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled' => true,
            'sync.batch_size' => 500,
            'sync.credential' => 'shared-secret',
            'sync.node_type' => 'terminal',
        ]);

        $this->harness = ThreeNodeSyncHarness::make();
        $this->harness->migrateAll();
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();

        parent::tearDown();
    }

    public function test_an_offline_sale_reaches_the_server_exactly_once(): void
    {
        $this->seedSharedReferenceData();
        $this->serveTheMesh();

        $this->peersReachable = false;
        [$docketId, $response] = $this->sellOnTerminal();
        $response->assertOk();
        $this->assertTrue($response->json('queued_for_sync'), 'A sale made with no reachable peer is queued.');

        $this->peersReachable = true;
        $this->runSync('a');

        $this->actAsNode('a');
        $terminalDocket = PosDocket::findOrFail($docketId);

        $this->actAsNode('b');
        $this->assertSame(1, PosDocket::count(), 'The sale must land on the server exactly once.');
        $this->assertSame(1, PosDocketItem::count(), 'The line item must land exactly once.');
        $this->assertSame(1, PosDocketPayment::count(), 'The payment must land exactly once.');
        $this->assertSame(1, DrinkStockMovement::where('type', 'sale')->count(), 'Exactly one stock movement.');

        $serverDocket = PosDocket::firstOrFail();
        $this->assertSame($terminalDocket->sync_uid, $serverDocket->sync_uid, 'Identity is the origin ULID, not a local id.');
        $this->assertSame('closed', $serverDocket->status);
        $this->assertSame((string) $terminalDocket->total, (string) $serverDocket->total);
    }

    public function test_repeating_the_cycle_does_not_duplicate_the_sale(): void
    {
        $this->seedSharedReferenceData();
        $this->serveTheMesh();

        $this->peersReachable = false;
        $this->sellOnTerminal();
        $this->peersReachable = true;

        for ($i = 0; $i < 5; $i++) {
            $this->runSync('a');
            $this->runSync('b');
        }

        $this->actAsNode('b');
        $this->assertSame(1, PosDocket::count(), 'Running the cycle again must not create a second docket.');
        $this->assertSame(1, PosDocketPayment::count(), 'A replayed cycle must not create a second payment.');
        $this->assertSame(1, DrinkStockMovement::where('type', 'sale')->count(), 'Stock must not be decremented twice.');
    }

    public function test_a_payment_is_not_applied_twice(): void
    {
        $this->seedSharedReferenceData();
        $this->serveTheMesh();

        $this->peersReachable = false;
        $this->sellOnTerminal();
        $this->peersReachable = true;
        $this->runSync('a');

        $this->actAsNode('a');
        $change = SyncChange::where('table_name', 'pos_docket_payments')->firstOrFail();
        $attributes = $change->toArray();

        $this->actAsNode('b');
        $this->assertSame(1, PosDocketPayment::count());

        // Replay the same payment change straight through the applier, twice.
        // Identity is sync_uid, so the second application is a no-op rather
        // than a second row and a second accounting entry.
        app(SyncApplier::class)->apply(new SyncChange($attributes));
        app(SyncApplier::class)->apply(new SyncChange($attributes));

        $this->assertSame(1, PosDocketPayment::count(), 'A replayed payment must not create a second row.');
        $this->assertSame(0, DB::table('charges')->count(), 'A cash sale posts no folio charge.');
    }

    public function test_inventory_reconciles_by_movement_not_by_balance(): void
    {
        $this->seedSharedReferenceData();
        $this->serveTheMesh();

        $this->peersReachable = false;
        $this->sellOnTerminal(quantity: 2);
        $this->sellOnSupervisor(quantity: 1);

        $this->peersReachable = true;
        $this->converge();

        $this->actAsNode('a');
        $this->assertSame(3, DrinkStockMovement::count(), 'Both sales and the delivery are present.');
        $this->assertSame(2, (int) DrinkStockMovement::sum('quantity_change'));
        Artisan::call('drink-stock:rebuild');
        $terminalBalance = $this->stockBalance();

        $this->actAsNode('b');
        $this->assertSame(3, DrinkStockMovement::count());
        $this->assertSame(2, (int) DrinkStockMovement::sum('quantity_change'));
        Artisan::call('drink-stock:rebuild');
        $serverBalance = $this->stockBalance();

        // Neither side's local balance survives; both rebuild to the ledger
        // sum. Last-write-wins on the balance would leave 3 or 4.
        $this->assertSame(2, $terminalBalance);
        $this->assertSame($terminalBalance, $serverBalance, 'The balance must converge to the movement sum.');
    }

    public function test_a_terminal_that_sold_more_than_existed_still_converges(): void
    {
        $this->seedSharedReferenceData();
        $this->serveTheMesh();

        // Five bottles were delivered. Both nodes sold against that snapshot
        // while disconnected: 2 + 4 = 6 > 5, so the true balance is -1.
        $this->peersReachable = false;
        $this->sellOnTerminal(quantity: 2);
        $this->sellOnSupervisor(quantity: 4);

        $this->peersReachable = true;
        $this->converge();

        $this->actAsNode('a');
        $this->assertSame(3, DrinkStockMovement::count());
        $this->assertSame(-1, (int) DrinkStockMovement::sum('quantity_change'));
        Artisan::call('drink-stock:rebuild');
        $terminalStock = $this->stock();

        $this->actAsNode('b');
        Artisan::call('drink-stock:rebuild');
        $serverStock = $this->stock();

        $this->assertSame(-1, (int) $terminalStock->full_bottles, 'The balance must show the oversell, not be silently clamped.');
        $this->assertSame((int) $terminalStock->full_bottles, (int) $serverStock->full_bottles);
        $this->assertTrue($terminalStock->is_low_stock, 'The oversell must be surfaced as low stock.');
        $this->assertSame(
            (int) DrinkStockMovement::sum('quantity_change'),
            (int) $serverStock->full_bottles,
            'The balance is the ledger sum, which is the only truthful value.',
        );

        // Neither sale was discarded: both dockets survive on both nodes.
        $this->assertSame(2, PosDocket::count());
    }

    public function test_a_failure_mid_reconcile_does_not_advance_the_cursor_past_the_failure(): void
    {
        $this->actAsNode('a');
        $this->uidOne = (string) Str::ulid();
        $this->uidTwo = (string) Str::ulid();

        Http::fake(function (Request $request) {
            if ($request->method() !== 'GET') {
                return Http::response(['accepted' => 0], 200);
            }

            return Http::response($this->poison ? $this->poisonPage() : $this->goodPage(), 200);
        });

        try {
            app(SyncCycleRunner::class)->pull();
            $this->fail('The poisoned page must abort the pull.');
        } catch (\Throwable) {
            // Expected: an unregistered entity is a bug, not a transport
            // fault, so the cycle runner does not swallow it.
        }

        $this->actAsNode('a');
        $this->assertSame(0, InventoryItem::count(), 'The valid rows must roll back with the failed page.');
        $this->assertSame(
            0,
            DB::table('sync_cursors')->where('node_id', 'node-a')->where('direction', 'in')->count(),
            'The cursor must not advance past a page that failed to apply.',
        );

        // The server is fixed; the retry applies every valid change once.
        $this->poison = false;
        app(SyncCycleRunner::class)->pull();

        $this->actAsNode('a');
        $this->assertSame(2, InventoryItem::count(), 'The retry applies each change exactly once.');
        $this->assertSame(
            2,
            (int) DB::table('sync_cursors')
                ->where('node_id', 'node-a')
                ->where('target_node_id', 'node-b')
                ->where('direction', 'in')
                ->value('last_applied_change_id'),
        );
    }

    /**
     * Seed the terminal's snapshot and give the server the same reference rows.
     *
     * A terminal never authors menu items, devices or users - they are
     * read-only to it - so they do not travel over sync. They are instead
     * present on both nodes identically, exactly as a pushed-down snapshot
     * leaves them, with matching ids and ULIDs.
     */
    private function seedSharedReferenceData(): void
    {
        $this->actAsNode('a');

        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'club.pos.access', 'slug' => 'club.pos.access', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);

        $this->device = PosDevice::factory()->create();

        $this->session = PosSession::factory()->create([
            'device_id' => $this->device->id,
            'user_id' => $this->staff->id,
        ]);

        $this->item = MenuItem::factory()->create([
            'name' => 'House Lager',
            'price' => 1000,
            'is_available' => true,
        ]);

        // Delivered stock is recorded as a movement, so the ledger explains the
        // opening balance rather than the balance appearing from nowhere.
        app(DrinkStockService::class)->addStock($this->item, 5, $this->staff->id);

        $this->copyReferenceDataTo('b');
    }

    /**
     * Copy the non-synced reference rows that carry foreign keys the sale
     * depends on, preserving ids and ULIDs so the server can accept them.
     *
     * Only the rows this test created are copied. The reference tables are
     * already partly populated by a data migration on every node, and copying
     * a whole table would collide with those rows rather than mirror the
     * snapshot the terminal actually holds.
     */
    private function copyReferenceDataTo(string $letter): void
    {
        $this->harness->use('a');

        $rows = [
            'users' => DB::table('users')->where('id', $this->staff->id)->get(),
            'pos_devices' => DB::table('pos_devices')->where('id', $this->device->id)->get(),
            'menu_categories' => DB::table('menu_categories')->where('id', $this->item->menu_category_id)->get(),
            'menu_items' => DB::table('menu_items')->where('id', $this->item->id)->get(),
            'drink_stock' => DB::table('drink_stock')->where('menu_item_id', $this->item->id)->get(),
        ];

        $this->harness->use($letter);

        foreach ($rows as $table => $tableRows) {
            $tableRows = $tableRows->map(fn ($row): array => (array) $row)->all();

            if ($tableRows !== []) {
                DB::table($table)->insert($tableRows);
            }
        }
    }

    /**
     * Sell on the terminal through the real routes, while offline.
     *
     * @return array{0: string, 1: \Illuminate\Testing\TestResponse}
     */
    private function sellOnTerminal(int $quantity = 2): array
    {
        $this->actAsNode('a');

        $docketId = (string) $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => $quantity],
        )->assertOk();

        $response = $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => $this->item->price * $quantity]]],
        );

        return [$docketId, $response];
    }

    /**
     * Sell on the server independently, as a separate machine that was also
     * trading against the same snapshot while the terminal was cut off.
     */
    private function sellOnSupervisor(int $quantity): void
    {
        $this->actAsNode('b');

        // Resolve the exact rows this test seeded, not the first row in the
        // table: the reference tables carry migration-seeded menu items too,
        // and the stock consumed must be this item's stock.
        $device = PosDevice::findOrFail($this->device->id);
        $staff = User::findOrFail($this->staff->id);
        $item = MenuItem::findOrFail($this->item->id);

        $pos = app(ClubPosService::class);
        $session = $pos->getOrCreateSession($device, $staff);
        $docket = $pos->openDocket($session, $staff);
        $pos->addItem($docket, $item, $quantity, $staff);
        $pos->closeDocket($docket, [['method' => 'cash', 'amount' => $item->price * $quantity]]);
    }

    private function stock(): DrinkStock
    {
        return DrinkStock::where('menu_item_id', $this->item->id)->firstOrFail();
    }

    private function stockBalance(): int
    {
        return (int) $this->stock()->full_bottles;
    }

    /**
     * Route each outbound call to the peer's own database and controller.
     */
    private function serveTheMesh(): void
    {
        $hosts = $this->harness->hostMap();

        Http::fake(function (Request $request) use ($hosts) {
            if (! $this->peersReachable) {
                throw new ConnectionException('Connection refused');
            }

            $host = parse_url($request->url(), PHP_URL_HOST);
            $target = $hosts[$host] ?? null;

            if ($target === null) {
                throw new ConnectionException("Unknown sync host [{$host}].");
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

    private function runSync(string $letter): array
    {
        $this->actAsNode($letter);

        return app(SyncCycleRunner::class)->run();
    }

    private function converge(int $rounds = 3): void
    {
        for ($i = 0; $i < $rounds; $i++) {
            $this->runSync('a');
            $this->runSync('b');
        }
    }

    /**
     * Make the given harness node active, and peer it with only the other of
     * the two nodes this test uses.
     */
    private function actAsNode(string $letter): void
    {
        $this->harness->use($letter);

        $other = $letter === 'a' ? 'b' : 'a';

        config([
            'sync.node_type' => $letter === 'a' ? 'terminal' : 'lan_server',
            'sync.peers' => ['node-'.$other => ['base_url' => 'http://node-'.$other.'.test']],
        ]);
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
            public function __construct(public ?string $node_id) {}
        };
    }

    /**
     * A page whose first two changes apply and whose third names an entity
     * nobody registered. Applying the third throws, so the whole page - both
     * valid rows and the cursor - must roll back together.
     *
     * @return array<string, mixed>
     */
    private function poisonPage(): array
    {
        return [
            'changes' => [
                $this->inventoryItemChange($this->uidOne, 'P-1', '1000000000000-0-node-b'),
                $this->inventoryItemChange($this->uidTwo, 'P-2', '1000000000000-1-node-b'),
                [
                    'table_name' => 'definitely_not_a_registered_table',
                    'row_id' => (string) Str::ulid(),
                    'operation' => 'create',
                    'node_id' => 'node-b',
                    'hlc' => '1000000000000-2-node-b',
                    'payload' => ['anything' => true],
                ],
            ],
            'cursor' => 3,
            'has_more' => false,
        ];
    }

    /**
     * The same two valid changes, with no poison: the retry after the failure.
     *
     * @return array<string, mixed>
     */
    private function goodPage(): array
    {
        return [
            'changes' => [
                $this->inventoryItemChange($this->uidOne, 'P-1', '1000000000000-0-node-b'),
                $this->inventoryItemChange($this->uidTwo, 'P-2', '1000000000000-1-node-b'),
            ],
            'cursor' => 2,
            'has_more' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryItemChange(string $uid, string $sku, string $hlc): array
    {
        return [
            'table_name' => 'inventory_items',
            'row_id' => $uid,
            'operation' => 'create',
            'node_id' => 'node-b',
            'hlc' => $hlc,
            'payload' => [
                'sync_uid' => $uid,
                'name' => "Item {$sku}",
                'sku' => $sku,
                'unit' => 'kg',
                'sync_hlc' => $hlc,
                'sync_origin_node_id' => 'node-b',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ];
    }
}
