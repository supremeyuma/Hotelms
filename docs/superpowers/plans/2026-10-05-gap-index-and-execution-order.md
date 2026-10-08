# Gap Index and Execution Order

> **Date:** 2026-10-05
> **Status:** Planning. No task in any linked plan has been started.

This document is the entry point for closing every gap between
`docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md` and the
codebase. It enumerates the gaps, points at the plan that owns each one, and
fixes the execution order. Read this first, then work the plans in order.

---

## Verified baseline

Re-measured 2026-10-05 against the working tree at `5326df2d`:

| Fact | Value |
|---|---|
| Migrations applied on SQLite | 165 |
| Test suite | 164 passed, 0 failed, 0 skipped, 675 assertions (141 before Phase 0R added 23 GuestRequest tests) |
| Scheduled events | 16 |
| Sync tables | 4 exist and migrate |
| Sync columns | 15 tables migrated with `sync_hlc`, `sync_origin_node_id`, `sync_deleted_at` |
| Phase plans on disk | 0, 1, 2, 4 |
| Phases with no plan | 3, 5, 6, 7, 8 |
| Desktop runtime | none (no `Cargo.toml`, `src-tauri/`, `tauri.conf.json`) |

---

## Gap register

Every row is a gap verified against code, not inferred. "Owner" is the plan that
closes it.

| # | Gap | Severity | Owner plan |
|---|---|---|---|
| G1 | 9 sync files contain literal `\n` bytes and cannot autoload. **Resolved:** Phase 2R Part A Task 1 rewrote all nine with real newlines; they autoload and pass `php -l`. | Blocker | [Phase 2R Part A](2026-10-05-phase-2r-part-a-repair-schema-policies.md) |
| G2 | Nothing writes `sync_hlc`; no change-log capture exists. **Resolved:** Phase 2R Part B Tasks 2–3 added `SyncLogger::capture` and made `Syncable` stamp the HLC and append one change row per create/update/delete, all guarded so a remote apply is not re-logged. `ChangeLogCaptureTest` covers every capture rule. | Blocker | Phase 2R |
| G3 | `SyncMerger::merge()` is `array_merge`, ignores policy, cannot return conflict. **Resolved:** Phase 2R Part A Task 4 replaced it with catalogue dispatch; the signature now takes the entity key and can return a `ConflictResult` as well as a row. | Blocker | Phase 2R |
| G4 | 6 policy classes do not exist. **Resolved:** Phase 2R Part A Task 1 wrote them and Task 3 wired them into `SyncScope`, with `PolicyCatalogueTest` covering all five classes. | Blocker | Phase 2R |
| G5 | `Syncable` is a 2-method accessor, not a sync trait. **Resolved:** Phase 2R Part B Task 3 built it out: mint `sync_uid`, stamp HLC + origin node, capture create/update/delete, refuse to stamp without node identity or outside the catalogue. | Blocker | Phase 2R |
| G6 | `sync_nodes` lacks `property_id` and `credential` required by §5.4/§4.4. **Resolved:** Phase 2R Part A Task 2 added `property_id` (FK to `properties`, nullOnDelete) and `credential` to `sync_nodes`, with an index on `[property_id, type]`. | High | Phase 2R |
| G7 | `sync_cursors` stores `cursor_hlc`, not `last_applied_change_id` (§5.4). **Resolved:** Phase 2R Part A Task 2 dropped `cursor_hlc` and added nullable `last_applied_change_id` to `sync_cursors`. | High | Phase 2R |
| G8 | `sync_changes` has no per-node monotonic sequence (§5.4). **Resolved:** Phase 2R Part A Task 2 added `sequence` with a unique `[node_id, sequence]` index, plus a `sync_sequences` counter table so allocation does not race on `max()+1`. | High | Phase 2R |
| G9 | ULID row identity (§5.4) incompatible with `row_id unsignedBigInteger`. **Resolved:** Phase 2R Part A Task 2 widened `sync_changes.row_id` and `sync_conflicts.row_id` to string, re-asserting the surrounding indexes because SQLite rebuilds the table. Added a uniform unique `sync_uid` ULID to all 15 synced tables instead of touching their domain keys. | High | Phase 2R |
| G10 | `sync_changes` stores `old_values`/`new_values` (diff), §5.2 requires full snapshot. **Resolved:** Phase 2R Part A Task 2 added a `payload` longText column carrying the full snapshot. `old_values`/`new_values` stay for audit. | High | Phase 2R |
| G11 | `TwoDbSyncTest` opens the same in-memory handle twice. **Resolved:** Phase 2R Part B Task 5 built `TwoNodeSyncHarness` on two real file-backed SQLite databases and rewrote `TwoDbSyncTest` to prove they are separate, that a row crosses, and that an apply is not re-logged. | High | Phase 2R |
| G12 | `SyncEngineTest` only asserts HLC round-trip and an accessor. **Resolved:** Phase 2R Part B Task 6 rewrote it: round trip, monotonic counter, tie-break by node id, higher timestamp wins, and the syncable trait. | High | Phase 2R |
| G13 | `softDeletesTz` for `sync_deleted_at` yields different types per engine | Medium | Phase 1R |
| G14 | Phase 0 Task 4 dead files still present (`GuestRequestPoicy`, `BookingAccontingService`, `cmb.py`) | Medium | [Phase 0R](2026-10-05-phase-0r-close-remaining-ground-truth.md) |
| G15 | Guest-request authorization genuinely absent; needs specification, not a rename | High | Phase 0R |
| G16 | `REPORTING_COMPLETE.md` still claims "fully implemented" and "100%" | Medium | Phase 0R |
| G17 | `SchemaParityTest` asserts `true` and its name is false | Blocker | [Phase 1R](2026-10-05-phase-1r-postgres-portability-and-parity-gate.md) |
| G18 | No migration has been run against Postgres | Blocker | Phase 1R |
| G19 | `2026_04_10_160000_harden_inventory_ledger_tables.php` uses MySQL-only index inspection | High | Phase 1R |
| G20 | `sync:push`/`sync:pull` print "No remote configured; skipping." | Blocker | [Phase 4R](2026-10-05-phase-4r-sync-node-runtime-and-transport.md) |
| G21 | `SyncWorker` is never dispatched; `routes/console.php` has zero sync entries | Blocker | Phase 4R |
| G22 | No HTTP transport, no auth, no peer registry wiring | Blocker | Phase 4R |
| G23 | No Class 3 drink-stock rebuild (§6.2 names both ledgers) | High | Phase 4R |
| G24 | `inventory:rebuild` uses double-quoted string literals inside `DB::raw` SQL | Medium | Phase 4R |
| G25 | No multi-process convergence proof | High | Phase 4R |
| G26 | No sync scope manifest and no tripwire test (§7) | High | [Phase 4S](2026-10-05-phase-4s-sync-scope-manifest.md) |
| G27 | `resources/js/ziggy.js` bakes `http://localhost` (§9.4 prerequisite) | Blocker | [Ziggy](2026-10-05-ziggy-runtime-route-manifest.md) |
| G51 | Three routes have an empty name (`admin.reports.`, `frontdesk.`, `staff.`), so they are unreachable by `route()` name and risk future collisions | Medium | [Ziggy](2026-10-05-ziggy-runtime-route-manifest.md) Task 4 Step 6 |
| G52 | `resources/js/ziggy.js` is tracked in git but imported by nothing; `@routes` in `app.blade.php` is the actual runtime source. Anyone editing the file fixes nothing. | Medium | [Ziggy](2026-10-05-ziggy-runtime-route-manifest.md) |
| G28 | Phase 3 has no plan; §2.3 defects mostly fixed but unverified as a set | High | [Phase 3](2026-10-05-phase-3-club-pos-repair.md) |
| G29 | `PosDevice` has no seeder or registration endpoint | High | Phase 3 |
| G30 | No receipt or print implementation despite `pos_print_logs` (spec §16.1 open item) | Medium | Phase 7 |
| G31 | `BookingAdminController::reassignRoom` writes only the legacy `bookings.room_id`, bypassing the `booking_rooms` pivot every other query reads | High | Phase 5 |
| G32 | 36-hour booking cutoff (§8) absent from `app/`, `config/`, `resources/` | Blocker | [Phase 5](2026-10-05-phase-5-booking-authority-partition.md) |
| G33 | Online booking engine has no authority gate; both channels book freely | Blocker | Phase 5 |
| G34 | Front-desk booking form has no far-out refusal | Blocker | Phase 5 |
| G35 | Cutoff not stored as property-level `settings` config (Class 5) | High | Phase 5 |
| G36 | No staff communication for a business-rule change (§8, §14) | High | Phase 5 |
| G37 | No Tauri application, bundled FrankenPHP, or setup wizard | Blocker | [Phase 6](2026-10-05-phase-6-tauri-desktop-and-setup-wizard.md) |
| G38 | No embedded Postgres for the server role | Blocker | Phase 6 |
| G39 | No UDP discovery or pairing-code flow | High | Phase 6 |
| G40 | No offline snapshot-pull path proven against a LAN server | Blocker | [Phase 7](2026-10-05-phase-7-offline-terminal.md) |
| G41 | No offline shift scenario test (§13) | High | Phase 7 |
| G42 | No Coolify/Postgres cloud deployment with a sync daemon | High | [Phase 8](2026-10-05-phase-8-cloud-deployment.md) |
| G43 | No golden sync fixture (§13) | Medium | Phase 4R |
| G44 | `ClubPosService` calls `BillingService` without importing it | Low | Phase 3 |
| G45 | Spec names `cash_shifts` and folio tables that do not exist | Low | Phase 0R (correct the spec) |
| G46 | `FrontDesk\BookingsController::store` and `update` attach rooms with no availability re-check | Blocker | Phase 5 |
| G47 | `BookingController::cancelBooking` calls an unassigned `$this->service` (fatal) and is unrouted | High | Phase 5 |
| G48 | `BookingService::extendStay` calls `$booking->isEditable()`, which does not exist (fatal) | High | Phase 5 |
| G49 | `bookings.checked_in_at`/`checked_out_at` are written but exist in no migration | High | Phase 5 |
| G50 | `Room::$fillable` lists `room_number`, dropped by `2026_01_08_163319_remove_room_number_from_rooms_table.php`; three live readers remain | Medium | Phase 5 |
| G53 | Two disconnected nodes creating the same business key (e.g. the same inventory `sku`) hit the unique index on the second insert — a `UniqueConstraintViolationException` before sync ever runs. Needs a product decision (upsert-by-key, per-key conflict, or a documented constraint on offline creation), not an implementation guess | High | Phase 4R (decision needed) |
| G54 | `ConflictPolicy::overlappingReservation` (bookings) and `doubleClaimedDevice` (pos_sessions) gate on `sync_row_key`, a column no migration defines. The branches always compare `null` to `null` and bail to `capturedThenRefunded`, so only `payments` (Class 4) can ever surface a conflict. Recorded as intent vs. reachable code | Medium | Phase 4R |
| G55 | Applying a remote `delete` for a row edited locally while disconnected drops the local edit outright; the delete path in `SyncApplier::apply` bypasses the merge policy entirely. A local write then a peer delete is a real offline scenario (e.g. voiding against a live row) and currently has no conflict protection | Medium | Phase 4R |

---

## Spec defects to correct

These are contradictions inside the spec that implementation must resolve one way.
Each needs a decision, recorded in the spec, not a silent workaround.

| Spec reference | Problem | Resolution owner |
|---|---|---|
| §6 Class 4 "two cash shifts claiming one device" | No `cash_shifts` table. The real tables are `pos_sessions` and `pos_devices`. | Phase 0R corrects the spec text; Phase 4R implements against `pos_sessions`. |
| §6 Class 2 "Field-level last-write-wins" | The schema has one `sync_hlc` per **row**, which can only express row-level LWW. True field-level LWW needs a per-field HLC map. | **Resolved: row-level LWW, and §6 is amended in the same commit.** Phase 2R Part A Task 3 Step 4. Accepted risk: two terminals editing different fields of one row offline — the later write wins. |
| §5.4 "Every row retains the ULID" | 9 of the 15 synced domain tables have an auto-increment integer primary key, so a ULID in `sync_changes.row_id` has nothing to point at. The other 6 already use a ULID primary key. | **Resolved:** Phase 2R Part A adds a nullable unique `sync_uid` ULID column to every synced table, backfilled. `row_id` references `sync_uid`, never `id`. Domain primary keys and existing FKs untouched. Adding it to all 15 rather than only the integer 9 is deliberate: it lets `row_id` always mean `sync_uid`, so the applier needs no per-table rule. |
| §7 "folio/ledger tables" | No `folio` or `ledger` table exists. The ledger is `charges` + `journal_entries` + `journal_lines`. | Phase 4S builds the manifest from the real transitive closure. |
| §7 "authentication and permission tables" | Spatie's permission tables exist (created by `2026_04_09_101500_create_permission_support_tables.php`) but are not what authorises anything in practice. Every policy gates on `hasRole`/`hasAnyRole`; a few also accept `hasPermissionTo` as an extra OR. No permission rows are seeded for ordinary roles. | Phase 4S builds the manifest from `users` and `roles`, which is what actually decides access. |
| §11 "the 144 existing migrations" | **165 now** (verified on a fresh SQLite database, cross-checked against `git ls-files`). | Every plan uses the live count. Corrected gates: **166 after Phase 1R**, **172 after Phase 2R**. The earlier 166/167/173 figures were off by one. **Because Phase 1R is deferred (see below), Phase 2R actually lands on 171 — 165 + its own 6 — until Phase 1R runs and adds its 1.** |
| §9.1 required extension list | Image extension was unspecified. | **Resolved 2026-10-05: GD.** It ships inside the PHP runtime, so the Windows packaging surface is smaller than Imagick. Recorded in §9.1 and §16. |
| §9.1 "embedded Postgres build" | No off-the-shelf Windows installer-free embedded Postgres covers all target platforms. | Phase 6 Gate B. Options and costs are laid out. |
| §16 open item 3 | FrankenPHP extension packaging for Windows, specifically `pdo_pgsql`. | Phase 6 Gate C. `scripts/verify-runtime.ps1` is written before any packaging code, so the answer arrives before the work does. |
| §16 open item 1 | Receipt-printing hardware unknown. | **Answered: thermal / ESC-POS.** Phase 7 Gate; generic ESC/POS command set so the exact model does not block the build. |
| §8 "36-hour cutoff" | `bookings.check_in` is cast to `date`, carrying up to a day of imprecision. A literal 36h cutoff evaluated against dates flips the answer near the boundary. | **Resolved: cutoff default widened to 48h**, arrival read at `endOfDay()` for the most inclusive reading. Effective window is 48–72h, reported as a range. Phase 5 Decision 2. Alternative — an `arrival_at` column — was not taken. |
| §8 front-desk authority | The spec's realistic desk handling (walk the guest online, or take a deposit) is a workflow, not code. | Phase 5 ships the refusal plus the hand-off, and documents the provisional-hold flow as out of scope. |
| §16.1 open item 1 — receipt hardware | Unknown target device. | **Resolved: thermal / ESC-POS.** Phase 7 builds a device-agnostic ESC/POS adapter plus per-terminal configuration, not browser print. The concrete command set still needs the printer model — see the Phase 7 Gate. |

---

## Execution order

### Postgres deferral (decided 2026-10-05)

Postgres does not exist anywhere in this design until **Phase 1R Task 1**, which
provisions it with `docker-compose.pgsql.yml`. Everything before that point runs
on SQLite or MySQL only. The machine executing these phases also has no Postgres
available today: Docker Desktop is installed but its daemon is stopped, and no
`psql`, `pg_ctl` or `postgres` binary is on PATH.

So Phase 1R, Phase 6 Gate B (embedded Postgres packaging) and Phase 8 (cloud) are
**deferred to the moment Postgres can be provisioned**, and everything with no
Postgres dependency runs first:

```
NOW, in this order:                    DEFERRED until Postgres exists:
  Phase 2R Part A   (no prereq)          Phase 1R      74/74 refs are Postgres
  Phase 2R Part B   (needs 2R Part A)    Phase 6 Gate B embedded Postgres
  Ziggy             (no prereq)          Phase 8       cloud, 19 refs
  Phase 3           (see caveat)
  Phase 4S -> 4R -> 5
  Phase 6 minus Gate B
  Phase 7
```

Two declared prerequisites are sequencing artifacts rather than technical
dependencies, and are treated as satisfied by Phase 0R: Phase 3 and Phase 7 both
list Phase 1R, but their only Postgres references are a tech-stack line and a
back-reference in a list. Phase 4S, 4R and 5 inherit the same artifact through
their chain back to Phase 3. Flag this again rather than silently ignoring it
when each of those phases is opened.

Consequence for gates: a phase's expected migration count assumes Phase 1R ran
first. Under deferral, subtract 1. Phase 2R Part A therefore lands on **171**, not
172.

### The chain the plans were written against

Dependency chain is not negotiable. Each boundary commits a verified state. The
deferral above governs where execution actually starts; this is the order the
individual plan documents assume.

```
Phase 0R   close remaining ground truth          (G14, G15, G16, G45)
    |
Phase 1R   Postgres portability + real parity gate  (G13, G17, G18, G19)
    |
Phase 2R   repair + complete the sync engine     (G1-G12, G43)  [Part A + Part B]
    |
Ziggy      runtime route manifest                (G27) - blocks every non-cloud node
    |
Phase 3    Club POS repair                       (G28, G29, G44)
    |
Phase 4S   sync scope manifest + tripwire        (G26)
    |
Phase 4R   sync node runtime + transport         (G20-G25)
    |
Phase 5    booking authority partition           (G31-G36, G46-G50)
    |
Phase 6    Tauri app + setup wizard              (G37, G38, G39)
    |
Phase 7    offline terminal                      (G30, G40, G41)
    |
Phase 8    cloud deployment                      (G42)
```

Two ordering notes that matter:

- **Phase 2R precedes everything.** Nine unloadable files and a `array_merge`
  merge function mean no sync test can pass. Fixing this first is what makes
  every later phase verifiable.
- **Ziggy sits before Phase 3.** The spec calls §9.4 a prerequisite, not a
  phase item. It blocks any node other than the cloud from serving the
  application, so it cannot wait for Phase 6.

---

## Verification gate between phases

Every plan ends with the same gate. Do not proceed until it passes:

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_gap_gate.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
php artisan test
```

Expected: `0 FAIL`, then zero failures and zero skips. The DONE count grows by
whatever the phase being verified added, so read it against that phase's own
gate rather than against a fixed number. The Phase 0R-era figures were
`165 DONE` and a suite of 164 tests / 675 assertions; after Phase 2R Part A
it is `171 DONE`, with the suite still 164 / 675.

---

## What is deliberately not planned here

Out of scope per spec §15, and not covered by any plan here: multi-property
partitioning, tenant isolation, per-property users or roles, staff-client local
read caching, migration off Hostinger, offline guest room-service ordering, and
provisional holds for far-out front-desk reservations.
