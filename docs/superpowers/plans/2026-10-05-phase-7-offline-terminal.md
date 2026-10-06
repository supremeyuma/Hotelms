# Phase 7 — Offline Terminal

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 6](2026-10-05-phase-6-tauri-desktop-and-setup-wizard.md) complete.

**Goal:** Make a sale complete with the network physically disconnected, survive an application restart mid-sale, and reconcile afterwards without double-charging.

**Architecture:** The terminal writes to its own SQLite database exactly as it would online. What changes is the surrounding behaviour: every write is captured to `sync_changes` (Phase 2R), a connectivity probe gates the UI's offline banner, and unacknowledged dockets queue locally. Reconciliation is already built (Phase 4R), so this phase is about proving the sale path survives disconnection — not about building new sync machinery.

**Tech Stack:** SQLite, Laravel, the Phase 4R transport, the POS screen from Phase 3.

**Spec:** §9.2 (terminal setup path), §12 Phase 7, §13 (testing strategy), §14 (risks), §16.1 (receipt printing — unresolved).

**Closes:** G30, G40, G41.

---

## Read this before starting

### Gate — receipt printing hardware (blocks Task 6, not the rest)

Spec §16.1: "`PosPrintLog` exists but no print implementation does. Target hardware
is unknown — thermal receipt printers, or reprint from a management screen."

**Decision made: thermal / ESC-POS.** The estate has committed thermal receipt
printers, so this plan builds **B — a local ESC/POS bridge**. Recorded here so a
later reader does not reopen it.

| Approach | Needs | Offline-safe | Verdict |
|---|---|---|---|
| **A — Browser print** | A CSS `@media print` receipt view; the OS print dialog | Yes | **Rejected.** A cashier pressing "print" gets a modal dialog and must select a device on every sale. Not viable behind a bar. |
| **B — Local ESC/POS bridge** | A small local daemon that speaks ESC/POS to a USB/serial thermal printer | Yes, daemon is local | **Adopted.** Silent, fast, correct for a bar. |
| **C — Management-screen reprint** | Nothing | Only when online | Rejected as a primary path: a guest at the bar with no network gets no receipt. Retained as a *fallback* in the bridge. |

The consequence to accept: Task 6 now carries real hardware risk that browser
print did not. It is mitigated by making the bridge **device-agnostic** — the app
emits a print job, and a separate daemon owns every byte written to the printer.
Swapping printer models then touches one daemon, not the POS.

**The Gate that remains inside Task 6:** the printer model and transport
(USB vs serial vs network, ESC/POS vs a vendor SDK). Task 6 Step 1 enumerates
what to capture. If the model is not yet known, build the daemon and the job
interface against the **generic ESC/POS command set** (init, align, bold,
double-height, feed, cut) plus a pluggable page-size profile (58mm / 80mm), which
covers the overwhelming majority of thermal receipt hardware. Do not block on the
exact model.

### What is already built, and must not be rebuilt

- SQLite schema creation (Phase 0).
- Postgres portability and the real parity gate (Phase 1R).
- HLC, `sync_changes`, the policy catalogue, capture and remote application (Phase 2R).
- The Ziggy runtime manifest (prerequisite).
- POS correctness, device registration, stock/report screens (Phase 3).
- Sync node transport, cursors, peer failover, three-process convergence (Phase 4R).
- Booking authority (Phase 5).

This phase composes existing parts. If a task below requires changing a merge
policy, that is a signal the task is in the wrong phase.

---

## File Structure

**Created:**
- `app/Services/Connectivity/ConnectivityMonitor.php` — is this node talking to a peer?
- `app/Services/Connectivity/OfflineGuard.php` — what is permitted while offline.
- `app/Http/Controllers/ClubPos/ConnectivityController.php`
- `resources/js/js/Components/Pos/OfflineBanner.vue` — visible, non-dismissible.
- `resources/views/pos/receipt.blade.php` — the printable receipt.
- `tests/Feature/Offline/OfflineSaleTest.php`
- `tests/Feature/Offline/MidShiftRestartTest.php`
- `tests/Feature/Offline/ReconciliationTest.php`
- `tests/Feature/Offline/OfflineBookingAuthorityTest.php`

**Modified:**
- `app/Services/ClubPosService.php` — queue-and-continue behaviour.
- `app/Http/Controllers/ClubPos/PosDocketsController.php` (or equivalent) — connectivity surface.
- `routes/web.php` or `routes/club.php` — connectivity endpoint.

**Responsibility note:** the offline sale path and its verification. Sync itself
is Phase 4R's.

---

## Task 1: Connectivity monitoring

**Files:**
- Create: `app/Services/Connectivity/ConnectivityMonitor.php`
- Create: `app/Http/Controllers/ClubPos/ConnectivityController.php`

**Interfaces:**
- Consumes: `config('sync.peers')`, the Phase 4R `HttpSyncTransport`.
- Produces: `ConnectivityMonitor::status(): array` and `GET /club/pos/connectivity`.

- [ ] **Step 1: Decide what "offline" means**

Not "the cable is unplugged". A terminal with a live socket to a server that has
stopped running migrations is operationally offline. The useful question is
"did my last sync cycle succeed", which is exactly the signal Phase 4R already
produces.

```php
<?php

namespace App\Services\Connectivity;

use App\Sync\Exceptions\SyncTransportException;
use App\Sync\SyncCycleRunner;

/**
 * Answers "can this terminal currently reach a peer", from evidence rather
 * than from the network stack.
 *
 * A cable being plugged in proves nothing. A terminal that can reach a server
 * which returns 500 is not online, and a sale must not depend on it.
 */
class ConnectivityMonitor
{
    private ?array $lastResult = null;

    private bool $probed = false;

    public function __construct(private readonly SyncCycleRunner $runner)
    {
    }

    /**
     * @return array{online: bool, checked_at: ?string, peers: int, failed: list<string>}
     */
    public function status(bool $probe = false): array
    {
        if (! config('sync.enabled')) {
            // Sync disabled means a single-node deployment. Always available.
            return ['online' => true, 'checked_at' => null, 'peers' => 0, 'failed' => []];
        }

        if ($probe || ! $this->probed) {
            $this->lastResult = $this->probe();
            $this->probed = true;
        }

        $result = $this->lastResult ?? ['peers' => 0, 'failed' => []];

        return [
            'online'     => $result['failed'] === [] && $result['peers'] > 0,
            'checked_at' => $this->lastResult === null ? null : now()->toIso8601String(),
            'peers'      => $result['peers'],
            'failed'     => $result['failed'],
        ];
    }

    private function probe(): array
    {
        try {
            return $this->runner->run();
        } catch (SyncTransportException $e) {
            return ['peers' => 0, 'failed' => [$e->getMessage()]];
        }
    }
}
```

- [ ] **Step 2: Expose it**

```php
Route::get('/club/pos/connectivity', [ConnectivityController::class, 'show'])
    ->middleware(['auth', 'role:frontdesk|md|manager|superuser|staff'])
    ->name('club.pos.connectivity');
```

Role-gated like the rest of the POS surface. This endpoint performs an outbound
probe on demand, which is exactly the sort of thing that should not be
discoverable by an unauthenticated user on a hotel LAN.

- [ ] **Step 3: Surface it in the POS screen**

```vue
<!-- resources/js/js/Components/Pos/OfflineBanner.vue -->
<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'

const status = ref({ online: true, peers: 0, failed: [] })
let timer = null

onMounted(async () => {
  const refresh = async () => {
    try {
      const { data } = await axios.get(route('club.pos.connectivity'), { params: { probe: 1 } })
      status.value = data
    } catch {
      status.value = { online: false, peers: 0, failed: ['unreachable'] }
    }
  }

  await refresh()
  timer = setInterval(refresh, 15000)
})

onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div
    v-if="!status.online"
    role="alert"
    aria-live="assertive"
    class="sticky top-0 z-50 flex items-center gap-3 bg-amber-500 px-4 py-3 text-amber-950"
  >
    <svg class="h-5 w-5 shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
    </svg>
    <div class="text-sm font-semibold">
      Offline — sales are being recorded on this terminal and will sync automatically.
      <span v-if="status.peers > 0" class="font-normal">
        {{ status.failed.length }} of {{ status.peers }} servers unreachable.
      </span>
    </div>
  </div>
</template>
```

The banner is sticky, non-dismissible, and announced via `role="alert"`. A guest
watching a cashier who does not realise they are offline is how a property ends
up with duplicate dockets.

- [ ] **Step 4: Test**

```php
public function test_connectivity_is_reported_as_online_when_sync_is_disabled(): void
{
    config(['sync.enabled' => false]);

    $this->getJson('/club/pos/connectivity')
        ->assertOk()
        ->assertJson(['online' => true]);
}

public function test_connectivity_is_reported_as_offline_when_every_peer_fails(): void
{
    config(['sync.enabled' => true, 'sync.peers' => ['http://unreachable.test']]);
    Http::fake(fn () => throw new ConnectionException('refused'));

    $this->getJson('/club/pos/connectivity?probe=1')
        ->assertOk()
        ->assertJson(['online' => false]);
}

public function test_connectivity_is_reported_as_online_when_any_peer_succeeds(): void
{
    config([
        'sync.enabled' => true,
        'sync.peers'   => ['http://lan.test', 'http://cloud.test'],
    ]);

    Http::fake([
        'lan.test/*'   => fn () => throw new ConnectionException('refused'),
        'cloud.test/*' => Http::response(['accepted' => 0, 'changes' => [], 'cursor' => 0]),
    ]);

    $this->getJson('/club/pos/connectivity?probe=1')
        ->assertOk()
        ->assertJson(['online' => true]);
}

public function test_the_connectivity_endpoint_requires_authentication(): void
{
    $this->getJson('/club/pos/connectivity')->assertUnauthorized();
}
```

Commit: `feat: report terminal connectivity from real sync evidence`

---

## Task 2: The offline sale path

**Files:**
- Modify: `app/Services/ClubPosService.php`

**Interfaces:**
- Consumes: `ConnectivityMonitor`, the Phase 2R `Syncable` capture.
- Produces: a docket that completes whether or not a peer is reachable.

- [ ] **Step 1: Confirm the sale path is already offline-capable**

```powershell
rg -n "function create|function finalize|DB::transaction|DB::beginTransaction" app/Services/ClubPosService.php
rg -n "Syncable" app/Models/PosDocket.php app/Models/PosDocketItem.php app/Models/PosDocketPayment.php
```

If the POS models already use `Syncable`, then a sale writes to the local
database and appends to `sync_changes` in the same transaction, which means it
works offline **by construction**. That is the design working. Verify it rather
than assuming.

- [ ] **Step 2: Make the transaction boundary explicit**

The critical property: the business row and its change-log entry commit together.
If they could diverge, a crash between them loses the change permanently and the
sale never reaches the server.

```php
// inside ClubPosService, replacing the existing create/finalize transaction
DB::transaction(function () use ($payload, $user) {
    $docket = PosDocket::create([...]);

    $docket->items()->createMany($payload['items']);

    $docket->payments()->createMany($payload['payments']);

    $docket->inventory_movements()->createMany($this->movementsFor($docket));

    // Syncable's model event appends to sync_changes inside this transaction,
    // so the change log cannot drift from the data.
    $docket->stampSyncMetadata();

    return $docket;
});
```

If `stampSyncMetadata` is currently called outside the transaction, that is a
correctness bug in the Phase 2R work and belongs there, not here. Fix it where it
is.

- [ ] **Step 3: Do not branch on connectivity inside the sale**

An early version of this design gated stock checks on connectivity. Do not. If the
terminal is offline, stock is whatever the last snapshot said, and the sale must
proceed — that is the entire premise. Branching on connectivity in the write path
creates two code paths, and only one of them gets exercised during an outage.

`ClubPosService` must behave identically online and offline. The only difference
is what the UI tells the user afterwards.

- [ ] **Step 4: Return the acknowledgement the UI needs**

```php
return [
    'docket'          => $docket,
    'queued_for_sync' => ! $this->connectivity->status()['online'],
];
```

The UI shows a "will sync automatically" confirmation instead of pretending the
server has it.

- [ ] **Step 5: Test the offline sale**

```php
<?php

namespace Tests\Feature\Offline;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OfflineSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sale_completes_with_every_peer_unreachable(): void
    {
        config(['sync.enabled' => true, 'sync.peers' => ['http://lan.test']]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $response = $this->actingAs($this->posUser())
            ->postJson('/club/pos/dockets', $this->docketPayload());

        $response->assertCreated();

        $this->assertDatabaseHas('pos_dockets', ['id' => $response->json('docket.id')]);
        $this->assertDatabaseHas('sync_changes', [
            'table_name' => 'pos_dockets',
            'row_id'     => (string) $response->json('docket.id'),
        ]);

        $this->assertTrue($response->json('queued_for_sync'));
    }

    public function test_the_business_row_and_its_change_log_commit_together(): void
    {
        // Force a failure between the two writes and assert neither survives.
        // Use a model event that throws on sync_changes insert.
    }

    public function test_inventory_movements_are_recorded_offline(): void
    {
        config(['sync.enabled' => true]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $before = (float) \App\Models\InventoryStock::where('inventory_item_id', 1)->value('quantity');

        $this->actingAs($this->posUser())->postJson('/club/pos/dockets', $this->docketPayload());

        $after = (float) \App\Models\InventoryStock::where('inventory_item_id', 1)->value('quantity');

        $this->assertLessThan($before, $after);
    }

    public function test_a_sale_produces_identical_local_state_whether_online_or_offline(): void
    {
        // Run the same sale twice against two identical fixtures, one with a
        // reachable peer and one with none, and assert the resulting rows match
        // apart from sync_hlc and sync_origin_node_id.
    }
}
```

That last test is the one that catches the "two code paths" bug. Write it.

Commit: `feat: make the pos sale path identical online and offline`

---

## Task 3: Survive a restart mid-shift

A terminal that loses power during a sale must not lose it.

**Files:**
- Create: `tests/Feature/Offline/MidShiftRestartTest.php`

**Interfaces:**
- Consumes: the local SQLite file.
- Produces: proof that unacknowledged work survives.

- [ ] **Step 1: Prove durability**

```php
public function test_an_unacknowledged_sale_survives_a_restart(): void
{
    config(['sync.enabled' => true, 'sync.peers' => ['http://lan.test']]);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

    $docket = $this->actingAs($this->posUser())
        ->postJson('/club/pos/dockets', $this->docketPayload())
        ->assertCreated()
        ->json('docket');

    // Simulate a restart: drop every in-memory object and re-open the database.
    DB::disconnect();
    DB::reconnect();

    $this->assertDatabaseHas('pos_dockets', ['id' => $docket['id']]);
    $this->assertDatabaseHas('sync_changes', [
        'table_name' => 'pos_dockets',
        'row_id'     => (string) $docket['id'],
    ]);

    // And the peer's cursor has NOT advanced, which is how "not delivered yet"
    // is expressed. There is no `sent` column on sync_changes, and there must
    // not be: a single boolean cannot describe delivery in a mesh. One change is
    // delivered to the LAN server and still pending for the cloud. Delivery is
    // tracked per peer in sync_cursors, which the *receiving* node owns.
    // Column names are the real ones from the create_sync_cursors migration:
    // node_id (this node) + target_node_id (the peer).
    $this->assertSame(
        0,
        (int) DB::table('sync_cursors')
            ->where('node_id', (string) config('sync.node_id'))
            ->where('target_node_id', $lanServerId)
            ->value('last_applied_change_id'),
    );
}

public function test_a_partially_completed_sale_leaves_no_orphan_rows(): void
{
    // Fail the transaction at the payment step; assert no docket, no items,
    // no movements, no change-log entries.
}
```

- [ ] **Step 2: Run the actual test across a process boundary**

The in-process reconnect above is a weak test. For real confidence, spawn a
second PHP process against the same SQLite file after the first has exited. A
transaction that only appears atomic inside one process is not atomic.

- [ ] **Step 3: Check SQLite durability settings**

```php
$this->assertSame(1, (int) DB::selectOne('PRAGMA journal_mode')->journal_mode ?? 1);
```

WAL is required. In rollback-journal mode a power loss can corrupt the database,
and an offline terminal is precisely the machine likely to lose power. Confirm
`journal_mode=WAL` is set in the connection config for the terminal role, and that
`synchronous=FULL` is used for the terminal role even at a throughput cost — a
lost sale is worse than a slow one.

Commit: `test: prove an offline sale survives a mid-shift restart`

---

## Task 4: Reconcile

**Files:**
- Create: `tests/Feature/Offline/ReconciliationTest.php`

**Interfaces:**
- Consumes: Phase 4R's cycle runner, `IdempotentPolicy`.
- Produces: proof that reconciliation produces no duplicates and no double charges.

- [ ] **Step 1: Test the happy reconnect**

```php
public function test_an_offline_sale_reaches_the_server_exactly_once(): void
{
    // Sell offline on the terminal.
    // Bring the server back.
    // Run sync:run.
    // Assert exactly one docket on the server and exactly one applied change.
}

public function test_repeating_the_cycle_does_not_duplicate_the_sale(): void
{
    // Run sync:run five times; assert the docket count is still one.
}

public function test_a_payment_is_not_applied_twice(): void
{
    // A replayed pos_docket_payments change must not create a second
    // pos_docket_payments row or a second accounting entry.
}

public function test_inventory_reconciles_by_movement_not_by_balance(): void
{
    // The terminal and server both sold the same item offline. Assert the
    // balance equals the sum of both movements, not last-write-wins on a
    // balance column. This is the Class 1 versus Class 3 distinction.
}
```

- [ ] **Step 2: Test the hostile case**

```php
public function test_a_terminal_that_sold_more_than_existed_still_converges(): void
{
    // The terminal had a stale snapshot and oversold. Assert the movements
    // reconcile, the balance goes negative, and the conflict is surfaced
    // rather than silently corrected. Negative stock is permitted but flagged.
}
```

Silently correcting an oversale is how a hotel loses money without noticing. The
ledger must show what happened.

- [ ] **Step 3: Test the crash-during-reconcile case**

```php
public function test_a_failure_mid_reconcile_does_not_advance_the_cursor_past_the_failure(): void
{
    // Apply a batch where the third change throws. Assert the cursor stopped
    // before the third, and a re-run applies it exactly once.
}
```

This is the cursor rule from Phase 4R tested at the failure boundary.

Commit: `test: prove offline reconciliation is idempotent and complete`

---

## Task 5: Verify the front-desk half of the Phase 5 cutoff

Spec §12 Phase 7: "Verify the front-desk half of the Phase 5 cutoff against a real
LAN server here."

Phase 5 enforced the partition on both channels but proved the front-desk half
only against configuration, not against a real LAN server.

**Files:**
- Create: `tests/Feature/Offline/OfflineBookingAuthorityTest.php`

**Interfaces:**
- Consumes: `BookingAuthority` (Phase 5), a running LAN server role (Phase 6).
- Produces: proof the partition holds across two real nodes.

- [ ] **Step 1: Set up two real nodes**

Cloud (Postgres) and LAN server (embedded Postgres), both supervised. Terminals
point at the LAN server.

- [ ] **Step 2: Walk the §8 table**

| Guest wants a room for | Front desk may book on LAN? | Online may book? |
|---|---|---|
| Now (walk-in) | Yes | Closed |
| Tomorrow | Yes | Closed |
| Three weeks out | No | Yes |

Assert each cell against the running pair, not against config:

```php
public function test_the_partition_holds_between_a_real_lan_server_and_the_cloud(): void
{
    // Walk-in: created on the LAN server, rejected on the cloud, syncs up.
    // Tomorrow: same.
    // Three weeks out: refused on the LAN server, accepted on the cloud,
    //                  syncs down and is present on the LAN server.
}

public function test_a_walk_in_reaches_manager_reporting_within_sync_latency(): void
{
    // Spec section 8: "it reaches manager reporting within synchronization
    // latency". Walk-in on the LAN server, then assert the reporting screen on
    // the cloud shows it after the next cycle.
}
```

That second test is the one that justifies the design. If a walk-in does not
appear in reporting, the front desk will refuse to use the LAN path and the whole
topology is dead.

- [ ] **Step 3: Verify the 36-hour slack**

Spec §8: the margin "provides 36 hours of slack to notice a failed sync". Test
that a cloud booking for arrival within 36 hours was pushed to the LAN server
with at least 36 hours to spare, so a failed sync is visible before the guest
arrives.

Commit: `test: verify the booking authority partition across real nodes`

---

## Task 6: Thermal receipt printing (ESC/POS bridge)

Approach B, per the decision at the top of this phase.

**Files:**
- Create: `app/Print/PrintJob.php`, `app/Print/EscPosReceiptRenderer.php`, `app/Print/PrintBridgeClient.php`
- Create: `app/Console/Commands/PrintDispatch.php`
- Create: `database/migrations/2026_10_05_100090_create_print_jobs_table.php`
- Create: `desktop/print-bridge/` — the local daemon (see Step 4)
- Modify: `app/Http/Controllers/ClubPos/PosDocketsController.php` (or equivalent)
- Modify: `app/Models/PosPrintLog.php` usage

**Interfaces:**
- Consumes: an existing docket.
- Produces: `PrintJob::createForDocket()`, and a `pos_print_logs` row per print attempt.

### The shape, and why

The app never talks to a printer. It writes a **print job** and hands it to a
local daemon. The daemon owns every byte written to the device.

That boundary is the whole point of the design:

- Swapping printer models, or USB for network, touches the daemon only.
- The POS sale path does not block on printer hardware. A sale must never hang
  because a thermal printer is out of paper.
- The same job table drives an audit trail and a retry queue.

**A sale does not fail because printing failed.** Capture the sale, enqueue the
job, and surface "receipt queued" rather than failing the transaction.

- [ ] **Step 1: Capture the hardware facts**

```powershell
rg -n "class PosPrintLog" -A 25 app/Models/
rg -n "pos_print_logs" database/migrations/
```

`pos_print_logs` exists (Phase 3 audit). Nothing writes it.

Record, before writing the daemon: printer model, transport (USB / serial /
network), paper width (58mm / 80mm), and whether it supports a cut and a
cash-drawer pulse. **If the model is unknown, proceed with generic ESC/POS** —
init, align, bold, double-height, feed, cut — and a pluggable page-size profile.
Do not block on this.

- [ ] **Step 2: Build the job table**

```php
Schema::create('print_jobs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('docket_id')->constrained('pos_dockets')->cascadeOnDelete();
    $table->string('node_id');
    $table->string('status')->default('queued');   // queued | sent | done | failed
    $table->unsignedSmallInteger('attempts')->default(0);
    $table->text('error')->nullable();
    $table->timestamp('printed_at')->nullable();
    $table->timestamps();
    $table->index(['node_id', 'status']);
});
```

`sent` here is correct and `sent` on `sync_changes` is not. A print job has
exactly one destination — the local daemon — so a single status column is an
accurate model. A sync change has one destination *per peer*, which is why
delivery is tracked in `sync_cursors` instead.

- [ ] **Step 3: Render the receipt to ESC/POS, not HTML**

```php
<?php

namespace App\Print;

final class EscPosReceiptRenderer
{
    public function __construct(private readonly int $paperWidth = 32) {}

    /**
     * @return string raw ESC/POS bytes for a thermal printer
     */
    public function render(Docket $docket): string
    {
        $c = $this->paperWidth;

        $out  = "\x1b\x40";                       // ESC @  initialise
        $out .= "\x1b\x61\x01";                   // centre
        $out .= "\x1b\x45\x01";                   // bold on
        $out .= $this->centre($docket->reference);
        $out .= "\x1b\x45\x00";                   // bold off
        $out .= "\x1b\x61\x00";                   // left

        foreach ($docket->items as $item) {
            $out .= $this->column($item->name, $item->qty . ' x ' . $item->price);
        }

        $out .= "\x1b\x61\x01";
        $out .= $this->centre('TOTAL ' . $item->currency . number_format($docket->total(), 2));
        $out .= "\x1b\x61\x00";

        $out .= "\x1d\x56\x00";                   // GS V 0  partial cut, feed first

        return $out;
    }
}
```

Guest name, room, dates, line items, quantities, prices, taxes, service charges
and payment methods all belong on the receipt, using the existing `tax_*` and
`service_charge_*` setting keys.

**Width is a character count, not a millimetre value** — 32 for 58mm paper, 48
for 80mm. `column()` truncates and right-aligns the price; a line that overflows
wraps on most cheap printers and corrupts the receipt on others, so truncate
explicitly rather than trusting the device.

- [ ] **Step 4: Write the local daemon**

The daemon is local-only. It listens on `127.0.0.1`, **not** `0.0.0.0` — it
writes to a physical device on the host and must not be reachable from the LAN.
A printer bridge reachable by every guest device on the network is a real and
embarrassing exposure.

It exposes one endpoint, `POST /print`, accepts raw ESC/POS bytes plus a job id,
writes them to the device, and returns the outcome. It also owns the hardware
retry: a transient USB failure retries three times inside the daemon, so the app
only ever sees a terminal answer.

Confirm before writing it: the language. A Tauri/Rust option already exists in
the codebase from Phase 6 and gives one bundled artifact; a small Python or Node
daemon is faster to write but adds a second runtime to the installer. Either is
acceptable — pick one and record it.

- [ ] **Step 5: Dispatch from the sale path**

`PrintDispatch` drains queued jobs to the daemon. It runs on the POS request's
`terminating` callback, not in the sale transaction.

```php
PosPrintLog::create([
    'docket_id'  => $docket->id,
    'printed_by' => auth()->id(),
    'printed_at' => now(),
    'is_reprint' => false,
    'node_id'    => config('sync.node_id'),
]);
```

Include `node_id`, because in a multi-terminal estate "where was this printed"
is the first question about a missing receipt.

- [ ] **Step 6: Log every print attempt, including failures**

`pos_print_logs` exists to answer "did the guest get a receipt", and reprint
history matters for disputes. Write one row per **attempt**, including reprints
and failures, with the reason where one is given.

A failed print must leave a trace. A missing log row and a missing receipt are
indistinguishable from each other, which is exactly the question the log exists
to answer.

- [ ] **Step 7: Keep reprint as a fallback, not the primary path**

Spec §16.1 offers reprint from a management screen. Keep it — it is cheap, and it
is the recovery path when the daemon is down or the paper jams. But it is not
how a guest gets a receipt at the bar.

- [ ] **Step 8: Verify with the network disconnected**

The receipt must render and print with no network: rendering is local data, and
the daemon is on `127.0.0.1`. Test with the LAN link down, and test with the
printer unplugged to confirm the sale still completes.

Commit: `feat: print thermal receipts through a local escpos bridge`

---

## Task 7: Offline-first UX pass

**Files:**
- Modify: `resources/js/js/Pages/Club/**`

- [ ] **Step 1: Make every POS action work with no connection**

Walk the POS screen with the network disconnected. Every button either works or
says why not. Nothing spins forever, nothing silently fails.

- [ ] **Step 2: Protect submissions against double submission**

Per project standards: a submit button enters a non-repeatable loading state
immediately after the first valid submission and stays disabled until the request
completes or fails. This matters more offline than online, where the round trip
is slow and a cashier's instinct is to press again.

- [ ] **Step 3: Show what is pending**

A cashier needs to see unacknowledged work at shift end.

Pending means "local changes the peer has not yet acknowledged", computed against
that peer's cursor. Do **not** add a `sent` column to `sync_changes`: one change
can be delivered to the LAN server and still be pending for the cloud, so a
single boolean would be wrong on the first day of a mesh.

```php
$pending = DB::table('sync_changes')
    ->where('node_id', (string) config('sync.node_id'))
    ->where('id', '>', (int) (DB::table('sync_cursors')
        ->where('node_id', (string) config('sync.node_id'))
        ->where('target_node_id', $peerId)
        ->value('last_applied_change_id') ?? 0))
    ->count();
```

Surface the same number in `sync:status`. If the cursor row is missing, treat
the baseline as `0`, which makes "no cursor" mean "nothing acknowledged yet" —
the safe reading.

- [ ] **Step 4: Confirm visible loading feedback**

Every navigation and async action on the POS path shows a loader. Per project
standards, based on context: inline loader on the button, page-level skeleton for
navigation.

Commit: `feat: make the pos screen fully usable offline`

---

## Task 8: Final verification

- [ ] **Step 1: The full gate**

```powershell
php artisan migrate:fresh --force
php artisan test
```

- [ ] **Step 2: Verify offline is actually survivable**

Unplug the network. Sell. Restart the application. Sell again. Reconnect. Run
`sync:run`. Assert on the server: both sales present exactly once, stock correct,
no duplicate payments, no lost changes.

- [ ] **Step 3: Verify the terminal never required a server URL**

Set up a terminal from a clean install with no server address entered. It must
complete setup and be ready to sell.

- [ ] **Step 4: Verify a real walk-in reaches reporting**

Sell a walk-in on the LAN server, then check the cloud's reporting screen. This is
the end-to-end proof the whole design rests on.

Commit: `chore: phase 7 verification`

---

## Exit criteria

- A sale completes with every peer unreachable.
- The sale path is identical online and offline; nothing branches on connectivity.
- The docket and its change-log entry commit together.
- An unacknowledged sale survives a process restart, verified across processes.
- Reconciliation is idempotent: repeated cycles produce no duplicates.
- An oversale converges and is surfaced rather than silently corrected.
- The booking partition holds across two real nodes, and a walk-in reaches
  cloud reporting within sync latency.
- Receipts print offline and every print is logged.
- Every POS action works with no connection, and submissions cannot double-fire.

Next: [Phase 8 — cloud deployment](2026-10-05-phase-8-cloud-deployment.md).
