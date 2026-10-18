<?php

namespace Tests\Feature\Print;

use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosPrintLog;
use App\Models\PosSession;
use App\Models\User;
use App\Print\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrintDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private PosSession $session;

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
    }

    public function test_dispatch_marks_job_done_and_writes_success_log_when_bridge_accepts(): void
    {
        Http::fake(['127.0.0.1:9100/*' => Http::response(['ok' => true], 200)]);

        $docket = $this->makeClosedDocket();
        PrintJob::createForDocket($docket);

        Artisan::call('print:dispatch');

        $this->assertSame(0, PrintJob::where('status', PrintJob::STATUS_QUEUED)->count());

        $job = PrintJob::first();
        $this->assertSame(PrintJob::STATUS_DONE, $job->status);

        $log = PosPrintLog::latest('id')->first();
        $this->assertTrue($log->success);
        $this->assertSame('receipt', $log->type);
        $this->assertSame($docket->id, $log->docket_id);
        $this->assertSame((string) config('sync.node_id'), $log->node_id);
        $this->assertFalse((bool) $log->is_reprint);
    }

    public function test_sale_still_completes_when_bridge_is_down_and_failure_is_logged(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('connection refused');
        });

        $docket = $this->makeClosedDocket();
        PrintJob::createForDocket($docket);

        Artisan::call('print:dispatch');

        $job = PrintJob::first();
        $this->assertSame(PrintJob::STATUS_FAILED, $job->status);

        $log = PosPrintLog::latest('id')->first();
        $this->assertFalse($log->success);
        $this->assertNotSame('', trim((string) $log->reason));
        $this->assertSame($docket->id, $log->docket_id);
    }

    public function test_reprint_queueing_marks_the_log_as_a_reprint(): void
    {
        Http::fake(['127.0.0.1:9100/*' => Http::response(['ok' => true], 200)]);

        $docket = $this->makeClosedDocket();
        PrintJob::createForDocket($docket, isReprint: true);

        Artisan::call('print:dispatch');

        $log = PosPrintLog::latest('id')->first();
        $this->assertTrue((bool) $log->is_reprint);
        $this->assertTrue($log->success);
    }

    public function test_the_reprint_endpoint_queues_a_reprint_job(): void
    {
        Http::fake(['127.0.0.1:9100/*' => Http::response(['ok' => true], 200)]);

        $docket = $this->makeClosedDocket();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.reprint', $docket->id))
            ->assertStatus(202);

        // queueReceipt() defers dispatch via app()->terminating(); flush it.
        $this->app->terminate();
        $this->app->boot();

        $this->assertSame(1, PrintJob::count());
        $this->assertTrue(PrintJob::first()->is_reprint);
    }

    /**
     * The sale path must never touch the printer: pay() only queues the job and
     * defers dispatch to the terminating callback. If pay() ever regresses to
     * awaiting the bridge inline, this test fails on assertNothingSent().
     */
    public function test_pay_queues_receipt_without_awaiting_the_bridge(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('connection refused');
        });

        $docket = $this->makeOpenDocket();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.pay', $docket->id), [
                'payments' => [['method' => 'cash', 'amount' => 1000]],
            ])
            ->assertStatus(200)
            ->assertJsonPath('receipt_queued', true);

        $this->assertSame(1, PrintJob::count());
        Http::assertNothingSent();

        // The sale is already committed before a single byte is printed.
        $this->assertSame('closed', $docket->fresh()->status);

        // Flushing the terminating callback drains the job; with the bridge
        // down the job fails but the failure is recorded, not thrown.
        $this->app->terminate();
        $this->app->boot();

        $this->assertSame(PrintJob::STATUS_FAILED, PrintJob::first()->status);

        $log = PosPrintLog::latest('id')->first();
        $this->assertFalse($log->success);
        $this->assertSame($docket->id, $log->docket_id);
    }

    public function test_reprint_is_refused_for_an_open_docket(): void
    {
        Http::fake(['127.0.0.1:9100/*' => Http::response(['ok' => true], 200)]);

        $docket = $this->makeOpenDocket();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.reprint', $docket->id))
            ->assertStatus(422);

        $this->assertSame(0, PrintJob::count());
    }

    /**
     * Two concurrent drains can both read the same queued list; the atomic
     * claim in PrintDispatch must ensure only the first attempt sends, so a
     * receipt is never printed twice.
     */
    public function test_a_claimed_job_is_not_sent_twice_by_a_second_drain(): void
    {
        Http::fake(['127.0.0.1:9100/*' => Http::response(['ok' => true], 200)]);

        $docket = $this->makeClosedDocket();
        $job = PrintJob::createForDocket($docket);

        // First drain claims and completes the job.
        Artisan::call('print:dispatch');
        $this->assertSame(PrintJob::STATUS_DONE, $job->fresh()->status);
        $this->assertSame(1, $job->fresh()->attempts);
        $this->assertSame(1, PosPrintLog::count());

        // A second drain finds nothing queued: no duplicate send, no second log.
        Artisan::call('print:dispatch');
        $this->assertSame(1, $job->fresh()->attempts);
        $this->assertSame(1, PosPrintLog::count());
        Http::assertSentCount(1);
    }

    /** A claim only succeeds while the job is still queued. */
    public function test_the_claim_is_a_no_op_once_the_job_leaves_queued(): void
    {
        $docket = $this->makeClosedDocket();
        $job = PrintJob::createForDocket($docket);
        $job->update(['status' => PrintJob::STATUS_SENT]);

        // The claim targets `status = queued`; it must affect no rows.
        $affected = PrintJob::whereKey($job->getKey())
            ->where('status', PrintJob::STATUS_QUEUED)
            ->update(['status' => PrintJob::STATUS_SENT]);

        $this->assertSame(0, $affected);
        $this->assertSame(0, $job->fresh()->attempts);
    }

    private function makeOpenDocket(): PosDocket
    {
        $device = PosDevice::factory()->create();

        return PosDocket::create([
            'device_id' => $device->id,
            'session_id' => $this->session->id,
            'staff_id' => $this->staff->id,
            'docket_number' => random_int(1, 100000),
            'status' => 'open',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
    }

    private function makeClosedDocket(): PosDocket
    {
        $device = PosDevice::factory()->create();

        return PosDocket::create([
            'device_id' => $device->id,
            'session_id' => $this->session->id,
            'staff_id' => $this->staff->id,
            'docket_number' => random_int(1, 100000),
            'status' => 'closed',
            'subtotal' => 1000,
            'total' => 1000,
            'closed_at' => now(),
        ]);
    }
}
