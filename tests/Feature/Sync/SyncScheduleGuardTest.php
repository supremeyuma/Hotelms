<?php

namespace Tests\Feature\Sync;

use App\Jobs\SyncWorker;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase 8 ruling R6 (Option A): there is no cloud push worker. The cloud
 * accepts pushes over the sync API and terminals pull from it, so a node with
 * no outbound peers would iterate an empty peer list every minute and log a
 * no-op cycle.
 *
 * The guard is a `when` filter on the existing schedule entry rather than a
 * conditional registration: the config is read when the scheduler evaluates
 * the event, not once at boot, and `schedule:list` keeps showing the worker on
 * every node so its presence is never mistaken for a missing entry. Laravel's
 * ScheduleRunCommand skips any event whose filters do not pass (it calls
 * `filtersPass` before dispatching), so a filtered-out worker never runs.
 *
 * Terminal and lan_server behaviour is unchanged: they configure peers, the
 * filter passes, and the entry is registered exactly as before.
 */
class SyncScheduleGuardTest extends TestCase
{
    public function test_the_sync_worker_stays_registered_once_with_its_every_minute_cadence(): void
    {
        $event = $this->syncWorkerEvent();

        // The registration itself is untouched - only the filter decides
        // whether the worker actually runs.
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_a_node_without_outbound_peers_filters_the_sync_worker_out(): void
    {
        config(['sync.peers' => []]);

        $event = $this->syncWorkerEvent();

        $this->assertFalse(
            $event->filtersPass($this->app),
            'With zero outbound peers (the cloud role) the scheduled worker must be filtered out.',
        );
    }

    public function test_a_node_with_outbound_peers_keeps_the_sync_worker_running(): void
    {
        config(['sync.peers' => ['node-b' => ['base_url' => 'http://10.0.0.2:8000']]]);

        $event = $this->syncWorkerEvent();

        $this->assertTrue(
            $event->filtersPass($this->app),
            'A terminal or lan server with configured peers must keep its sync cycle.',
        );
    }

    /**
     * The single registered SyncWorker entry. CallbackEvent::getSummaryForDisplay
     * returns the name Schedule::job() stamped on it (the job class).
     */
    private function syncWorkerEvent(): CallbackEvent
    {
        $events = collect(Schedule::events())
            ->filter(fn ($event): bool => $event->getSummaryForDisplay() === SyncWorker::class);

        $this->assertCount(1, $events->all(), 'The SyncWorker must stay registered exactly once.');

        return $events->first();
    }
}
