# Phase 3 — Club POS Repair

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 0R](2026-10-05-phase-0r-close-remaining-ground-truth.md), [Phase 1R](2026-10-05-phase-1r-postgres-portability-and-parity-gate.md), [Ziggy](2026-10-05-ziggy-runtime-route-manifest.md) complete.

**Goal:** Ship a Club POS that actually works on the web, verified end to end, with the tests it currently lacks.

**Architecture:** No new architecture. Most §2.3 defects turn out to be already fixed; this phase verifies each one, fixes what remains, and closes the two real gaps — no `PosDevice` registration path and no receipt printing.

**Tech Stack:** Laravel 12, PHP 8.2+, Inertia.js, Vue 3, SQLite/MySQL/Postgres.

**Spec:** §2.3 (defects that block POS), §12 Phase 3, §16 open item 1.

**Closes:** G28, G29, G44. (Receipt printing is not in this phase — it is a spec §16.1 open item with unknown target hardware, and lands in Phase 7.)

---

## Implementation status (2026-10-11)

Tasks 1–5 executed. Commits on top of `3a50b3b4`:

- `a71764c1` … `3a50b3b4` — Tasks 1–4 (see git log).
- `c6ef059b` fix: load the club pos dashboard reliably.
- `32c279d8` fix: return inertia responses for club pos docket actions.
- `4f91244f` feat: add club pos stock and reports screens.
- `9a0d90d0` chore: rebuild frontend assets for club pos stock and reports.

Defects found beyond the §2.3 list (all fixed in the commits above):

- `DocketController` was missing `addItem`/`removeItem`/`pay`/`void`; the Vue
  panel called routes that did not exist.
- `PosSession`/`PosDocket` relationship foreign keys were guessed wrong
  (`pos_session_id`/`pos_device_id`) vs. the real `session_id`/`device_id`.
- `menu_items.service_area` is enum `['kitchen','bar']`; club POS filters used
  `'club'` — aligned to `'bar'`.
- `DashboardController` ordered `drink_stock` by a non-existent `quantity`
  column, so the dashboard 500'd on load.
- Docket actions returned plain JSON to the Inertia router → invalid-response
  error modal; now Inertia-aware (G57).
- `resolveDevice(null)` minted a random device per request; now one device per
  browser identity, default device otherwise.

**Task 5 route decision (adapts Step 3).** The staff sidebar already linked
`club.pos.stock.index` and `club.pos.reports.sales` as pages, so those exact
route names now render Inertia pages and the JSON payloads moved to new
`*.data` routes (`club.pos.stock.data`, `club.pos.reports.sales.data`,
`club.pos.reports.trends.data`). `club.pos.reports.stock` stays JSON. Payload
shapes are unchanged. New pages live under `Pages/ClubPos/` and use
`ClubPosLayout`, matching `Dashboard.vue`.

**Deferred:** the sidebar's "Shift History" link (`club.pos.shifts.current`)
still returns JSON; recorded as G56. Browser walkthrough (Task 1 Step 2, Task 5
Step 5, Task 7) not yet performed.

---

## The gap this closes

Spec §2.3 enumerates eight defects. Re-measured against the tree at `5326df2d`,
six are already fixed:

| §2.3 defect | Status now |
|---|---|
| `DashboardController` hard-codes `'openDockets' => []` | Fixed — not present |
| `DashboardController` hard-codes `'currentSession' => null` | Fixed — not present |
| `DashboardController` passes `$device = null` | Fixed — not present |
| `Dashboard.vue` calls the wrong route name | Fixed — no `club.pos.dockets.items.add` reference |
| `getOrCreateSession()` never called | Fixed — `DocketController` calls it |
| `PosDevice` never instantiated | **Open** — used in two controllers, but no seeder or registration endpoint |
| `StockController`, `ReportController` return raw JSON | **Open** — routes exist with no Vue consumer |
| No receipt or print implementation | **Open** — `pos_print_logs` exists, nothing writes to it |

So this phase is mostly verification plus three real pieces of work. Treat the
"fixed" rows as unverified until the walkthrough in Task 1 proves them.

---

## Global Constraints

- **Verify before fixing.** Six defects are already fixed. Confirm each with a
  test rather than rewriting working code.
- A POS that cannot open a shift is not shippable. The session lifecycle is the
  spine; everything else hangs off it.
- No speculative work. If a defect is not reproducible, record that rather than
  refactoring around it.
- Commit after every task.
- Follow the existing Club POS conventions — match `DocketController`'s
  structure, response shapes and authorization style.

---

## File Structure

**Created:**
- `database/seeders/PosDeviceSeeder.php` — registers a device.
- `app/Http/Controllers/ClubPos/ReceiptController.php` — receipt and print path.
- `resources/js/Pages/ClubPos/Stock.vue`, `reports/*.vue` — Vue consumers for the raw-JSON endpoints.
- `tests/Feature/ClubPos/PosSessionLifecycleTest.php`
- `tests/Feature/ClubPos/PosDeviceRegistrationTest.php`
- `tests/Feature/ClubPos/DocketLifecycleTest.php`
- `tests/Feature/ClubPos/StockAndReportsTest.php`
- `tests/Feature/ClubPos/ReceiptTest.php`

**Modified:** whatever Task 1 finds still broken.

**Responsibility note:** a working POS on the web. No offline behaviour, no sync — that is Phase 7.

---

## Task 1: Walk the POS and verify the claimed-fixed defects

**Files:** none.

**Interfaces:**
- Consumes: the existing POS code.
- Produces: a verified defect list. Everything after this task depends on it.

- [x] **Step 1: Confirm each §2.3 row against the code**

```powershell
rg -n "openDockets|currentSession|\$device" app/Http/Controllers/ClubPos/DashboardController.php
rg -n "club.pos.dockets.items.add" resources/js/Pages/ClubPos/
rg -n "getOrCreateSession" app/
```

- [ ] **Step 2: Start the app and walk the POS manually**

Follow the skill `workflow-test`, not a checklist. Actually drive the UI:

1. Log in as a user with `club.pos.access`.
2. Open `/club/pos`. Does the dashboard render dockets and a session?
3. Open a shift. Does a session appear?
4. Create a docket. Add items. Take payment. Close the shift.

Record the first point where it fails, if any. That is the real defect list.

- [x] **Step 3: Fix whatever actually breaks**

One commit per fix. If nothing breaks, say so and commit the verification tests
from Task 2 — a POS with no tests regresses silently.

Commit: `fix: club pos <specific defect found in the walkthrough>` (see the
Implementation status commits; the §2.3 "fixed" rows were verified and the
remaining defects — missing docket endpoints, wrong relationship keys, the
`service_area` mismatch, the room-charge fallback, and the broken dashboard
query — were fixed.)

---

## Task 2: Test the session lifecycle

`getOrCreateSession()` is the spine — no shift, no docket. It has no test.

**Files:**
- Create: `tests/Feature/ClubPos/PosSessionLifecycleTest.php`

**Interfaces:**
- Consumes: `ShiftController`, `ClubPosService::getOrCreateSession()`.
- Produces: a regression guard on the shift lifecycle.

- [x] **Step 1: Write the lifecycle test**

```php
<?php

namespace Tests\Feature\ClubPos;

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create();
        $this->staff->givePermissionTo('club.pos.access');
    }

    public function test_a_shift_can_be_opened(): void
    {
        $device = PosDevice::factory()->create();

        $response = $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.open'), ['device_id' => $device->id]);

        $response->assertOk();

        $this->assertDatabaseHas('pos_sessions', [
            'device_id'     => $device->id,
            'opened_by'     => $this->staff->id,
            'closed_at'     => null,
        ]);
    }

    public function test_opening_a_second_shift_on_one_device_is_refused(): void
    {
        $device = PosDevice::factory()->create();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.open'), ['device_id' => $device->id])
            ->assertOk();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.open'), ['device_id' => $device->id])
            ->assertStatus(422);
    }

    public function test_a_shift_can_be_closed_and_reconciled(): void
    {
        $device = PosDevice::factory()->create();

        $session = PosSession::factory()->create([
            'device_id' => $device->id,
            'opened_by' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.close', $session), [
                'counted_cash' => 0,
            ])
            ->assertOk();

        $this->assertNotNull($session->fresh()->closed_at);
    }

    public function test_the_current_shift_endpoint_returns_the_open_session(): void
    {
        $device = PosDevice::factory()->create();

        $session = PosSession::factory()->create([
            'device_id' => $device->id,
            'opened_by' => $this->staff->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->getJson(route('club.pos.shifts.current'));

        $response->assertOk();
        $this->assertSame($session->id, $response->json('id'));
    }
}
```

Use the real factory names and permission names after inspecting them:

```powershell
rg -n "class PosSessionFactory|class PosDeviceFactory" database/factories/
rg -n "club.pos.access|shifts.open" database/seeders/ app/
```

- [x] **Step 2: Run and fix**

```powershell
php artisan test --filter=PosSessionLifecycleTest
```

If `PosDeviceFactory` or `PosSessionFactory` does not exist, create it first —
`PosDevice` currently has no seeder or factory at all, which is Task 3.

Commit: `test: cover the pos session lifecycle`

Note: the draft above asserts a second shift open is refused (422). The agreed
behaviour is **idempotent 200 returning the same session id**; the shipped test
asserts that instead. Open returns 201.

---

## Task 3: Add `PosDevice` registration

Spec §2.3: "`app/Models/PosDevice.php` exists but is never instantiated; no
seeder, no registration endpoint." Both controllers reference the model but
nothing creates the rows.

**Files:**
- Create: `database/factories/PosDeviceFactory.php`
- Create: `database/seeders/PosDeviceSeeder.php`
- Create: `tests/Feature/ClubPos/PosDeviceRegistrationTest.php`

**Interfaces:**
- Consumes: `PosDevice`, the `pos_devices` table.
- Produces: a real device registration path. Phase 6's setup wizard depends on this.

- [x] **Step 1: Inspect the model and table**

```powershell
Get-Content app/Models/PosDevice.php
Get-Content database/migrations/*create_pos_devices_table.php
```

- [x] **Step 2: Create the factory**

Follow the existing factory conventions in `database/factories/`.

```php
<?php

namespace Database\Factories;

use App\Models\PosDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PosDevice> */
class PosDeviceFactory extends Factory
{
    protected $model = PosDevice::class;

    public function definition(): array
    {
        return [
            'name'       => 'Terminal '.fake()->numerify('#'),
            'identifier' => (string) \Illuminate\Support\Str::ulid(),
            'status'     => 'active',
        ];
    }
}
```

Use the real column names from the migration.

- [x] **Step 3: Create the seeder**

A terminal needs at least one device before a shift can open. Register one in the
demo and production seeders.

- [x] **Step 4: Add a registration path**

Decide with the user whether registration is admin-only via a settings screen, or
automatic on first authenticated POS use. Automatic is fewer moving parts for a
staff member who installs the app; admin-only gives better device inventory
control.

**Decision (agreed): automatic, per browser identity, behind a config flag.**
`config/pos.php` (`devices.auto_register`, default true) registers one device per
unique browser identity on first POS access via a `pos_device_code` cookie, and
falls back to a seeded default device otherwise. The seeder provides the default
demo terminal. No settings screen.

- [x] **Step 5: Test registration**

```php
public function test_a_device_is_registered_on_first_pos_access(): void
{
    $this->assertSame(0, PosDevice::count());

    $this->actingAs($this->staff)->get(route('club.pos.dashboard'))->assertOk();

    $this->assertSame(1, PosDevice::count());
}

public function test_repeat_access_does_not_create_duplicate_devices(): void
{
    $this->actingAs($this->staff)->get(route('club.pos.dashboard'));
    $this->actingAs($this->staff)->get(route('club.pos.dashboard'));

    $this->assertSame(1, PosDevice::count());
}
```

The second test matters: a naive implementation creates a device per request.

- [x] **Step 6: Run**

```powershell
php artisan migrate:fresh --seed --force
php artisan test --filter=PosDeviceRegistrationTest
```

Commit: `feat: register pos devices on first access`

---

## Task 4: Test the docket lifecycle

**Files:**
- Create: `tests/Feature/ClubPos/DocketLifecycleTest.php`

**Interfaces:**
- Consumes: `DocketController`, `ClubPosService`.
- Produces: a regression guard on create, add item, pay, void.

- [x] **Step 1: Cover the whole lifecycle**

```php
<?php

namespace Tests\Feature\ClubPos;

use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private PosSession $session;
    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create();
        $this->staff->givePermissionTo('club.pos.access');

        $device = PosDevice::factory()->create();

        $this->session = PosSession::factory()->create([
            'device_id' => $device->id,
            'opened_by' => $this->staff->id,
        ]);

        $this->item = MenuItem::factory()->create([
            'price'    => 1000,
            'is_active' => true,
        ]);
    }

    public function test_a_docket_can_be_opened(): void
    {
        $response = $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.store'),
            ['session_id' => $this->session->id],
        );

        $response->assertOk();

        $this->assertDatabaseHas('pos_dockets', [
            'session_id' => $this->session->id,
            'status'     => 'open',
        ]);
    }

    public function test_an_item_can_be_added_and_the_subtotal_recalculates(): void
    {
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 2],
        )->assertOk();

        $this->assertDatabaseHas('pos_docket_items', [
            'docket_id'    => $docketId,
            'menu_item_id' => $this->item->id,
            'quantity'     => 2,
        ]);

        $this->assertSame(2000.0, (float) \App\Models\PosDocket::find($docketId)->subtotal);
    }

    public function test_adding_an_item_to_a_closed_docket_is_refused(): void
    {
        $docketId = $this->openDocket();

        \App\Models\PosDocket::find($docketId)->update(['status' => 'paid']);

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 1],
        )->assertStatus(422);
    }

    public function test_a_room_charge_posts_to_the_guest_folio(): void
    {
        $booking = \App\Models\Booking::factory()->create();

        $docketId = $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.store'),
            ['session_id' => $this->session->id, 'booking_id' => $booking->id],
        )->assertOk()->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            [
                'payments' => [[
                    'method' => 'room_charge',
                    'amount' => 2000,
                ]],
            ],
        )->assertOk();

        $this->assertDatabaseHas('charges', ['booking_id' => $booking->id]);
    }

    public function test_a_paid_docket_cannot_be_voided(): void
    {
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => 0]]],
        )->assertOk();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.void', $docketId))
            ->assertStatus(422);
    }

    private function openDocket(): int
    {
        return (int) $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.store'),
            ['session_id' => $this->session->id],
        )->assertOk()->json('id');
    }
}
```

The `room_charge` test is the most important one. Spec §7 names it as the reason
bookings must sync to a terminal: `ClubPosService` posts a `room_charge` sale to
a guest folio, which cannot be evaluated offline without booking data.

Note `ClubPosService` calls `BillingService` via `app(BillingService::class)` at
line 144 without a `use` import. That resolves at runtime through the container
so it works, but add the import for clarity while you are in the file.

- [x] **Step 2: Run and fix**

```powershell
php artisan test --filter=DocketLifecycleTest
```

Commit: `test: cover the pos docket lifecycle including room charge`

Note: drafted against `'paid'`/200, but the shipped model uses the `'closed'`
status and `store` returns **201**; the test asserts the real behaviour. Added
two further guards: Inertia docket actions return a redirect (not JSON), and a
docket item cannot be removed through a different docket's route.

---

## Task 5: Give the stock and report endpoints a UI

Spec §2.3: `StockController` and `ReportController` return raw JSON with no Vue
consumer. The routes exist; nothing renders them.

**Files:**
- Create: `resources/js/Pages/ClubPos/Stock.vue`
- Create: `resources/js/Pages/ClubPos/reports/Sales.vue`, `reports/Trends.vue`
- Modify: `resources/js/Pages/ClubPos/Dashboard.vue` — add navigation
- Create: `tests/Feature/ClubPos/StockAndReportsTest.php`

**Interfaces:**
- Consumes: existing `club.pos.stock.*` and `club.pos.reports.*` routes.
- Produces: a navigable stock and reports surface.

- [x] **Step 1: Confirm the endpoints' response shapes**

```powershell
Get-Content app/Http/Controllers/ClubPos/StockController.php
Get-Content app/Http/Controllers/ClubPos/ReportController.php
```

Build the Vue pages against the actual payloads. Do not reshape the endpoints
to suit a guessed UI. (Confirmed: `stock.index` is a bare array of
`{menu_item, stock, low_stock}`; `reports.sales` accepts a **nullable** date
range and returns 200, not 422. The Step 4 drafts below were corrected to match
the real shapes.)

- [x] **Step 2: Build the pages**

Follow the existing Club POS visual conventions — look at `Dashboard.vue` for
layout, table styling, and how Inertia components are used. The direction is
professional and practical, not experimental.

Per the project's UX rules: summary cards must link to the page containing the
underlying records, and async actions must show a loading state and be protected
against double submission.

- [x] **Step 3: Add navigation from the dashboard**

Adapted: the existing `club.pos.stock.index` / `club.pos.reports.sales` route
names now render the Inertia pages (the staff sidebar already treated them as
pages); the JSON payloads moved to `*.data` routes. Navigation was added to
`Components/ClubPos/TopBar.vue` rather than `Dashboard.vue`, so all POS screens
share it.

- [x] **Step 4: Test the endpoints**

Tests were written against the real payloads (authorization 403/redirect plus
200 shape checks on the `*.data` routes and the page routes). The draft
`assertStatus(422)` for a missing date range was dropped — the endpoint treats
the range as optional.

- [ ] **Step 5: Walk the new pages**

Use `workflow-test`. Add stock, take a stocktake, view sales and trends.
Commit: `feat: add club pos stock and reports screens`

---

## Task 6: Defer receipt printing to Phase 7

Receipt printing is spec §16.1 open item 1: "`PosPrintLog` exists but no print
implementation does. Target hardware is unknown."

It is deliberately **not** implemented here. A print implementation depends on
target hardware, and a POS screen designed against the wrong answer has to be
redone. Spec §16.1 is a question for the business, not a code decision.

**The question has since been answered: thermal / ESC-POS.** Phase 7 owns it as
G30 and builds a local ESC/POS bridge, with the exact printer model and transport
confirmed at that phase's Gate. Deferred here so the POS screen is not built
against a print shape that has to be thrown away.

The one thing this phase must do is make sure the deferral leaves no trap: if
`PosDocketsController` currently calls `window.print()` or renders a print-only
view, remove it, because a half-built browser-print path is harder to delete
later than an absent one.

- [x] **Step 1: Record why it is out of scope for this phase**

Add a line to the Phase 3 commit history or a `docs/` note stating that receipt
printing is deferred to Phase 7 pending the §16.1 hardware answer, so nobody
reads the absence as an oversight.

Recorded: spec §16.1 now states thermal/ESC-POS via a local daemon bridge, owned
by Phase 7, printer model/transport to be captured from the user. This plan's
Task 6 and Phase 7 Task 6 both state the deferral.

- [x] **Step 2: Confirm `pos_print_logs` is ready to be written to**

The table exists and nothing writes it. Check its columns match what Phase 7 will
write § `docket_id`, `printed_by`, `printed_at`, `is_reprint`, `reason`, and a
`node_id` for multi-terminal estates.

```powershell
rg -n -A 20 "create_pos_print_logs|Schema::create\('pos_print_logs'" database/migrations/
```

Confirmed: `docket_id`, `type`, `printer_name`, `content`, `printed_at`,
`success` already present; `printed_by`, `is_reprint`, `reason`, `node_id`
missing.

- [x] **Step 3: Add `node_id` if it is missing**

A property with several terminals cannot answer "where was this receipt printed"
without it. Adding it here, while the table is otherwise untouched, is cheaper
than a migration in Phase 7.

Added `printed_by`, `is_reprint`, `reason` and `node_id` in
`2026_10_11_000001_add_print_audit_fields_to_pos_print_logs.php`; `PosPrintLog`
fillable/casts updated. `node_id` is written by the print path
(`config('sync.node_id')`) independent of whether sync is enabled, so it is not
redundant with the sync-internal `sync_origin_node_id`, which is only stamped
when sync is on.

- [x] **Step 4: Note it in the spec**

Add receipt printing to the spec as Phase 7's scope with the hardware question
still open, rather than leaving it implied as a Phase 3 obligation.

Already present in spec §16.1.

Commit: `chore: defer pos receipt printing to phase 7 pending hardware decision`

---

## Task 7: Final verification

- [ ] **Step 1: Walk the POS end to end one more time**

Use `workflow-test`. Open shift, sell, pay by cash and by room charge, close and
reconcile the shift, view stock and reports.

- [ ] **Step 2: Run the gate**

```powershell
php artisan migrate:fresh --force
php artisan test
```

Expected: zero failures, zero skips.

- [ ] **Step 3: Confirm the §2.3 table is fully closed**

Every row in the spec §2.3 table now has either a passing test or an explicit
note. Record the test name covering each row in the commit message.

Commit: `chore: phase 3 verification`

---

## Exit criteria

- Every §2.3 defect is either fixed and tested, or documented as already fixed
  with a covering test.
- The shift lifecycle, docket lifecycle, room charge, stock and reports all work
  in the browser.
- `PosDevice` rows are created by a real registration path.
- `pos_print_logs` is ready to be written to, with a `node_id`.
- Receipt printing is deliberately deferred to Phase 7, with the reason recorded.
- The suite is green.

Next: [Phase 4S — sync scope manifest](2026-10-05-phase-4s-sync-scope-manifest.md).
