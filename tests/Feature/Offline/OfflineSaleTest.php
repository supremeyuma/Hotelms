<?php

namespace Tests\Feature\Offline;

use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosSession;
use App\Models\Sync\SyncChange;
use App\Models\User;
use App\Services\ClubPosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The offline sale path (Phase 7, Task 2).
 *
 * A sale must complete against the local database whether or not any peer is
 * reachable, and the business rows and their change-log entries must commit
 * together. The write path is the same code online and offline; only the
 * acknowledgement the UI receives differs.
 *
 * Real POS sale shape (adapted from the task brief): a docket is opened via
 * POST /club/pos/dockets, items are added, and the sale completes (closed) via
 * POST /club/pos/dockets/{docket}/pay. There is no single-shot "create sale"
 * endpoint, and the stock path writes drink_stock_movements, not
 * inventory_movements.
 */
class OfflineSaleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private PosSession $session;

    private MenuItem $item;

    /**
     * Whether the faked peers answer. A single stubbed closure is registered
     * once per test and reads this flag at call time. Http::fake() appends to
     * its callback list rather than replacing it, so a per-run fake would leave
     * the first (always-throwing) stub in place and poison later runs.
     */
    private bool $peersReachable = true;

    private bool $connectivityFaked = false;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'club.pos.access', 'slug' => 'club.pos.access', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);

        $device = PosDevice::factory()->create();

        $this->session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
        ]);

        $this->item = MenuItem::factory()->create([
            'name' => 'House Lager',
            'price' => 1000,
            'is_available' => true,
        ]);
    }

    private function enableSync(array $overrides = []): void
    {
        config(array_merge([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
            'sync.node_type' => 'terminal',
            'sync.peers' => ['http://lan.test'],
            'sync.credential' => 'secret',
        ], $overrides));
    }

    private function fakeAllPeersUnreachable(): void
    {
        $this->peersReachable = false;
        $this->fakeConnectivityOnce();
    }

    private function fakeAllPeersReachable(): void
    {
        $this->peersReachable = true;
        $this->fakeConnectivityOnce();
    }

    private function fakeConnectivityOnce(): void
    {
        if ($this->connectivityFaked) {
            return;
        }

        $this->connectivityFaked = true;

        Http::fake(fn () => $this->peersReachable
            ? Http::response([
                'accepted' => 0,
                'changes' => [],
                'cursor' => 0,
                'has_more' => false,
            ], 200)
            : throw new ConnectionException('Connection refused'));
    }

    /**
     * @return array{0: string, 1: \Illuminate\Testing\TestResponse}
     */
    private function completeSale(): array
    {
        $docketId = $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 2],
        )->assertOk();

        $response = $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => 2000]]],
        );

        return [$docketId, $response];
    }

    public function test_a_sale_completes_with_every_peer_unreachable(): void
    {
        $this->enableSync();
        $this->fakeAllPeersUnreachable();

        [$docketId, $response] = $this->completeSale();

        $response->assertOk();
        $this->assertTrue($response->json('queued_for_sync'));

        $docket = PosDocket::findOrFail($docketId);
        $this->assertSame('closed', $docket->status);
        $this->assertNotNull($docket->sync_uid);

        $this->assertDatabaseHas('sync_changes', [
            'table_name' => 'pos_dockets',
            'row_id' => $docket->sync_uid,
        ]);
    }

    public function test_a_sale_with_a_reachable_peer_is_not_marked_queued(): void
    {
        $this->enableSync();
        $this->fakeAllPeersReachable();

        [$docketId, $response] = $this->completeSale();

        $response->assertOk();
        $this->assertFalse($response->json('queued_for_sync'));
        $this->assertSame('closed', PosDocket::findOrFail($docketId)->status);
    }

    /**
     * The change-log entry and the business row must live or die together, or a
     * crash between them loses the sale permanently.
     */
    public function test_the_business_row_and_its_change_log_commit_together(): void
    {
        $this->enableSync();

        SyncChange::creating(function (SyncChange $change): void {
            if ($change->table_name === 'pos_dockets') {
                throw new \RuntimeException('forced change-log failure');
            }
        });

        $docketsBefore = PosDocket::count();

        try {
            app(ClubPosService::class)->openDocket($this->session, $this->staff);
            $this->fail('The forced capture failure should have aborted the docket write.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced change-log failure', $e->getMessage());
        }

        $this->assertSame($docketsBefore, PosDocket::count());
        $this->assertSame(0, SyncChange::count());
    }

    /**
     * An offline sale still consumes local stock and records the movement, and
     * the movement is captured for later delivery.
     */
    public function test_stock_consumed_by_an_offline_sale_is_recorded(): void
    {
        $this->enableSync();
        $this->fakeAllPeersUnreachable();

        DrinkStock::create([
            'menu_item_id' => $this->item->id,
            'full_bottles' => 5,
            'opened_bottles' => 0,
            'pours_per_bottle' => 25,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        $this->completeSale();

        $this->assertSame(3, (int) DrinkStock::where('menu_item_id', $this->item->id)->value('full_bottles'));

        $movement = DrinkStockMovement::where('type', 'sale')->firstOrFail();

        $this->assertDatabaseHas('sync_changes', [
            'table_name' => 'drink_stock_movements',
            'row_id' => $movement->sync_uid,
        ]);
    }

    /**
     * The test that catches a split code path: the same sale run with a
     * reachable peer and with none must produce identical local business state.
     * Only sync bookkeeping columns and row identities may differ.
     */
    public function test_a_sale_produces_identical_local_state_whether_online_or_offline(): void
    {
        $offline = $this->runSaleAndSnapshot(online: false);
        $online = $this->runSaleAndSnapshot(online: true);

        $this->assertSame($offline, $online);
    }

    /**
     * Run one complete sale against a fresh fixture and return only the
     * business-relevant local state, so two runs can be compared directly.
     *
     * @return array<string, mixed>
     */
    private function runSaleAndSnapshot(bool $online): array
    {
        $device = PosDevice::factory()->create();
        $session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
        ]);
        $item = MenuItem::factory()->create([
            'name' => 'House Lager',
            'price' => 1000,
            'is_available' => true,
        ]);

        DrinkStock::create([
            'menu_item_id' => $item->id,
            'full_bottles' => 5,
            'opened_bottles' => 0,
            'pours_per_bottle' => 25,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        $this->enableSync();
        $online ? $this->fakeAllPeersReachable() : $this->fakeAllPeersUnreachable();

        // Each run stands in for a separate HTTP request. The connectivity
        // monitor is held by the singleton ClubPosService, so reset it to get a
        // fresh probe rather than the previous run's cached result.
        $this->app->forgetInstance(ClubPosService::class);

        $docketId = $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $session->id])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $item->id, 'quantity' => 2],
        )->assertOk();

        $this->actingAs($this->staff)->postJson(route('club.pos.dockets.pay', $docketId), [
            'payments' => [['method' => 'cash', 'amount' => 2000]],
        ])->assertOk();

        $docket = PosDocket::findOrFail($docketId);
        $session->refresh();

        return [
            'docket' => [
                'status' => $docket->status,
                'subtotal' => (string) $docket->subtotal,
                'vat' => (string) $docket->vat,
                'service_charge' => (string) $docket->service_charge,
                'total' => (string) $docket->total,
            ],
            'items' => $docket->items()->orderBy('id')->get()->map(fn ($row) => [
                'item_name' => $row->item_name,
                'quantity' => $row->quantity,
                'unit_price' => (string) $row->unit_price,
                'subtotal' => (string) $row->subtotal,
            ])->all(),
            'payments' => $docket->payments()->orderBy('id')->get()->map(fn ($row) => [
                'payment_method' => $row->payment_method,
                'amount' => (string) $row->amount,
            ])->all(),
            'session' => [
                'total_sales' => (string) $session->total_sales,
                'docket_count' => $session->docket_count,
            ],
            'drink_bottles' => (int) DrinkStock::where('menu_item_id', $item->id)->value('full_bottles'),
        ];
    }
}
