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
