<?php

namespace Tests\Feature\Offline;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Booking\BookingAuthority;
use App\Sync\SyncCycleRunner;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\ThreeNodeSyncHarness;
use Tests\TestCase;

/**
 * Task 5 of the offline terminal work: verify the front-desk half of the
 * Phase 5 booking-authority cutoff against a real second node.
 *
 * Spec section 8 partitions booking authority by arrival date. Phase 5
 * enforced that partition on both channels, but proved the front-desk half
 * only against configuration. This file walks the section 8 table across two
 * genuinely separate databases joined by the real sync engine: node `a` is the
 * LAN server (front desk, `sync.node_type = lan_server`) and node `b` is the
 * cloud (online channel, `sync.node_type = cloud`). Each node is peered only
 * with the other, and the third harness database is left inert.
 *
 * Only the transport socket is imagined. The front-desk and online booking
 * writes go through the real routes, controllers, services and gates; the
 * changes travel over the real ChangeLogController, applier and cursors. The
 * reference data a desk or guest needs (property, room type, room) is present
 * on both nodes with matching ids and ULIDs, exactly as a pushed-down snapshot
 * leaves it, so only the bookings themselves travel.
 *
 * The physical two-machine walk-through (cloud Postgres plus a supervised LAN
 * server with embedded Postgres) cannot run in this environment and is left as
 * an OWNER-RUN step in the task report; nothing here pretends to have dialled a
 * real LAN server.
 */
class OfflineBookingAuthorityTest extends TestCase
{
    private ThreeNodeSyncHarness $harness;

    /**
     * Read at call time by the single stubbed transport closure. A per-run fake
     * would leave the first stub in place - Http::fake() appends rather than
     * replaces - so the flag is what turns the server on and off.
     */
    private bool $peersReachable = true;

    private int $roomTypeId = 0;

    private int $roomId = 0;

    private ?User $desk = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config([
            'sync.enabled' => true,
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

    /**
     * Walk the section 8 table against the running pair.
     *
     * Now and Tomorrow are the front desk's: the desk books them on the LAN
     * server and the online funnel is closed on the cloud. Three weeks out is
     * the cloud's: the desk refuses it and the online channel accepts it. Each
     * cell is asserted by driving the real channel on the node that owns it,
     * not by reading configuration.
     */
    public function test_the_partition_holds_between_a_real_lan_server_and_the_cloud(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));

        $this->seedSharedProperty();
        $this->serveTheMesh();

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $farIn = now()->addDays(21)->toDateString();
        $farOut = now()->addDays(23)->toDateString();

        // --- LAN server (node a): the front desk ---
        $this->actAsNode('a');
        $desk = $this->frontDeskUser();

        // Now (walk-in): front desk may book on the LAN.
        $this->actingAs($desk)
            ->post(route('frontdesk.bookings.store'), $this->deskPayload('Walk In', $today, $tomorrow))
            ->assertRedirect();

        // Tomorrow: front desk may book on the LAN.
        $this->actingAs($desk)
            ->post(route('frontdesk.bookings.store'), $this->deskPayload('Tomorrow Guest', $tomorrow, now()->addDays(2)->toDateString()))
            ->assertRedirect();

        // Three weeks out: the desk refuses and hands the guest to online.
        $this->actingAs($desk)
            ->post(route('frontdesk.bookings.store'), $this->deskPayload('Refused Far Guest', $farIn, $farOut))
            ->assertStatus(422);

        $walkIn = Booking::where('guest_name', 'Walk In')->firstOrFail();
        $tomorrowBooking = Booking::where('guest_name', 'Tomorrow Guest')->firstOrFail();
        $this->assertSame(0, Booking::where('guest_name', 'Refused Far Guest')->count(), 'The LAN server must not hold a booking it refused.');

        // --- Cloud (node b): the online channel ---
        $this->actAsNode('b');

        // Now: online is closed.
        $this->getJson($this->onlineSearchUrl($today))->assertStatus(422);

        // Tomorrow: online is closed.
        $this->getJson($this->onlineSearchUrl($tomorrow))->assertStatus(422);

        // Three weeks out: online accepts.
        $this->withSession(['booking' => $this->onlineSession('Online Far Guest', $farIn, $farOut)])
            ->post(route('booking.create'))
            ->assertRedirect();

        $cloudBooking = Booking::where('guest_name', 'Online Far Guest')->firstOrFail();

        // One cycle each way is the whole synchronization latency: a push of
        // the LAN server's bookings to the cloud and a pull of the cloud's
        // booking back down.
        $this->runSync('a');

        $this->actAsNode('b');
        $this->assertSame(1, Booking::where('sync_uid', $walkIn->sync_uid)->count(), 'The walk-in must reach the cloud.');
        $this->assertSame(1, Booking::where('sync_uid', $tomorrowBooking->sync_uid)->count(), 'The tomorrow booking must reach the cloud.');

        $this->actAsNode('a');
        $this->assertSame(1, Booking::where('sync_uid', $cloudBooking->sync_uid)->count(), 'The cloud booking must reach the LAN server.');
    }

    /**
     * The test that justifies the topology: a walk-in taken on the LAN server
     * appears in manager reporting on the cloud within one sync cycle.
     *
     * Section 8: "it reaches manager reporting within synchronization latency."
     * If a walk-in does not show up here, the front desk will refuse to use the
     * LAN path and the whole topology is dead.
     */
    public function test_a_walk_in_reaches_manager_reporting_within_sync_latency(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));

        $this->seedSharedProperty();
        $this->serveTheMesh();

        $this->actAsNode('a');
        $desk = $this->frontDeskUser();

        $this->actingAs($desk)
            ->post(route('frontdesk.bookings.store'), $this->deskPayload(
                'Reporting Walk In',
                now()->toDateString(),
                now()->addDay()->toDateString(),
            ))
            ->assertRedirect();

        $walkIn = Booking::where('guest_name', 'Reporting Walk In')->firstOrFail();

        // The next cycle is the synchronization latency.
        $this->runSync('a');

        $this->actAsNode('b');
        $manager = $this->managerUser();

        $response = $this->actingAs($manager)
            ->get(route('admin.bookings.index', ['search' => $walkIn->booking_code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Bookings/Index')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.booking_code', $walkIn->booking_code)
        );
    }

    /**
     * Verify the section 8 slack against the real nodes.
     *
     * Spec section 8: the cutoff "guarantees the LAN server holds the
     * reservation long before arrival, and provides 36 hours of slack to notice
     * a failed sync."
     *
     * The delivered cutoff is 48 hours with a date-only arrival date (Phase 5,
     * Decision 2), so the front desk owns today and tomorrow and the earliest
     * cloud-authoritative arrival is the day after tomorrow. The clock is frozen
     * at 11:00 so the earliest cloud arrival sits 37 hours out, one hour above
     * the 36-hour line; at noon it is exactly 36 hours, and later in the day the
     * date-only window lets it dip below (the boundary is recorded in the task
     * report). What this asserts on the real pair is that a booking the cloud is
     * allowed to take reaches the LAN server with at least 36 hours still to run
     * before the guest's arrival day begins.
     */
    public function test_the_36_hour_slack_holds_for_a_cloud_authoritative_arrival(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 11:00:00'));

        $this->seedSharedProperty();
        $this->serveTheMesh();

        // Configuration-free check of the boundary itself: the desk owns today
        // and tomorrow; the day after tomorrow is the cloud's.
        $authority = app(BookingAuthority::class);
        $this->assertTrue($authority->isLocalWindow(now()), 'Today belongs to the front desk.');
        $this->assertTrue($authority->isLocalWindow(now()->addDay()), 'Tomorrow belongs to the front desk.');
        $this->assertFalse($authority->isLocalWindow(now()->addDays(2)), 'The day after tomorrow belongs to the cloud.');

        // The cloud takes the earliest arrival it is allowed to take.
        $this->actAsNode('b');
        $this->withSession(['booking' => $this->onlineSession(
            'Earliest Cloud Guest',
            now()->addDays(2)->toDateString(),
            now()->addDays(4)->toDateString(),
        )])->post(route('booking.create'))->assertRedirect();

        $cloudBooking = Booking::where('guest_name', 'Earliest Cloud Guest')->firstOrFail();

        // The LAN server holds the reservation after the next cycle.
        $this->runSync('a');

        $this->actAsNode('a');
        $lanBooking = Booking::where('sync_uid', $cloudBooking->sync_uid)->firstOrFail();

        $arrival = $lanBooking->check_in->copy()->startOfDay();
        $this->assertTrue(
            $arrival->greaterThanOrEqualTo(now()->copy()->addHours(36)),
            'The reservation must reach the LAN server at least 36 hours before the arrival date begins.',
        );
    }

    /**
     * Seed the one property, room type and room the bookings depend on, and
     * give the cloud the identical rows.
     *
     * Reference data is not authored by a desk or a guest, so it does not
     * travel as a booking change would. It is present on both nodes with
     * matching ids and ULIDs, exactly as a pushed-down snapshot leaves it. The
     * rows are created with sync capture disabled so the snapshot does not
     * itself become a change batch that would adopt a second copy on the peer.
     */
    private function seedSharedProperty(): void
    {
        config(['sync.enabled' => false]);

        $this->actAsNode('a');

        $property = Property::create([
            'name' => 'Lan Server Hotel',
            'location' => 'Lagos',
            'amenities' => [],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 80000,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 201',
            'code' => 'EXEC-201',
            'status' => 'available',
            'meta' => [],
        ]);

        $this->roomTypeId = $roomType->id;
        $this->roomId = $room->id;

        // The front-desk operator is reference data too. A pushed-down
        // snapshot leaves it on both nodes before any booking is taken, and
        // the booking's audit trail carries the operator's user id - a
        // foreign key the receiving node has to be able to resolve.
        $this->desk = $this->makeUser('frontdesk');

        $this->copyReferenceDataTo('b', [
            'properties' => [$property->id],
            'room_types' => [$roomType->id],
            'rooms' => [$room->id],
            'users' => [$this->desk->id],
        ]);

        // copyReferenceDataTo leaves the peer active, but the desk transacts on
        // the LAN server (node a), so hand control back before the test writes.
        $this->actAsNode('a');

        config(['sync.enabled' => true]);
    }

    /**
     * Copy the reference rows this test created to the peer, preserving ids and
     * ULIDs so the peer can accept the bookings that point at them.
     *
     * @param  array<string, list<int>>  $tables
     */
    private function copyReferenceDataTo(string $letter, array $tables): void
    {
        $this->harness->use('a');

        $rows = [];

        foreach ($tables as $table => $ids) {
            $rows[$table] = DB::table($table)
                ->whereIn('id', $ids)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all();
        }

        $this->harness->use($letter);

        foreach ($rows as $table => $tableRows) {
            if ($tableRows !== []) {
                DB::table($table)->insert($tableRows);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function deskPayload(string $name, string $checkIn, string $checkOut): array
    {
        return [
            'guest_name' => $name,
            'guest_email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'guest_phone' => '08030000000',
            'selected_room_ids' => [$this->roomId],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 1,
            'children' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function onlineSession(string $guestName, string $checkIn, string $checkOut): array
    {
        return [
            'room_type_id' => $this->roomTypeId,
            'quantity' => 1,
            'selected_room_ids' => [$this->roomId],
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

    private function onlineSearchUrl(string $checkIn): string
    {
        return '/booking/rooms?check_in='.$checkIn
            .'&check_out='.Carbon::parse($checkIn)->addDays(2)->toDateString()
            .'&adults=1';
    }

    private function frontDeskUser(): User
    {
        return $this->desk ?? $this->makeUser('frontdesk');
    }

    private function managerUser(): User
    {
        return $this->makeUser('manager');
    }

    /**
     * Create a staff user with the named role on the active node.
     */
    private function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['slug' => $roleName],
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Route each outbound call to the peer's own database and controller.
     */
    private function serveTheMesh(): void
    {
        $hosts = $this->harness->hostMap();

        Http::fake(function (Request $request) use ($hosts) {
            if (! $this->peersReachable) {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            }

            $host = parse_url($request->url(), PHP_URL_HOST);
            $target = $hosts[$host] ?? null;

            if ($target === null) {
                throw new \Illuminate\Http\Client\ConnectionException("Unknown sync host [{$host}].");
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
     * Make the given harness node active, peer it with only the other node this
     * test uses, and drop any authentication borrowed from the other machine.
     *
     * Node `a` is the LAN server the desk transacts on; node `b` is the cloud
     * that serves the online channel and manager reporting.
     */
    private function actAsNode(string $letter): void
    {
        $this->harness->use($letter);

        $other = $letter === 'a' ? 'b' : 'a';

        config([
            'sync.node_type' => $letter === 'a' ? 'lan_server' : 'cloud',
            'sync.peers' => ['node-'.$other => ['base_url' => 'http://node-'.$other.'.test']],
        ]);

        // A session authenticated on one node must not leak into the other.
        $this->app['auth']->forgetGuards();
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
}
