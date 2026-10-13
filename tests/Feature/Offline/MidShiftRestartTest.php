<?php

namespace Tests\Feature\Offline;

use App\Models\AuditLog;
use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosDocketPayment;
use App\Models\PosSession;
use App\Models\Sync\SyncChange;
use App\Models\User;
use App\Sync\SyncCycleRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Task 3: a terminal that loses power mid-shift must not lose a sale.
 *
 * Two claims are proven here. First, a committed offline sale survives a
 * restart: the business row, its change-log row and the unchanged per-peer
 * cursor are all still there after the connection is dropped and reopened, and
 * even after a genuinely separate PHP process writes to the same file. Second,
 * a crash inside the sale transaction leaves nothing half-written: no payment
 * rows, no stock movements, no change-log entries and no advanced sequence
 * counter, because the atomic write from Task 2 rolls back as a unit.
 *
 * These tests need a file-backed database. `:memory:` is destroyed by a
 * disconnect, so a restart proof on it would be meaningless; two processes
 * cannot share it at all.
 */
class MidShiftRestartTest extends TestCase
{
    private string $dir;

    private string $dbPath;

    private User $staff;

    private PosDevice $device;

    private PosSession $session;

    private MenuItem $item;

    /**
     * While true, the next audit-log write throws. Fails the sale on the last
     * write inside closeDocket, after payments, the close and the stock
     * consumption have all been written in the transaction.
     */
    private bool $failNextAudit = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/hotelms-midshift-'.bin2hex(random_bytes(6));

        if (! mkdir($this->dir, 0777, true) && ! is_dir($this->dir)) {
            throw new \RuntimeException("Could not create midshift directory [{$this->dir}].");
        }

        $this->dbPath = $this->dir.'/terminal.sqlite';
        touch($this->dbPath);

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $this->dbPath]);
        DB::purge('sqlite');

        $this->artisan('migrate:fresh', ['--force' => true]);

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

        DrinkStock::create([
            'menu_item_id' => $this->item->id,
            'full_bottles' => 5,
            'opened_bottles' => 0,
            'pours_per_bottle' => 25,
            'low_stock_threshold' => 3,
            'is_active' => true,
        ]);

        AuditLog::creating(function (): void {
            if ($this->failNextAudit) {
                throw new \RuntimeException('simulated power loss before commit');
            }
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        foreach (glob($this->dbPath.'*') ?: [] as $file) {
            @unlink($file);
        }

        foreach ([$this->dir.'/fixtures.json'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        if (is_dir($this->dir)) {
            @rmdir($this->dir);
        }

        parent::tearDown();
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

    private function fakePeersUnreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
    }

    private function openDocket(): string
    {
        return (string) $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated()
            ->json('id');
    }

    private function addHouseLager(string $docketId): void
    {
        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 2],
        )->assertOk();
    }

    private function pay(string $docketId): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => 2000]]],
        );
    }

    private function assertLanCursorIsZero(): void
    {
        $cursor = (int) DB::table('sync_cursors')
            ->where('node_id', (string) config('sync.node_id'))
            ->where('target_node_id', 'http://lan.test')
            ->where('direction', 'out')
            ->value('last_applied_change_id');

        // Absent row reads as null, cast to 0: the peer has not acknowledged a
        // single change. There is no `sent` column on sync_changes, and there
        // must not be: delivery is tracked per peer in sync_cursors, which the
        // receiving node owns.
        $this->assertSame(0, $cursor);
    }

    public function test_an_unacknowledged_sale_survives_a_restart(): void
    {
        $this->enableSync();
        $this->fakePeersUnreachable();

        $docketId = $this->openDocket();
        $this->addHouseLager($docketId);

        $response = $this->pay($docketId);
        $response->assertOk();
        $this->assertTrue($response->json('queued_for_sync'));

        $docket = PosDocket::findOrFail($docketId);
        $this->assertNotNull($docket->sync_uid);

        // Simulate a restart: drop every in-memory object and re-open the
        // database from the file. What is on disk must still be there.
        DB::disconnect();
        DB::reconnect();

        $this->assertDatabaseHas('pos_dockets', ['id' => $docketId, 'status' => 'closed']);
        $this->assertDatabaseHas('sync_changes', [
            'table_name' => 'pos_dockets',
            'row_id' => $docket->sync_uid,
        ]);

        // The peer's outbound cursor has not advanced, which is how "not
        // delivered yet" is expressed.
        $this->assertLanCursorIsZero();

        // A fresh delivery attempt cannot manufacture acknowledgement either:
        // the push fails at the transport and leaves the change queued.
        app(SyncCycleRunner::class)->push();

        $this->assertLanCursorIsZero();
        $this->assertDatabaseHas('pos_dockets', ['id' => $docketId, 'status' => 'closed']);
        $this->assertDatabaseHas('sync_changes', [
            'table_name' => 'pos_dockets',
            'row_id' => $docket->sync_uid,
        ]);
    }

    /**
     * Step 2 of the brief: run the sale in a genuinely separate PHP process
     * against the same SQLite file, and assert from this process afterwards.
     * An in-process reconnect proves nothing about cross-process atomicity.
     */
    public function test_a_sale_survives_across_a_process_boundary(): void
    {
        $this->enableSync();

        $fixturesPath = $this->dir.'/fixtures.json';
        file_put_contents($fixturesPath, json_encode([
            'device_id' => $this->device->id,
            'session_id' => $this->session->id,
            'staff_id' => $this->staff->id,
            'item_id' => $this->item->id,
        ]));

        // Let the file go before another process writes to it, exactly as a
        // powered-off terminal leaves no process behind.
        DB::disconnect();

        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/Scripts/record_offline_sale.php'),
            '--db='.$this->dbPath,
            '--node-id='.'node-a',
            '--fixtures='.$fixturesPath,
        ]);

        $process->setTimeout(120);
        $process->setEnv([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->dbPath,
        ]);
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

        $sale = json_decode($process->getOutput(), true);

        // Re-open with a fresh connection: this process never wrote the sale.
        DB::reconnect();

        $this->assertDatabaseHas('pos_dockets', ['id' => $sale['docket_id'], 'status' => 'closed']);
        $this->assertDatabaseHas('sync_changes', [
            'table_name' => 'pos_dockets',
            'row_id' => $sale['sync_uid'],
        ]);

        $this->assertLanCursorIsZero();
    }

    /**
     * The half-sale must not exist. The failure is injected at the last write
     * of closeDocket - after payments, the docket close, the session update
     * and the stock consumption have all been written inside the transaction -
     * so a surviving fragment would be visible if the write were not atomic.
     */
    public function test_a_partially_completed_sale_leaves_no_orphan_rows(): void
    {
        $this->enableSync();
        $this->fakePeersUnreachable();

        $docketId = $this->openDocket();
        $this->addHouseLager($docketId);

        $changesBefore = SyncChange::count();
        $paymentsBefore = PosDocketPayment::count();
        $movementsBefore = DrinkStockMovement::count();
        $sequenceBefore = (int) DB::table('sync_sequences')->where('node_id', 'node-a')->value('sequence');
        $stockBefore = (int) DrinkStock::where('menu_item_id', $this->item->id)->value('full_bottles');
        $sessionBefore = $this->session->refresh();

        $this->failNextAudit = true;
        $response = $this->pay($docketId);
        $this->failNextAudit = false;

        $response->assertStatus(422);

        $docket = PosDocket::findOrFail($docketId);
        $this->assertSame('open', $docket->status);
        $this->assertSame(1, $docket->items()->count());

        $this->assertSame($paymentsBefore, PosDocketPayment::count(), 'No orphan payment rows.');
        $this->assertSame($movementsBefore, DrinkStockMovement::count(), 'No orphan stock movements.');
        $this->assertSame($changesBefore, SyncChange::count(), 'No change-log fragments.');
        $this->assertSame($sequenceBefore, (int) DB::table('sync_sequences')->where('node_id', 'node-a')->value('sequence'), 'Sequence bookkeeping rolled back.');
        $this->assertSame($stockBefore, (int) DrinkStock::where('menu_item_id', $this->item->id)->value('full_bottles'), 'Stock was not consumed.');
        $this->assertSame((string) $sessionBefore->total_sales, (string) $this->session->refresh()->total_sales, 'Session totals unchanged.');
    }

    /**
     * Step 3 of the brief: the terminal database must run in WAL with
     * synchronous=FULL. Rollback-journal mode can corrupt the whole file on a
     * power loss, and an offline terminal is precisely the machine likely to
     * lose power. FULL costs throughput; a lost sale costs more.
     */
    public function test_the_terminal_database_runs_in_wal_with_full_synchronous(): void
    {
        $this->assertSame('wal', (string) DB::selectOne('PRAGMA journal_mode')->journal_mode);
        $this->assertSame(2, (int) DB::selectOne('PRAGMA synchronous')->synchronous);
    }
}
