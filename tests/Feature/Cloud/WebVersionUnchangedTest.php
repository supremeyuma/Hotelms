<?php

namespace Tests\Feature\Cloud;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosSession;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\DrinkStockService;
use App\Sync\SyncCycleRunner;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\ThreeNodeSyncHarness;
use Tests\TestCase;

/**
 * Phase 8, task 5 - the phase's negative test (spec section 10).
 *
 * Adding sync must not change anything the guest or the staff sees. This file
 * proves three things against the delivered code:
 *
 *   1. The public booking flow behaves exactly as it did before: an arrival
 *      beyond the local-authority cutoff is booked through the ordinary online
 *      web flow.
 *   2. An existing screen renders a sale that arrived over the real sync
 *      endpoint, with no screen, component or view modified.
 *   3. A staff client still holds no database: the node kinds that sync do not
 *      include it.
 *
 * No production behaviour was changed to make any assertion pass. Where the
 * brief's assumption does not match the code the test asserts what the code
 * actually does and the deviation is recorded in task-5-report.md.
 */
class WebVersionUnchangedTest extends TestCase
{
    private ThreeNodeSyncHarness $harness;

    /**
     * Read at call time by the single stubbed transport closure. Http::fake
     * appends rather than replaces, so a flag (not a new fake) turns the mesh
     * on and off.
     */
    private bool $peersReachable = true;

    private User $staff;

    private PosDevice $device;

    private PosSession $session;

    private MenuItem $menuItem;

    private InventoryItem $inventoryItem;

    private InventoryLocation $inventoryLocation;

    private ?Property $property = null;

    private ?RoomType $roomType = null;

    private ?Room $room = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

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

    // ---------------------------------------------------------------------
    // Intent 1 - the public booking flow is unchanged
    // ---------------------------------------------------------------------

    /**
     * Spec section 10: the guest books online exactly as before.
     *
     * Node `b` is the cloud, the online channel. The arrival is three days out,
     * well beyond the 48-hour local-authority window, so the ordinary web flow
     * owns it. No sync mesh is involved: the booking is created by the same
     * route, controller and service it always was.
     */
    public function test_the_public_booking_flow_is_unchanged_beyond_the_cutoff(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));

        $this->seedSharedProperty();
        config(['sync.node_type' => 'cloud']);

        $checkIn = now()->addDays(3)->toDateString();
        $checkOut = now()->addDays(5)->toDateString();

        $this->withSession(['booking' => $this->onlineSession('Unchanged Web Guest', $checkIn, $checkOut)])
            ->post(route('booking.create'))
            ->assertRedirect();

        $booking = Booking::where('guest_name', 'Unchanged Web Guest')->firstOrFail();

        $this->assertSame('Unchanged Web Guest', $booking->guest_name);
        $this->assertSame($checkIn, $booking->check_in->toDateString());
        $this->assertSame($checkOut, $booking->check_out->toDateString());
        $this->assertSame(
            $this->room->id,
            $booking->room_id,
            'The ordinary web flow must still assign the selected room.',
        );
    }

    // ---------------------------------------------------------------------
    // Intent 2 - existing screens show synced POS data unmodified
    // ---------------------------------------------------------------------

    /**
     * Spec section 10: no screen needs a change to show a synced sale.
     *
     * A docket is sold on a club POS terminal (node `a`) and travels to the
     * cloud (node `b`) through the real ChangeLogController and applier. The
     * cloud then renders the EXISTING Club POS sales dashboard - the same
     * component and controller as before this phase - and the synced sale is
     * present in its props, plus in the JSON endpoint that screen loads.
     *
     * The brief also asked for an existing admin/reporting screen that shows
     * POS sales. None does: no controller under app/Http/Controllers/Admin
     * reads `pos_dockets` (nor the `reporting_pos_facts` projection), so there
     * is no admin screen to render. Per the brief, this test asserts the Club
     * POS sales screen and its JSON route instead.
     */
    public function test_existing_admin_screens_need_no_modification_to_show_pos_data(): void
    {
        $this->seedPosReferenceData();
        $this->serveTheMesh();

        // The terminal sells while its peer is unreachable, exactly as it does
        // when the cloud is briefly unavailable.
        $this->peersReachable = false;
        [$docketId] = $this->sellOnTerminal();
        $this->peersReachable = true;

        $this->actAsNode('a');
        $terminalDocket = PosDocket::findOrFail($docketId);

        // One cycle is the whole synchronization latency.
        $this->runSync('a');

        $this->actAsNode('b');
        $cloudStaff = $this->makeClubUser('b');

        // The existing sales screen renders the synced docket, unmodified.
        $page = $this->actingAs($cloudStaff)->get(route('club.pos.reports.sales'));
        $page->assertOk();
        $page->assertInertia(fn (Assert $inertia) => $inertia
            ->component('ClubPos/reports/Sales')
            ->where('summary.total_dockets', 1)
            ->has('dockets', 1)
            ->where('dockets.0.sync_uid', $terminalDocket->sync_uid)
        );

        // The JSON endpoint the same screen loads in-page agrees.
        $report = $this->actingAs($cloudStaff)
            ->getJson(route('club.pos.reports.sales.data'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $report['summary']['total_dockets'], 'Reporting must count the synced sale once.');
        $this->assertSame(
            (float) $terminalDocket->total,
            (float) $report['summary']['total_sales'],
            'Reporting must total the synced sale.',
        );
        $this->assertSame(
            $terminalDocket->sync_uid,
            $report['dockets'][0]['sync_uid'],
            'The report row must be the synced sale.',
        );
    }

    // ---------------------------------------------------------------------
    // Intent 3 - a staff client holds no database
    // ---------------------------------------------------------------------

    /**
     * Spec sections 4.1 and 9.2: a staff client is a Tauri window pointed at a
     * LAN server. It holds no database and runs no business logic, so it is not
     * a node kind that syncs and appears nowhere in the sync scope.
     *
     * The web app cannot inspect a client's disk, so the "no database" half is
     * proven on the desktop side and cited here rather than faked with a
     * database-creation check the code does not have:
     *
     *   - desktop/src-tauri/src/envfile.rs renders `SYNC_NODE_TYPE=staff_client`
     *     with `DB_DATABASE=:memory:` and no DB_USERNAME/DB_PASSWORD, pinned by
     *     `staff_client_writes_no_database_credentials`.
     *   - desktop/src-tauri/src/setup/mod.rs: `ClientOptions` carries only a
     *     `server_url`, and `run_staff_client` performs no migration.
     *
     * What this test can prove is the sync-scope half: the node kinds that
     * synchronize are exactly the three that own data, and a staff client is
     * deliberately absent.
     */
    public function test_staff_clients_still_hold_no_database(): void
    {
        $nodes = config('sync_scope.nodes');

        $this->assertSame(
            ['cloud', 'lan_server', 'terminal'],
            $nodes,
            'The node kinds that sync are those that own data.'
        );
        $this->assertNotContains(
            'staff_client',
            $nodes,
            'A staff client holds no database (spec 4.1), so it appears nowhere in the sync scope.'
        );
    }

    // ------------------------------------------------------------------
    // Seeding
    // ------------------------------------------------------------------

    /**
     * Seed the one property, room type and room the booking test depends on,
     * on the active node (the cloud, node `b`).
     */
    private function seedSharedProperty(): void
    {
        if ($this->property !== null) {
            return;
        }

        $this->actAsNode('b');

        $this->property = Property::create([
            'name' => 'Web Unchanged Hotel',
            'address' => '1 Unchanged Way',
        ]);

        $this->roomType = RoomType::create([
            'property_id' => $this->property->id,
            'title' => 'Standard Double',
            'max_occupancy' => 2,
            'base_price' => 25000,
            'features' => [],
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $this->roomType->id,
            'name' => 'Standard 101',
            'code' => 'STD-101',
            'status' => 'available',
            'meta' => [],
        ]);
    }

    /**
     * Seed the POS reference data on the terminal and mirror the non-synced
     * rows onto the cloud, exactly as a pushed-down install snapshot leaves
     * them.
     */
    private function seedPosReferenceData(): void
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

        $this->menuItem = MenuItem::factory()->create([
            'name' => 'House Lager',
            'price' => 1000,
            'is_available' => true,
        ]);

        app(DrinkStockService::class)->addStock($this->menuItem, 5, $this->staff->id);

        $this->inventoryLocation = InventoryLocation::create([
            'name' => 'Web Unchanged Store',
            'type' => InventoryLocation::TYPE_MAIN_STORE,
        ]);

        $this->inventoryItem = InventoryItem::create([
            'name' => 'Web Unchanged Rice',
            'sku' => 'INV-WEB-1',
            'unit' => 'kg',
        ]);

        $this->copyReferenceDataTo('b');

        $this->actAsNode('a');
    }

    /**
     * Copy the reference rows this test created to the peer, preserving ids and
     * ULIDs so the synced rows that point at them resolve.
     */
    private function copyReferenceDataTo(string $letter): void
    {
        $this->harness->use('a');

        $rows = [
            'users' => DB::table('users')->where('id', $this->staff->id)->get(),
            'pos_devices' => DB::table('pos_devices')->where('id', $this->device->id)->get(),
            'menu_categories' => DB::table('menu_categories')->where('id', $this->menuItem->menu_category_id)->get(),
            'menu_items' => DB::table('menu_items')->where('id', $this->menuItem->id)->get(),
            'drink_stock' => DB::table('drink_stock')->where('menu_item_id', $this->menuItem->id)->get(),
            'inventory_locations' => DB::table('inventory_locations')->where('id', $this->inventoryLocation->id)->get(),
            'inventory_items' => DB::table('inventory_items')->where('id', $this->inventoryItem->id)->get(),
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
     * A club user on the given node, with the POS permission the report routes
     * require.
     */
    private function makeClubUser(string $letter): User
    {
        return $this->harness->onNode($letter, function (): User {
            $role = Role::firstOrCreate(
                ['name' => 'club_staff', 'guard_name' => 'web'],
                ['slug' => 'club_staff'],
            );
            $permission = Permission::firstOrCreate(
                ['name' => 'club.pos.access', 'guard_name' => 'web'],
                ['slug' => 'club.pos.access'],
            );
            $role->givePermissionTo($permission);

            $user = User::factory()->create();
            $user->assignRole($role);

            return $user;
        });
    }

    // ------------------------------------------------------------------
    // Real writes
    // ------------------------------------------------------------------

    /**
     * Sell on the terminal through the real routes.
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
            ['menu_item_id' => $this->menuItem->id, 'quantity' => $quantity],
        )->assertOk();

        $response = $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => $this->menuItem->price * $quantity]]],
        );

        return [$docketId, $response];
    }

    // ------------------------------------------------------------------
    // Transport
    // ------------------------------------------------------------------

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

    /**
     * Make the given harness node active, peer it with only the other node, and
     * set its node type (a = terminal, b = cloud).
     */
    private function actAsNode(string $letter): void
    {
        $this->harness->use($letter);

        $other = $letter === 'a' ? 'b' : 'a';

        config([
            'sync.node_type' => $letter === 'a' ? 'terminal' : 'cloud',
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
     * @return array<string, mixed>
     */
    private function onlineSession(string $guestName, string $checkIn, string $checkOut): array
    {
        return [
            'room_type_id' => $this->roomType->id,
            'quantity' => 1,
            'selected_room_ids' => [$this->room->id],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 1,
            'children' => 0,
            'guest_name' => $guestName,
            'guest_email' => strtolower(str_replace(' ', '.', $guestName)).'@example.test',
            'guest_phone' => '08030000009',
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'purpose_of_stay' => null,
            'special_requests' => null,
        ];
    }
}
