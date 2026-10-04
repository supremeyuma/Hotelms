# Phase 0 — Unblock the Ground Truth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make SQLite able to build the full schema, stop every scheduled job from running twice, restore a trustworthy test suite, remove dead code, and correct documentation that actively misleads — so that no later phase is built on false ground.

**Architecture:** No new architecture. This phase introduces no new feature. It makes two behaviour-restoring fixes — the migration layer so the committed default engine builds, and the scheduler so each job runs once — then removes verified-dead files and corrects documentation.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite 3, PHPUnit 11.5+ via `php artisan test`.

**Spec:** `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md` — see §2.2 (SQLite blocker), §12 (phasing), §12.2 (enumerated doc defects), §13 (testing strategy).

## Global Constraints

- Migration count is **144**. Migrations are timestamp-named (`2026_04_12_000001_create_reporting_events_table.php`), never sequentially numbered.
- Never rewrite an applied migration's **effect**. Make driver-specific paths conditional instead, following the established pattern in `database/migrations/2026_04_10_160000_harden_inventory_ledger_tables.php`.
- All schema changes ship via new migrations. Editing an existing migration to change what it produces is forbidden.
- Commit after every task. Small, focused commits. Never batch unrelated changes.
- Commit messages follow the repo convention: `fix:`, `refactor:`, `chore:`, `docs:`.
- The test suite baseline before this phase is **52 failed, 1 passed, 78 pending**. A task is not complete unless the count improves or the plan says why it cannot.
- Do not modify `.env`. It is untracked local state pointing at a MySQL development database. Use inline environment variables for all SQLite work.
- Counting scheduled tasks: use `php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"`. Do not parse `schedule:list` output — it is dotted, truncated by `Select-Object`, and its regex undercounts.

---

## File Structure

**Modified:**
- `database/migrations/2026_04_12_000001_create_reporting_events_table.php` — make the `fullText` call driver-conditional.
- `routes/web.php` — delete line 39, the stray `require __DIR__ . '/console.php';` that double-registers all 16 scheduled jobs.
- `README.md` — correct 16 false claims (§12.2 of the spec).
- `.github/copilot-instructions.md` — correct 9 false claims.
- `REPORTING_PHASE_2_SUMMARY.md`, `REPORTING_IMPLEMENTATION.md`, `REPORTING_COMPLETE.md`, `REPORTING_PHASE_3_COMPLETE.md` — stop pointing developers at the dead `app/Console/Kernel.php`.
- `PRODUCTION_DEPLOYMENT_READY.md`, `REPORTING_COMPLETE.md`, `DEPLOYMENT_READINESS.md` — withdraw false completion and approval claims.

**Deleted:**
- `app/Policies/GuestRequestPoicy.php` — zero bytes, misspelled, unreferenced.
- `app/Services/BookingAccontingService.php` — zero bytes, misspelled, unreferenced.
- `database/migrations/cmb.py` — 328-line Python dev script inside the migrations directory.
- `app/Console/Kernel.php` — never registered; `routes/console.php` is a strict superset.

**Created:**
- `docs/ARCHITECTURE.md` — the decision record that supersedes the shared-hosting constraints.

**Responsibility note:** this phase produces two runtime changes — one migration fix and one scheduler fix — plus removal of verified-dead files and corrections to documentation. Its output is a repository whose stated facts are true, whose committed default engine actually works, and whose scheduled jobs run once instead of twice.

---

### Task 1: Make `fullText` driver-conditional

This is first because every terminal in the design runs migrations against SQLite, and the test suite runs against in-memory SQLite. Until this passes, nothing else can be verified.

**Files:**
- Modify: `database/migrations/2026_04_12_000001_create_reporting_events_table.php:34`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: a schema where `reporting_events` exists on SQLite, MySQL and Postgres. Later tasks depend on `migrate:fresh` completing.

- [ ] **Step 1: Confirm the failure reproduces**

Run (PowerShell, from repo root):

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase0_probe.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
```

Expected: FAIL at `2026_04_12_000001_create_reporting_events_table` with `This database driver does not support fulltext index creation.`

- [ ] **Step 2: Replace the unconditional `fullText` call**

Verified safe: `rg -n "MATCH\s*\(|fulltext|fullText" app/` returns nothing, so **no application query depends on a fulltext index**. Skipping it on SQLite breaks no code.

Add the `DB` import beside the existing `Schema` import, so the file matches the established pattern in `2026_04_10_160000_harden_inventory_ledger_tables.php:12`:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
```

Capture the driver at the top of `up()`:

```php
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('reporting_events', function (Blueprint $table) {
```

Delete line 34 (`$table->fullText(['event_type', 'domain', 'department']);`) from inside the `Schema::create` closure, then append after the closing `});`:

```php
        // Laravel's schema builder cannot create a fulltext index on SQLite.
        // No query uses MATCH, so nothing depends on it there. Real SQLite
        // fulltext needs an fts5 virtual table plus sync triggers, which is a
        // separate decision and a separate migration - tracked in the spec.
        if ($driver !== 'sqlite') {
            Schema::table('reporting_events', function (Blueprint $table) {
                $table->fullText(['event_type', 'domain', 'department']);
            });
        }
```

**Do not claim an fts5 table exists.** This migration creates none. The comment must say the capability is absent, because a future developer reading "an fts5 table is created elsewhere" would search for it, fail to find it, and lose time.

- [ ] **Step 3: Verify this migration now passes and find the next failure**

Run the Step 1 command again.

Expected: `2026_04_12_000001_create_reporting_events_table ... DONE`, then either a clean finish or the **next** failure. Record the exact migration name and error — Task 2 depends on knowing how many there are.

- [ ] **Step 4: Confirm non-SQLite engines are unaffected**

```powershell
php artisan migrate:fresh --database=mysql --force
```

Expected: all 144 migrations DONE, no errors. If MySQL is unreachable locally, note that and rely on Step 3's SQLite result plus the `!== 'sqlite'` guard being provably the only behavioural change.

- [ ] **Step 5: Clean up and commit**

```powershell
Remove-Item "$env:TEMP\hotelms_phase0_probe.sqlite" -ErrorAction SilentlyContinue
git add database/migrations/2026_04_12_000001_create_reporting_events_table.php
git commit -m "fix: make reporting_events fulltext index conditional for sqlite"
```

---

### Task 2: Drive SQLite migrations to completion

24 migrations have never run on SQLite. Task 1 fixed the known blocker; this task works through whatever remains. **The failure count is unknown until you run it** — that is the point of this task, not a gap in the plan.

**Files:**
- Modify: whichever migrations fail, one commit per migration.

**Interfaces:**
- Consumes: Task 1's driver-conditional `fullText` guard.
- Produces: `migrate:fresh` completing all 144 migrations on SQLite. Every later phase depends on this.

- [ ] **Step 1: Establish the exact remaining failure list**

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase0_probe.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
```

Expected: FAIL at some migration after `2026_04_12_000001`. Write down the migration name and message.

- [ ] **Step 2: Fix one failure, then re-run**

For each failure, make the smallest driver-conditional change that preserves existing MySQL and Postgres behaviour exactly. Re-run Step 1 after every fix to surface the next one.

The 24 migrations that must now run, in order, are:

```
2026_04_12_000002_create_reporting_room_daily_facts_table.php
2026_04_12_000003_create_reporting_department_daily_facts_table.php
2026_04_12_000004_create_reporting_staff_daily_facts_table.php
2026_04_12_000005_create_reporting_booking_facts_table.php
2026_04_12_000006_create_reporting_order_facts_table.php
2026_04_12_000007_create_reporting_laundry_facts_table.php
2026_04_12_000008_create_reporting_maintenance_facts_table.php
2026_04_12_000009_create_reporting_charge_facts_table.php
2026_04_12_000010_create_reporting_payment_facts_table.php
2026_04_12_000011_create_reporting_exceptions_table.php
2026_04_12_000012_create_reporting_metric_definitions_table.php
2026_04_12_145500_align_maintenance_tickets_schema_with_app.php
2026_04_14_131217_create_failed_jobs_table.php
2026_04_14_131217_create_jobs_table.php
2026_05_05_120000_add_kitchen_menu_items.php
2026_06_18_200000_create_pos_devices_table.php
2026_06_18_200001_create_pos_sessions_table.php
2026_06_18_200002_create_pos_dockets_table.php
2026_06_18_200003_create_pos_docket_items_table.php
2026_06_18_200004_create_pos_docket_payments_table.php
2026_06_18_200005_create_drink_stock_table.php
2026_06_18_200006_create_drink_stock_movements_table.php
2026_06_18_200007_create_pos_print_logs_table.php
2026_06_18_200008_create_reporting_pos_facts_table.php
```

**Watch specifically for `->json()` on SQLite.** Laravel maps it to `text`, which is correct, but assertions written against `json` columns can break. **Also watch `2026_06_18_200001_create_pos_sessions_table.php`** — the POS migrations use `foreignUlid('device_id')`, and ULID column definitions and their foreign keys are the most likely remaining SQLite incompatibility.

- [ ] **Step 3: Verify all 144 complete on SQLite**

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase0_probe.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
```

Expected: every line ends in `DONE`, ending with `2026_06_18_200008_create_reporting_pos_facts_table ... DONE`. No `FAIL`.

- [ ] **Step 4: Verify the POS tables actually exist**

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase0_probe.sqlite"
php artisan tinker --execute="echo implode(', ', array_map(fn($r) => $r->name, DB::select(\"select name from sqlite_master where type='table' and name like 'pos_%' order by name\")));"
```

Expected: `pos_devices, pos_docket_items, pos_docket_payments, pos_dockets, pos_print_logs, pos_sessions, reporting_pos_facts`. If this is empty, Step 3 did not actually complete.

- [ ] **Step 5: Confirm MySQL still migrates cleanly**

```powershell
php artisan migrate:fresh --database=mysql --force
```

Expected: all 144 DONE. Any regression here means a Step 2 fix changed MySQL behaviour — revert that fix and redo it conditionally.

- [ ] **Step 6: Commit each fix separately**

```powershell
git add database/migrations/<the-file-you-fixed>.php
git commit -m "fix: make <specific-thing> portable to sqlite"
```

One migration per commit. Do not batch.

---

### Task 3: Get the test suite to a trustworthy state

**Files:**
- Modify: whatever test or application code is genuinely broken — one commit per cause.
- Create: none expected. If a test must be skipped, use `$this->markTestSkipped()` with a reason naming the defect, never a silent skip.

**Interfaces:**
- Consumes: Task 2's complete SQLite schema.
- Produces: a suite whose failures are known and enumerated. Every later phase uses this as its regression gate.

- [ ] **Step 1: Record the new baseline**

```powershell
php artisan test 2>&1 | Select-Object -Last 5
```

Expected: a summary line. Record the exact `Tests: N failed, M passed, P pending`. Compare against the pre-phase baseline of 52 failed, 1 passed, 78 pending.

- [ ] **Step 2: Classify every remaining failure into exactly one bucket**

Run each failing test individually and sort it:

- **Bucket A — schema or migration residue.** A table or column is missing because a migration is still incomplete. Fix the migration. Go back to Task 2.
- **Bucket B — genuine application defect.** The test found a real bug. Fix the bug. **Report these to the user before continuing** — Phase 3 already repairs Club POS and this may widen its scope.
- **Bucket C — the test asserts behaviour that was never built.** The test encodes an aspiration. Rewrite it to assert what exists, or skip it with a reason.

**Do not put a failure in a bucket to make the number go down.** A wrong "fix" here poisons every later phase's verification.

- [ ] **Step 3: Fix Bucket A failures, returning to Task 2 if needed**

- [ ] **Step 4: Fix Bucket B failures only after reporting them**

```powershell
php artisan test --filter=<TestClass> 2>&1 | Select-Object -Last 20
```

Present the list to the user. Do not fix a Bucket B defect silently — it may change scope or reveal a business-rule question, and `AGENTS.md` forbids guessing at business rules.

- [ ] **Step 5: Rewrite or explicitly skip Bucket C tests**

For each, either assert the real current behaviour or:

```php
$this->markTestSkipped('Blocked by <defect reference>. Tracked in docs/superpowers/plans/2026-10-04-phase-0-unblock-ground-truth.md.');
```

A skip must always name the defect it waits on. A skip with no reason is a hidden failure.

- [ ] **Step 6: Re-run and record the final state**

```powershell
php artisan test 2>&1 | Select-Object -Last 5
```

Expected: a summary you can state honestly. Zero failures, or a specific enumerated list of justified skips.

- [ ] **Step 7: Commit**

```powershell
git add tests/
git commit -m "test: resolve baseline failures and document justified skips"
```

---

### Task 4: Remove verified dead files

**Files:**
- Delete: `app/Policies/GuestRequestPoicy.php`
- Delete: `app/Services/BookingAccontingService.php`
- Delete: `database/migrations/cmb.py`

**Interfaces:**
- Consumes: a green-ish suite from Task 3, so the removal is provably harmless.
- Produces: a repository with no zero-byte or misspelled classes.

- [ ] **Step 1: Re-verify all three are unreferenced**

```powershell
rg -n "GuestRequestPoicy|BookingAcconting" --glob '!vendor' --glob '!node_modules' --glob '!docs/**' .
rg -n "cmb" --glob '!vendor' --glob '!node_modules' --glob '!*.lock' --glob '!docs/**' .
```

Expected: no output from either command. If anything matches, **stop** — a file with a live reference is not dead, and the plan's premise is wrong.

- [ ] **Step 2: Delete them**

```powershell
git rm app/Policies/GuestRequestPoicy.php app/Services/BookingAccontingService.php database/migrations/cmb.py
```

- [ ] **Step 3: Record the consequence, do not hide it**

`GuestRequestPoicy.php` was a misspelling of `GuestRequestPolicy`. No correctly-spelled class exists, so **guest-request authorization is genuinely absent from the codebase** — it is not a rename away. Confirm and state this:

```powershell
rg -n "GuestRequestPolicy" --glob '!vendor' --glob '!node_modules' .
```

Expected: no output. Add the finding to the Phase 3 scope in the spec — guest-request authorization needs specifying, not just implementing.

- [ ] **Step 4: Verify the application still boots**

```powershell
php artisan optimize:clear
php artisan route:list 2>&1 | Select-Object -Last 3
php artisan test 2>&1 | Select-Object -Last 5
```

Expected: routes list without error; test totals unchanged from Task 3.

- [ ] **Step 5: Commit**

```powershell
git commit -m "chore: remove zero-byte misspelled classes and stray python script"
```

---

### Task 5: Fix the schedule definition

Two independent defects live in the schedule wiring. Fix the first — it is a live production bug that runs every scheduled job twice — then remove the dead kernel.

**Files:**
- Modify: `routes/web.php:39`
- Delete: `app/Console/Kernel.php`
- Modify: `REPORTING_PHASE_2_SUMMARY.md:184`, `REPORTING_IMPLEMENTATION.md:226`, `REPORTING_COMPLETE.md:104`, `REPORTING_PHASE_3_COMPLETE.md:44`

**Interfaces:**
- Consumes: nothing.
- Produces: exactly one place to add a scheduled task, and one registration per task. Phase 4's sync worker depends on this being unambiguous.

- [ ] **Step 1: Confirm the double registration reproduces**

Count registered schedule events. `schedule:list` output is dotted and truncates, so count events directly:

```powershell
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
```

Expected: **32**. There are only **16** real jobs in `routes/console.php`, so every one is registered twice.

- [ ] **Step 2: Confirm the cause**

`routes/web.php:39` does `require __DIR__ . '/console.php';` in a block with `admin.php`, `auth.php`, `club.php` and the other web route files. `bootstrap/app.php:11` independently registers the same file via `withRouting(commands:)`. Two registrations, one file, sixteen jobs registered twice.

Verify nothing else is responsible:

```powershell
rg -n "console\.php" app/ bootstrap/ routes/ composer.json
```

Expected: three hits — `routes/web.php:39` (the bug), `bootstrap/app.php:11` (correct), `app/Console/Kernel.php:65` (dead, removed in Step 5). Confirm the framework does not auto-load it: `vendor/laravel/framework/src/Illuminate/Foundation/Console/Kernel.php:500-504` only requires paths in `$commandRoutePaths`, which is populated solely by the explicit call in `ApplicationBuilder.php:177`. No default auto-load exists.

- [ ] **Step 3: Remove the stray require**

Delete line 39 from `routes/web.php`:

```php
require __DIR__ . '/console.php';
```

Leave the surrounding `require` lines alone. `routes/console.php` contains a `Schedule` block and an `Artisan::command`, not web routes — it has no business in the web route file.

- [ ] **Step 4: Verify the count halves and nothing is lost**

```powershell
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
```

Expected: **16**. If it is not exactly 16, stop and re-derive the cause — do not proceed on a guess.

- [ ] **Step 5: Confirm nothing registers the dead kernel class**

```powershell
rg -n "App.Console.Kernel|Console.Kernel extends" --glob '!vendor' --glob '!node_modules' --glob '!*.md' .
```

Expected: no output. `tests/TestCase.php` imports `Illuminate\Contracts\Console\Kernel`, which is the framework interface, not this class.

- [ ] **Step 6: Delete the class and re-verify**

```powershell
git rm app/Console/Kernel.php
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
```

Expected: still **16**. If the count moved, the earlier superset analysis was wrong — restore with `git checkout app/Console/Kernel.php` and stop.

- [ ] **Step 7: Correct the four documents that point at the dead class**

Each tells a developer to add schedule entries to `app/Console/Kernel.php`, where the edit would be silently ignored. In each, redirect to `routes/console.php`. For example, `REPORTING_IMPLEMENTATION.md:226` currently reads approximately:

```markdown
Add to `app/Console/Kernel.php`:
```

Replace with:

```markdown
Add to `routes/console.php`:
```

Apply the equivalent change at `REPORTING_PHASE_2_SUMMARY.md:184`, `REPORTING_COMPLETE.md:104` and `REPORTING_PHASE_3_COMPLETE.md:44`. Read each site before editing — the surrounding wording differs.

- [ ] **Step 8: Commit as two focused commits**

The double-registration fix is a behaviour change and the kernel deletion is a refactor. Do not merge them.

```powershell
git add routes/web.php
git commit -m "fix: stop registering console routes twice in the scheduler"

git add app/Console/Kernel.php REPORTING_PHASE_2_SUMMARY.md REPORTING_IMPLEMENTATION.md REPORTING_COMPLETE.md REPORTING_PHASE_3_COMPLETE.md
git commit -m "refactor: remove unregistered console kernel and fix stale doc references"
```

---

### Task 6: Correct README.md

**Files:**
- Modify: `README.md` — 16 sites, listed in §12.2 of the spec.

**Interfaces:**
- Consumes: the verified fact table in spec §12.2.
- Produces: a README that does not assert things that are false.

- [ ] **Step 1: Fix the payment gateway claims (lines 11, 30)**

Replace "Flutterwave, Stripe, and PayPal" with "Flutterwave and Paystack" in both places. Stripe and PayPal exist only as transitive entries in `composer.lock`; no application code references them.

- [ ] **Step 2: Fix the API claims (lines 53, 134-138, 146, 350, 369)**

Delete the "RESTful API Architecture" section at lines 134-138 entirely. At line 53, remove the "RESTful APIs" bullet. At line 146, change "handlers (public, staff, admin, API)" to "handlers (public, guest, staff, admin)". At line 350, change "`api.php` — JSON API routes" to "`api.php` — Payment gateway webhooks (Flutterwave, Paystack)". At line 369, remove "API Design: RESTful endpoints with consistent patterns".

- [ ] **Step 3: Fix the tenancy claim (line 47)**

Replace "Multi-tenant configuration and customization" with "Site-wide configuration and customization". The system is single-property; `app/Services/BookingService.php:102` hard-codes `property_id => 1`.

- [ ] **Step 4: Fix the database claims (lines 65, 105, 162)**

At line 65, replace the SQLite row with an accurate statement: MySQL/MariaDB in production, SQLite for tests and local development, Postgres is the target for the LAN server and cloud. At line 105, change "Database (SQLite/Production DB)" to "Database (MySQL / Postgres / SQLite)". At line 162, remove "SQLite (built-in)" from prerequisites or correct it per Task 2's outcome.

- [ ] **Step 5: Fix the Inertia SSR claim (line 73)**

Replace "Server-driven Vue SSR without page reloads" with "Server-driven Vue navigation without full page reloads". Inertia is not SSR.

- [ ] **Step 6: Fix the false test references (line 214)**

Replace `php artisan test tests/Feature/BookingTest.php` with a path that exists:

```bash
php artisan test tests/Feature/ExampleTest.php
```

- [ ] **Step 7: Fix the licensing claim (line 381) — flag, do not decide**

There is no `LICENSE` file. Do not add one; licensing is an owner decision. Replace the section body with:

```markdown
## 📄 License

No license file is present. All rights reserved unless and until one is added.
```

Tell the user this needs their decision.

- [ ] **Step 8: Fix the coverage and migration claims (lines 394, 395)**

Replace "100% test coverage with PHPUnit" with the real figure from Task 3. Delete "Zero-downtime database migrations".

- [ ] **Step 9: Fix the closing claim (line 397)**

Replace "Perfect for hotels, hospitality chains, or booking platforms" with a statement that does not contradict the single-property design decision.

- [ ] **Step 10: Verify no false claim remains**

```powershell
rg -n "Stripe|PayPal|RESTful|multi-tenant|100% test coverage|Zero-downtime" README.md
```

Expected: no output.

- [ ] **Step 11: Commit**

```powershell
git commit -m "docs: correct false API, tenancy, database and coverage claims in README"
```

---

### Task 7: Correct `.github/copilot-instructions.md`

This file is consumed by AI agents. False statements here cause wrong code to be written, which makes it the highest-value documentation fix in this phase.

**Files:**
- Modify: `.github/copilot-instructions.md` — 9 sites, listed in §12.2 of the spec.

**Interfaces:**
- Consumes: nothing.
- Produces: agent instructions that match the codebase.

- [ ] **Step 1: Delete the claim that a nonexistent directory exists (line 22)**

Delete the line `- `app/Http/Controllers/Api/` - API endpoints (RoomApiController, BookingApiController)` in full. The directory does not exist.

- [ ] **Step 2: Fix the route-layer claims (lines 10, 32, 96)**

Replace "Multi-layer routes (public, staff, admin, API)" with "Multi-layer routes (public, guest, staff, admin) plus a payment webhook file". At lines 32 and 96, replace "API routes (JSON endpoints)" / "API endpoints for mobile/integrations" with "Payment gateway webhooks (Flutterwave, Paystack) — not a general-purpose API".

- [ ] **Step 3: Fix the internal contradiction at line 144**

Line 144 says "no API routes currently" while lines 10, 32 and 96 claim an API layer. Replace line 144 with:

```markdown
- Web routes live in `routes/web.php` and the per-module files it loads. `routes/api.php` contains only payment gateway webhooks — there is no general-purpose JSON API.
```

- [ ] **Step 4: Fix the migration naming contradiction (line 139)**

Line 139 says "Use named migrations (not timestamps)" while line 26 says "timestamped format, not numbered". The truth is that all 144 migrations are timestamp-named. Replace line 139 with:

```markdown
- Use **timestamped** migrations: `2026_04_12_000001_create_reporting_events_table.php`
```

- [ ] **Step 5: Fix the database line (line 7)**

Replace `- **Database**: SQLite (development), configurable via `config/database.php`` with:

```markdown
- **Database**: MySQL/MariaDB in production, SQLite for tests and local development, Postgres targeted for the LAN server and cloud. `config/database.php` defines sqlite, mysql, mariadb, pgsql and sqlsrv.
```

- [ ] **Step 6: Fix the service-config claims (lines 208, 211)**

Replace "default: sync for testing" with the actual `.env` value `redis`. Replace "array driver for testing" with the actual `.env.example` value `log`. Verify both before editing:

```powershell
rg -n "QUEUE_CONNECTION" .env .env.example
rg -n "MAIL_MAILER" .env .env.example
```

- [ ] **Step 7: Fix the stale date (line 231)**

Replace `**Last Updated**: December 2025` with the current date.

- [ ] **Step 8: Verify**

```powershell
rg -n "Controllers/Api|RESTful|not timestamps|multi-tenant" .github/copilot-instructions.md
```

Expected: no output.

- [ ] **Step 9: Commit**

```powershell
git commit -m "docs: correct false claims in copilot instructions"
```

---

### Task 8: Withdraw false completion and approval claims

**Files:**
- Modify: `PRODUCTION_DEPLOYMENT_READY.md`, `REPORTING_COMPLETE.md`, `DEPLOYMENT_READINESS.md`

**Interfaces:**
- Consumes: Task 3's real test numbers.
- Produces: no document asserting readiness that does not exist.

- [ ] **Step 1: Read each claim in context before editing**

`PRODUCTION_DEPLOYMENT_READY.md` asserts "APPROVED FOR PRODUCTION". `REPORTING_COMPLETE.md` asserts "fully implemented, tested". `DEPLOYMENT_READINESS.md:318-319` contains `Approved By: [Manager Name Here]` — an unfilled placeholder in an approval block.

- [ ] **Step 2: Replace approval language with status language**

Do not delete these documents — they contain real checklists. Replace the approval assertions with the actual state:

```markdown
> **Status: not production-approved.** This document is a checklist, not an
> approval record. As of Phase 0 the test suite baseline was
> 52 failed, 1 passed, 78 pending; see
> `docs/superpowers/plans/2026-10-04-phase-0-unblock-ground-truth.md` for the
> corrected baseline.
```

- [ ] **Step 3: Remove the unfilled approval placeholder**

Delete the `Approved By: [Manager Name Here]` line from `DEPLOYMENT_READINESS.md`. An unfilled signature block in an approval document is worse than no block.

- [ ] **Step 4: Commit**

```powershell
git commit -m "docs: withdraw unsupported production approval and completion claims"
```

---

### Task 9: Record the superseded architecture decision

**Files:**
- Create: `docs/ARCHITECTURE.md`

**Interfaces:**
- Consumes: spec §3.1 and §3.
- Produces: the visible replacement for the shared-hosting constraints in `technical layout.txt.txt`, so a future developer reading that file finds the contradiction already resolved rather than re-deriving it.

- [ ] **Step 1: Write the decision record**

Create `docs/ARCHITECTURE.md`:

```markdown
# Architecture Decision Record

## ADR-001: Supersede the shared-hosting-only constraint

**Date:** 2026-10-04
**Status:** Accepted
**Supersedes:** `technical layout.txt.txt` lines 15, 40-47, 54-59

### Context

`technical layout.txt.txt` mandates Hostinger shared hosting and forbids
long-running processes, Docker on production, Redis, and socket-based
broadcasting. `deploy.sh:5` hard-codes a Hostinger path.

The desktop, LAN-server, offline and cloud-sync programme requires an
always-on on-premise server holding authoritative data. That is incompatible
with the above.

### Decision

The production topology changes to a Docker deployment on a VPS managed with
Coolify, with Postgres. The repository was already prepared for this:
`Dockerfile` (multi-stage, 108 lines), `docker/entrypoint.sh:20-37` defining
web, worker and scheduler roles, and `docker/nginx/default.conf:2` naming the
Coolify service.

A dedicated LAN server node runs on operator hardware and synchronizes with
the cloud. POSIX terminals run offline against local SQLite.

Shared-hosting deployment is **not removed**. `deploy.sh` and
`public/.htaccess` remain valid and remain a fallback.

### Consequences

- Long-running queue workers and a scheduler are available, replacing the
  minutely-cron model at `technical layout.txt.txt:40-47`.
- `app/Console/Kernel.php` was dead code — see Phase 0 Task 5. `routes/console.php`
  is the single schedule definition.
- Postgres becomes the server engine; SQLite remains the test and terminal engine.
  See `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md`.

### Full design

`docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md`
```

- [ ] **Step 2: Link it from the superseded file**

Append to `technical layout.txt.txt`:

```markdown
---

> **SUPERSEDED IN PART.** The shared-hosting constraints in this document
> (lines 15, 40-47, 54-59) were superseded on 2026-10-04. See
> `docs/ARCHITECTURE.md` ADR-001 and
> `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md`.
> Do not add features that assume no long-running processes are available.
```

- [ ] **Step 3: Commit**

```powershell
git add docs/ARCHITECTURE.md "technical layout.txt.txt"
git commit -m "docs: record ADR-001 superseding shared-hosting-only constraint"
```

---

### Task 10: Final verification

**Files:**
- No changes. This task verifies only.

**Interfaces:**
- Consumes: every prior task.
- Produces: the evidence that Phase 0 is complete.

- [ ] **Step 1: Confirm SQLite builds the full schema**

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase0_final.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force 2>&1 | Select-Object -Last 3
```

Expected: last line is `2026_06_18_200008_create_reporting_pos_facts_table ... DONE`, no `FAIL`.

- [ ] **Step 2: Confirm MySQL builds the full schema**

```powershell
php artisan migrate:fresh --database=mysql --force 2>&1 | Select-Object -Last 3
```

Expected: all 144 DONE. Do not skip this — Tasks 1 and 2 both touched migrations that production runs.

- [ ] **Step 3: Record the test baseline**

```powershell
php artisan test 2>&1 | Select-Object -Last 5
```

Expected: state the honest number. Compare to 52 failed / 1 passed / 78 pending.

- [ ] **Step 4: Confirm the schedule is correct**

```powershell
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
php artisan schedule:list
```

Expected: **16** events, each job listed once. Phase 0 started at 32; if it is anything other than 16, the double registration is not actually fixed.

- [ ] **Step 5: Confirm no false claims remain**

```powershell
rg -n "Stripe|PayPal|RESTful|multi-tenant|100% test coverage|Controllers/Api" README.md .github/copilot-instructions.md
```

Expected: no output.

- [ ] **Step 6: Confirm the tree is clean and everything is committed**

```powershell
git status --short
```

Expected: no output.

- [ ] **Step 7: Report to the user**

Report: the final test number, any Bucket B defects found in Task 3 that were **not** fixed, the licensing decision needed for `README.md:381`, and the guest-request authorization gap from Task 4. These need owner input and must not be quietly absorbed.

---

## Out of Scope For This Phase

- Migrating to Postgres. That is Phase 1.
- Repairing Club POS. That is Phase 3, and it depends on this phase.
- Building an SQLite `fts5` search table for `reporting_events`. Phase 0 only makes the schema *build*; no query uses `MATCH`, so fulltext on SQLite is a tracked gap, not a silent regression. It needs its own migration and trigger design, and a decision about whether reporting search is even needed on a terminal.
- Any other application behaviour change.
- Adding a `LICENSE` file — an owner decision.
- Implementing guest-request authorization — needs specifying first.
- The remaining AI-generated root documents beyond the ones enumerated in Task 8.