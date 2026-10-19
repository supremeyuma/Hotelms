<?php

namespace Tests\Feature\Cloud;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosDocketPayment;
use App\Models\PosSession;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncNode;
use App\Models\User;
use App\Print\PrintJob;
use App\Services\DrinkStockService;
use App\Services\InventoryService;
use App\Sync\SyncCycleRunner;
use App\Sync\SyncEntityRegistry;
use App\Sync\SyncLogger;
use App\Sync\SyncScope;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\ThreeNodeSyncHarness;
use Tests\TestCase;

/**
 * Phase 8, task 4 — prove the full cloud sync round trip.
 *
 * Node `a` is a club POS terminal; node `b` is the cloud. Node `c` is left
 * inert. Every write goes through the real routes, services and models, and
 * every change travels over the real ChangeLogController, applier and cursors —
 * only the transport socket is stubbed, exactly as the Phase 7 reconciliation
 * tests do it.
 *
 * Where the delivered engine differs from the brief's expectation, the test
 * asserts what the code actually does and the deviation is recorded in
 * task-4-report.md rather than hidden. No production behaviour was changed to
 * make any assertion pass.
 */
class CloudSyncIntegrationTest extends TestCase
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
    // Intent 1 — a terminal sale reaches the cloud and appears in reporting
    // ---------------------------------------------------------------------

    public function test_a_terminal_sale_reaches_the_cloud_and_appears_in_reporting(): void
    {
        $this->seedPosReferenceData();
        $this->serveTheMesh();

        // The terminal sells while its peer is unreachable: the sale commits
        // locally and is reported as queued.
        $this->peersReachable = false;
        [$docketId, $response] = $this->sellOnTerminal();
        $response->assertOk();
        $this->assertTrue($response->json('queued_for_sync'));

        // A genuine Class 1 inventory movement, written through the real
        // service on the terminal (the literal `inventory_movements` table the
        // brief names; a POS sale records `drink_stock_movements` instead).
        $this->actAsNode('a');
        $inventoryMovement = app(InventoryService::class)->addStock(
            InventoryItem::findOrFail($this->inventoryItem->id),
            InventoryLocation::findOrFail($this->inventoryLocation->id),
            7.0,
            $this->staff->id,
            'cloud-integration-seed',
        );

        $this->actAsNode('a');
        $terminalDocket = PosDocket::findOrFail($docketId);

        // One cycle is the whole synchronization latency.
        $this->peersReachable = true;
        $this->runSync('a');

        $this->actAsNode('b');
        $this->assertSame(1, PosDocket::count(), 'The sale must land on the cloud exactly once.');
        $this->assertSame(1, PosDocketItem::count(), 'The line item must land exactly once.');
        $this->assertSame(1, PosDocketPayment::count(), 'The payment must land exactly once.');
        $this->assertSame(1, InventoryMovement::count(), 'The inventory movement must land exactly once.');
        $this->assertSame(2, \App\Models\DrinkStockMovement::count(), 'The delivery and the sale movement must both land.');

        $cloudDocket = PosDocket::firstOrFail();
        $this->assertSame($terminalDocket->sync_uid, $cloudDocket->sync_uid, 'Identity is the origin ULID, not a local id.');
        $this->assertSame('closed', $cloudDocket->status);
        $this->assertSame((string) $terminalDocket->total, (string) $cloudDocket->total);

        // Repeating the cycle must not duplicate the sale.
        $this->runSync('a');
        $this->actAsNode('b');
        $this->assertSame(1, PosDocket::count(), 'A repeat cycle must not duplicate the sale.');
        $this->assertSame(1, PosDocketItem::count());
        $this->assertSame(1, PosDocketPayment::count());
        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame(1, InventoryMovement::where('sync_uid', $inventoryMovement->sync_uid)->count());

        // The existing reporting read shows the sale: the same JSON endpoint the
        // Club POS sales screen loads.
        $cloudStaff = $this->makeClubUser('b');
        $this->actAsNode('b');

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
        $this->assertCount(1, $report['dockets'], 'The sales report must list the synced docket.');
        $this->assertSame(
            $terminalDocket->sync_uid,
            $report['dockets'][0]['sync_uid'],
            'The report row must be the synced sale.',
        );
    }

    /**
     * The cloud booking written by the online channel must reach the terminal,
     * past the 48-hour authority cutoff, exactly once.
     */
    public function test_a_cloud_booking_reaches_the_terminal(): void
    {
        $this->seedSharedProperty();
        $this->serveTheMesh();

        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));

        // The cloud (node b) is the online channel: only arrivals beyond the
        // local window are its to take.
        $this->actAsNode('b');
        $this->withSession(['booking' => $this->onlineSession(
            'Cloud Integration Guest',
            now()->addDays(3)->toDateString(),
            now()->addDays(5)->toDateString(),
        )])->post(route('booking.create'))->assertRedirect();

        $cloudBooking = Booking::where('guest_name', 'Cloud Integration Guest')->firstOrFail();

        // The terminal pulls the cloud's change on its next cycle.
        $this->runSync('a');

        $this->actAsNode('a');
        $terminalBooking = Booking::where('sync_uid', $cloudBooking->sync_uid)->firstOrFail();

        $this->assertSame(
            $cloudBooking->booking_code,
            $terminalBooking->booking_code,
            'The same reservation must arrive on the terminal.',
        );
        $this->assertSame(
            (string) $cloudBooking->check_in->toDateString(),
            (string) $terminalBooking->check_in->toDateString(),
            'The arrival date must survive the trip.',
        );
        $this->assertSame(
            $cloudBooking->room_id,
            $terminalBooking->room_id,
            'The booking must reach the terminal with the same room assignment.',
        );
        $this->assertNotNull($terminalBooking->room_id);
        $this->assertSame(
            $this->room->id,
            $terminalBooking->room_id,
            'The assigned room must be the room the cloud assigned.',
        );

        // DEVIATION (reported, BLOCKED-level): booking_rooms is the authoritative
        // pivot but is absent from SyncEntityRegistry, so it never travels. The
        // legacy bookings.room_id scalar above is the only room evidence the
        // terminal gets; the front desk's room relation (`with('rooms')`) is
        // empty. Asserted here so the gap is visible, not silently accepted.
        $this->assertSame(
            0,
            DB::table('booking_rooms')->where('booking_id', $terminalBooking->id)->count(),
            'The booking_rooms pivot is not synced; see task-4-report.md.',
        );
    }

    /**
     * Class 5 settings are cloud-authoritative. The gate that stops a terminal
     * pushing one up works; the cloud → terminal path is unimplemented, which
     * is recorded as a finding rather than faked.
     */
    public function test_class_5_settings_can_never_push_up(): void
    {
        $this->actAsNode('a');
        $this->assertFalse(
            SyncScope::mayEmit('settings'),
            'A terminal must never emit a settings change.',
        );

        // A terminal edit of the cutoff produces no change row at all.
        Setting::set('booking_local_authority_cutoff_hours', '24');
        $this->assertSame(
            0,
            SyncChange::where('table_name', 'settings')->count(),
            'A terminal settings write must never be captured for sync.',
        );

        $this->actAsNode('b');
        $this->assertTrue(SyncScope::mayEmit('settings'), 'The cloud may author Class 5 configuration.');

        // DEVIATION (reported, BLOCKED-level): the Class 5 push-down is
        // unimplemented. Setting is not Syncable and has no capture path, so a
        // cloud write produces no change either, and a would-be settings change
        // cannot be applied because the registry has no settings model.
        Setting::set('booking_local_authority_cutoff_hours', '24');
        $this->assertSame(
            0,
            SyncChange::where('table_name', 'settings')->count(),
            'No settings change exists to push down (unimplemented).',
        );

        $this->assertSame(
            'settings',
            $this->registryHasNoModelFor('settings'),
        );
    }

    /**
     * Class 1 is append-only: two distinct facts created on two nodes both
     * survive on both nodes — neither is merged over the other.
     */
    public function test_a_cloud_side_class_1_fact_is_never_merged_over_the_terminals(): void
    {
        $this->seedPosReferenceData();
        $this->serveTheMesh();

        // The terminal records one sale while disconnected.
        $this->peersReachable = false;
        [$terminalDocketId] = $this->sellOnTerminal();
        $this->peersReachable = true;

        $this->actAsNode('a');
        $terminalDocket = PosDocket::findOrFail($terminalDocketId);

        // The cloud records a genuinely different sale of its own (a distinct
        // sync_uid — a different fact, not an edit of the terminal's).
        $this->actAsNode('b');
        $cloudDocket = $this->sellOnCloud();

        $this->assertNotSame(
            $terminalDocket->sync_uid,
            $cloudDocket->sync_uid,
            'Two independent sales are two distinct facts.',
        );

        $this->converge();

        foreach (['a' => 'terminal', 'b' => 'cloud'] as $letter => $label) {
            $this->actAsNode($letter);
            $this->assertSame(2, PosDocket::count(), "Both sales must survive on the {$label}.");
            $this->assertSame(1, PosDocket::where('sync_uid', $terminalDocket->sync_uid)->count());
            $this->assertSame(1, PosDocket::where('sync_uid', $cloudDocket->sync_uid)->count());
        }

        // NOTE (reported): MergePolicy only keeps both rows apart while
        // sync_row_key is null, which no migration defines. A cloud edit to the
        // *same* sync_uid is resolved by last-write-wins, not append-only, so
        // "never merged over" is true for distinct facts (proven here) and not
        // for same-identity rewrites of an existing fact. See task-4-report.md.
    }

    /**
     * A malformed change is handled at the boundary without harming the app.
     *
     * Each shape violation is a 422; an unregistered table or a which-cannot-
     * be-inserted payload throws and rolls the whole batch back with a 500; an
     * unparseable HLC is accepted (deviation from the brief — see the report).
     */
    public function test_a_malformed_change_cannot_error_the_app(): void
    {
        $this->actAsNode('b');
        $this->authenticateCaller();

        // --- Shape violations: rejected at the boundary, nothing written. ---
        $this->pushBatch([$this->change(['table_name' => null])])->assertStatus(422);
        $this->pushBatch([$this->change(['hlc' => null])])->assertStatus(422);
        $this->pushBatch([$this->change(['operation' => 'upsert'])])->assertStatus(422);
        $this->assertSame(0, InventoryItem::count(), 'A rejected batch must write nothing.');

        // --- Unparseable HLC: ACCEPTED, because a change for a row this node
        // has never seen is adopted without consulting a merge policy, and only
        // a merge parses the HLC. The brief expected this to be rejected.
        $acceptedSku = 'R-BND-ACCEPTED';
        $this->pushBatch([$this->change([
            'hlc' => 'not-a-valid-hlc',
            'payload' => [
                'sync_uid' => (string) Str::ulid(),
                'name' => 'Boundary Rice',
                'sku' => $acceptedSku,
                'unit' => 'kg',
            ],
        ])])->assertOk();

        $this->assertSame(
            1,
            InventoryItem::where('sku', $acceptedSku)->count(),
            'DEVIATION: an unparseable HLC is accepted on insert, not rejected.',
        );

        // The one batch that was accepted advanced the cursor; every failed
        // batch below must leave it exactly where it was.
        $cursorAfterAccepted = (int) DB::table('sync_cursors')
            ->where('node_id', 'node-b')
            ->where('target_node_id', 'node-a')
            ->where('direction', 'in')
            ->value('last_applied_change_id');

        // --- Table not in the manifest: the applier throws, the whole batch
        // (including an otherwise-valid change) rolls back, and the caller sees
        // a 500 rather than a per-change rejection.
        $validSku = 'R-BND-ROLLBACK';
        $this->pushBatch([
            $this->change([
                'payload' => [
                    'sync_uid' => (string) Str::ulid(),
                    'name' => 'Rollback Rice',
                    'sku' => $validSku,
                    'unit' => 'kg',
                ],
            ]),
            $this->change([
                'table_name' => 'definitely_not_a_registered_table',
                'payload' => ['anything' => true],
            ]),
        ])->assertStatus(500);

        $this->assertSame(
            0,
            InventoryItem::where('sku', $validSku)->count(),
            'A change alongside a bad one must roll back with it.',
        );

        // --- Missing required columns: the insert fails, same rollback.
        $missingColumnSku = 'R-BND-MISSING';
        $this->pushBatch([
            $this->change([
                'payload' => [
                    'sync_uid' => (string) Str::ulid(),
                    'name' => 'Missing Column Rice',
                    'sku' => $missingColumnSku,
                    'unit' => 'kg',
                ],
            ]),
            $this->change([
                'payload' => [
                    'sync_uid' => (string) Str::ulid(),
                    // `name` omitted: inventory_items.name is NOT NULL.
                    'sku' => 'R-BND-BROKEN',
                    'unit' => 'kg',
                ],
            ]),
        ])->assertStatus(500);

        $this->assertSame(
            0,
            InventoryItem::where('sku', $missingColumnSku)->count(),
            'A payload that cannot be inserted must roll the batch back.',
        );

        // The failed batches never advanced the receiver's inbound cursor.
        $this->assertSame(
            $cursorAfterAccepted,
            (int) DB::table('sync_cursors')
                ->where('node_id', 'node-b')
                ->where('target_node_id', 'node-a')
                ->where('direction', 'in')
                ->value('last_applied_change_id'),
            'A rejected batch must not advance the cursor.',
        );

        // --- The rest of the app is unaffected: a normal write still works and
        // is still captured for sync.
        $before = SyncChange::where('node_id', 'node-b')->count();
        InventoryItem::create(['name' => 'Normal Rice', 'sku' => 'R-BND-NORMAL', 'unit' => 'kg']);

        $this->assertSame(1, InventoryItem::where('sku', 'R-BND-NORMAL')->count());
        $this->assertGreaterThan(
            $before,
            SyncChange::where('node_id', 'node-b')->count(),
            'The app still captures changes after the malformed batches.',
        );
    }

    /**
     * A dead peer never surfaces as a web error: the sale still commits and the
     * public booking flow still works.
     */
    public function test_a_dead_peer_never_surfaces_as_a_web_error(): void
    {
        $this->seedPosReferenceData();
        $this->seedSharedProperty();
        $this->serveTheMesh();

        $this->peersReachable = false;

        // A sale with an unreachable peer: local commit, no 5xx, told queued.
        [$docketId, $response] = $this->sellOnTerminal();
        $response->assertOk();
        $this->assertTrue($response->json('queued_for_sync'));

        $this->actAsNode('a');
        $this->assertSame('closed', PosDocket::findOrFail($docketId)->status, 'The sale commits regardless of the peer.');

        // The public booking flow does not depend on sync at all.
        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));
        $this->actAsNode('b');
        $this->withSession(['booking' => $this->onlineSession(
            'Dead Peer Guest',
            now()->addDays(3)->toDateString(),
            now()->addDays(4)->toDateString(),
        )])->post(route('booking.create'))->assertRedirect();

        $this->assertSame(
            1,
            Booking::where('guest_name', 'Dead Peer Guest')->count(),
            'The public booking flow must still succeed with the peer unreachable.',
        );
    }

    /**
     * When the applier itself throws during the connectivity probe, the sale
     * must not be lost and the failure must stay invisible.
     *
     * Spec section 10: a sync failure must never surface as a web error. A peer
     * whose change cannot be applied is a failed peer, not a 500/422 on the
     * sale the cashier already committed.
     */
    public function test_a_poisoned_peer_change_does_not_lose_the_sale(): void
    {
        $this->seedPosReferenceData();
        $this->serveTheMesh();

        // A poison change waits in the cloud's log: a table nobody registered.
        // Allocate its sequence through the real counter so the cloud's next
        // genuine write (the booking below) does not collide on (node_id,
        // sequence).
        $this->actAsNode('b');
        SyncChange::create([
            'table_name' => 'definitely_not_a_registered_table',
            'row_id' => (string) Str::ulid(),
            'operation' => 'create',
            'node_id' => 'node-b',
            'hlc' => '1000000000000-0-node-b',
            'sequence' => SyncLogger::nextSequence('node-b'),
            'payload' => ['anything' => true],
        ]);

        // The terminal sells; its connectivity probe will pull the poison and
        // the applier will throw inside that probe.
        $this->peersReachable = true;
        [$docketId, $response] = $this->sellOnTerminal();

        // A sync failure is invisible to the cashier: the sale request returns
        // its normal success response, the receipt is queued, and the sale is
        // reported as waiting for sync.
        $response->assertOk(
            'A sync failure must never surface as a web error.',
        );
        $this->assertTrue($response->json('receipt_queued'), 'The receipt must be queued.');
        $this->assertTrue(
            $response->json('queued_for_sync'),
            'A peer whose change cannot be applied is a failed peer, so the sale is queued.',
        );

        $this->actAsNode('a');
        $this->assertSame(
            'closed',
            PosDocket::findOrFail($docketId)->status,
            'The sale must be committed regardless of the sync failure.',
        );

        $this->assertSame(
            1,
            PrintJob::where('docket_id', $docketId)->count(),
            'A print job must be queued for the committed sale.',
        );

        // The public booking flow, which does not run sync, is unaffected.
        $this->seedSharedProperty();
        $this->actAsNode('b');
        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));
        $bookingResponse = $this->withSession(['booking' => $this->onlineSession(
            'Poisoned Peer Guest',
            now()->addDays(3)->toDateString(),
            now()->addDays(5)->toDateString(),
        )])->post(route('booking.create'));
        $bookingResponse->assertRedirect();

        $this->assertSame(
            1,
            Booking::where('guest_name', 'Poisoned Peer Guest')->count(),
            'The public booking flow must still work while a sync failure is present.',
        );
    }

    // ------------------------------------------------------------------
    // Seeding
    // ------------------------------------------------------------------

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
            'name' => 'Cloud Integration Store',
            'type' => InventoryLocation::TYPE_MAIN_STORE,
        ]);

        $this->inventoryItem = InventoryItem::create([
            'name' => 'Cloud Integration Rice',
            'sku' => 'INV-CLOUD-1',
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
     * Seed the one property, room type and room the booking tests depend on.
     */
    private function seedSharedProperty(): void
    {
        if ($this->property !== null) {
            return;
        }

        $previous = config('sync.enabled');
        config(['sync.enabled' => false]);
        $this->actAsNode('a');

        $property = Property::create([
            'name' => 'Cloud Integration Hotel',
            'address' => '1 Integration Way',
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Standard Double',
            'max_occupancy' => 2,
            'base_price' => 25000,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Standard 101',
            'code' => 'STD-101',
            'status' => 'available',
            'meta' => [],
        ]);

        $this->property = $property;
        $this->roomType = $roomType;
        $this->room = $room;

        $this->harness->use('a');
        $copy = [
            'properties' => DB::table('properties')->where('id', $property->id)->get(),
            'room_types' => DB::table('room_types')->where('id', $roomType->id)->get(),
            'rooms' => DB::table('rooms')->where('id', $room->id)->get(),
        ];
        $this->harness->use('b');

        foreach ($copy as $table => $tableRows) {
            $tableRows = $tableRows->map(fn ($row): array => (array) $row)->all();

            if ($tableRows !== []) {
                DB::table($table)->insert($tableRows);
            }
        }

        $this->actAsNode('a');
        config(['sync.enabled' => $previous]);
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

    /**
     * Sell on the cloud through the real POS service, as a separate machine.
     */
    private function sellOnCloud(): PosDocket
    {
        $this->actAsNode('b');

        $service = app(\App\Services\ClubPosService::class);
        $device = PosDevice::findOrFail($this->device->id);
        $staff = User::findOrFail($this->staff->id);
        $menuItem = MenuItem::findOrFail($this->menuItem->id);

        $session = $service->getOrCreateSession($device, $staff);
        $docket = $service->openDocket($session, $staff, customerName: 'Cloud Walk-in');
        $service->addItem($docket, $menuItem, 1, $staff);

        return $service->closeDocket($docket, [['method' => 'cash', 'amount' => $menuItem->price]]);
    }

    // ------------------------------------------------------------------
    // Malformed-change plumbing
    // ------------------------------------------------------------------

    private function authenticateCaller(): void
    {
        SyncNode::create([
            'node_id' => 'node-a',
            'name' => 'node-a',
            'type' => 'terminal',
            'status' => 'active',
            'credential' => hash('sha256', 'shared-secret'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $changes
     */
    private function pushBatch(array $changes): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/sync/changes', ['changes' => $changes], [
            'X-Sync-Node-Id' => 'node-a',
            'Authorization' => 'Bearer shared-secret',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function change(array $overrides = []): array
    {
        return array_merge([
            'change_id' => 1,
            'table_name' => 'inventory_items',
            'row_id' => (string) Str::ulid(),
            'operation' => 'create',
            'node_id' => 'node-a',
            'hlc' => '1000000000000-0-node-a',
            'payload' => [
                'sync_uid' => (string) Str::ulid(),
                'name' => 'Boundary Rice',
                'sku' => 'R-BND-'.Str::random(6),
                'unit' => 'kg',
            ],
        ], $overrides);
    }

    /**
     * Proves the registry has no settings model (the Class 5 down-path is
     * unimplemented). Returns the key so the caller can assert on it.
     */
    private function registryHasNoModelFor(string $key): string
    {
        try {
            SyncEntityRegistry::modelFor($key);
        } catch (\RuntimeException) {
            return $key;
        }

        $this->fail("Expected no registered model for [{$key}].");
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

    private function converge(int $rounds = 3): void
    {
        for ($i = 0; $i < $rounds; $i++) {
            $this->runSync('a');
            $this->runSync('b');
        }
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
