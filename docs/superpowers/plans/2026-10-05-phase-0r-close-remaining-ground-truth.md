# Phase 0R — Close the Remaining Ground-Truth Gaps

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the Phase 0 work that was left incomplete — the dead files that are still present, a documentation file that still claims false completeness, and a missing authorization policy that must be specified rather than renamed.

**Architecture:** No new architecture. This phase deletes verified-dead files, corrects one document, and specifies and implements guest-request authorization, which the original Phase 0 plan explicitly deferred.

**Tech Stack:** Laravel 12, PHP 8.2+.

**Spec:** §2.2, §12 Phase 0, §12.2, §16.

**Closes:** G14, G15, G16, G45.

---

## Global Constraints

- Never delete a file without confirming it is unreferenced. Grep first, every time.
- Do not rename `GuestRequestPoicy.php`. It is not a spelling mistake to fix — the policy genuinely does not exist and must be written.
- Documentation corrections must state what is true now, not what was planned.
- Commit after every task.
- Do not modify `.env`.

---

## File Structure

**Deleted:**
- `app/Policies/GuestRequestPoicy.php` — zero bytes, misspelled, unreferenced.
- `app/Services/BookingAccontingService.php` — zero bytes, misspelled, unreferenced.
- `database/migrations/cmb.py` — a 328-line Python development script inside the migrations directory. Not a migration; Laravel ignores it, so it is pure confusion.

**Created:**
- `app/Policies/GuestRequestPolicy.php` — real authorization, specified in Task 3.

**Modified:**
- `REPORTING_COMPLETE.md` — remove the "fully implemented" and "100%" claims.
- `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md` — correct the `cash_shifts` reference and the migration count.

---

## Task 1: Delete the three dead files

**Interfaces:**
- Consumes: nothing.
- Produces: a repository with no zero-byte or misspelled dead code.

- [ ] **Step 1: Confirm all three are unreferenced**

```powershell
rg -n "GuestRequestPoicy|BookingAccontingService|cmb\.py" app/ tests/ database/ config/ routes/ resources/ composer.json
```

Expected: only the files themselves. If `GuestRequestPoicy` appears in a
`Gate::policy` registration, stop — Task 3 covers that case.

- [ ] **Step 2: Confirm the byte counts**

```powershell
python -c "
for p in ['app/Policies/GuestRequestPoicy.php','app/Services/BookingAccontingService.php']:
    print(p, len(open(p,'rb').read()), 'bytes')
import os; print('cmb.py', os.path.getsize('database/migrations/cmb.py'), 'bytes')
"
```

Expected: `0 bytes`, `0 bytes`, and roughly 328 lines for `cmb.py`.

- [ ] **Step 3: Delete them**

```powershell
git rm app/Policies/GuestRequestPoicy.php app/Services/BookingAccontingService.php database/migrations/cmb.py
```

- [ ] **Step 4: Verify nothing broke**

```powershell
php artisan migrate:fresh --force
php artisan test
```

Expected: `166 DONE, 0 FAIL`, 141 passed.
Commit: `chore: remove three verified dead files from the migrations and services trees`

---

## Task 2: Correct the remaining false completion claims

Phase 0 Task 8 corrected `PRODUCTION_DEPLOYMENT_READY.md` and
`DEPLOYMENT_READINESS.md`. `REPORTING_COMPLETE.md` was missed — it still contains
"fully implemented" and "100%".

**Files:**
- Modify: `REPORTING_COMPLETE.md`

**Interfaces:**
- Consumes: nothing.
- Produces: documentation whose stated facts are true.

- [ ] **Step 1: Locate the false claims**

```powershell
rg -n "fully implemented|100%|100 percent|complete" REPORTING_COMPLETE.md
```

- [ ] **Step 2: Replace each with the actual state**

The reporting projectors are implemented and the suite passes, so the honest
claim is narrower than "fully implemented". State what is true: which projectors
exist, that the suite passes today, and that no coverage measurement has been
taken.

```markdown
> **Status note (2026-10-05):** The reporting projectors listed below are
> implemented and the test suite passes. "Fully implemented" and "100% coverage"
> were previously claimed here without a coverage measurement behind them. No
> coverage report has been taken, so no coverage figure is claimed.
```

- [ ] **Step 3: Verify no false claims remain anywhere**

```powershell
rg -n "100% test coverage|fully implemented|APPROVED FOR PRODUCTION" *.md
```

Expected: only the corrections themselves, not the original claims.
Commit: `docs: remove unsupported completion claims from REPORTING_COMPLETE`

---

## Task 3: Specify and implement guest-request authorization

The original Phase 0 plan recorded this correctly: *"No correctly-spelled
`GuestRequestPolicy` exists either, so guest-request authorization is genuinely
absent and must be specified rather than merely renamed."*

Verify the current state before specifying:

```powershell
rg -n "class GuestRequestPoicy|GuestRequestPolicy|Gate::policy" app/Providers/ app/Policies/
```

`GuestRequestController`, `GuestRequestService`, `GuestRequest` and
`GuestRequestCreated` all exist. The controller has no policy backing it.

- [ ] **Step 1: Read the controller and model to establish what access rules are needed**

```powershell
Get-Content app/Http/Controllers/FrontDesk/GuestRequestController.php
Get-Content app/Models/GuestRequest.php
```

- [ ] **Step 2: Ask before assuming the rules**

This is a business authorization decision. Do not invent it. Confirm with the
user:

- Which roles may view guest requests? (Front desk only, or also admin/manager?)
- Which roles may create them?
- Which roles may resolve or close them?
- Can a guest see their own request, or is this staff-only?
- Does a request belong to one booking, or to a property?

Record the answers in a short decision note in this plan's commit message.

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

use App\Models\GuestRequest;
use App\Models\User;

class GuestRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('guest_requests.view');
    }

    public function view(User $user, GuestRequest $request): bool
    {
        return $user->hasPermissionTo('guest_requests.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('guest_requests.create');
    }

    public function update(User $user, GuestRequest $request): bool
    {
        return $user->hasPermissionTo('guest_requests.update');
    }

    public function resolve(User $user, GuestRequest $request): bool
    {
        return $user->hasPermissionTo('guest_requests.resolve');
    }

    public function delete(User $user, GuestRequest $request): bool
    {
        return $user->hasPermissionTo('guest_requests.delete');
    }
}
```

- [ ] **Step 4: Register it and add the permissions**

Register in `app/Providers/AppServiceProvider.php` using whatever registration
pattern the project already uses, then seed the `guest_requests.*` permissions
for each role that Step 2 confirmed. Follow the existing permission seeding
pattern — do not invent a new one.

- [ ] **Step 5: Add the controller authorization calls**

```php
$this->authorize('viewAny', GuestRequest::class);
$this->authorize('create', GuestRequest::class);
$this->authorize('update', $request);
```

- [ ] **Step 6: Write tests for the confirmed rules**

One test per role per action, derived from the Step 2 answers. A test that
asserts the unauthorized case is denied is worth more than one asserting the
authorized case succeeds.

- [ ] **Step 7: Verify**

```powershell
php artisan test --filter=GuestRequest
php artisan test
```

Commit: `feat: add guest request authorization policy and permissions`

---

## Task 4: Correct the spec defects the audit found

Six spec statements do not match the code. Correct the spec rather than working
around the mismatch, so the next reader is not misled.

**Files:**
- Modify: `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md`

**Interfaces:**
- Consumes: the audit findings.
- Produces: a spec whose table and class names exist.

- [ ] **Step 1: Fix the `cash_shifts` reference**

Spec §6 Class 4 says "Two cash shifts claiming one device." No `cash_shifts`
table exists. The real tables are `pos_sessions` and `pos_devices`.

Replace with: "Two `pos_sessions` claiming one `pos_devices` row."

- [ ] **Step 2: Fix the folio table reference**

Spec §7 says "`bookings` and the folio/ledger tables." No table contains `folio`
or `ledger`. The ledger is `charges` + `journal_entries` + `journal_lines`.

Replace with the actual table names.

- [ ] **Step 3: Fix the permissions reference**

Spec §7 says "Authentication and permission tables." No `permissions` table
exists — authorization is `roles` only.

Replace with "`users` and `roles`".

- [ ] **Step 4: Fix the migration count**

Spec §11 and §12 say "the 144 existing migrations" in five places. The count is
now 166.

```powershell
rg -n "144|128 migrations|128 existing" docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md
```

Replace with "166 existing migrations".

- [ ] **Step 5: Resolve the image-extension ambiguity**

Spec §9.1 says "and an image extension" without naming one. This blocks Phase 6
estimation, which the spec itself calls the largest technical risk. Confirm the
choice with the user — GD versus Imagick has real licensing and packaging
consequences on Windows — then name it in the spec.

- [ ] **Step 6: Record the §16 open items as blocking**

Spec §16 leaves two items unresolved that Phase 6 depends on: the embedded
Postgres build and FrankenPHP extension packaging on Windows. Annotate both to
state explicitly that Phase 6 estimation is unreliable until they are answered.

Commit: `docs: correct spec references to tables and migration counts that do not exist`

---

## Task 5: Final verification

- [ ] **Step 1: Run the gate**

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase0r.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
php artisan test
```

Expected: `166 DONE, 0 FAIL` and a green suite with zero skips.

- [ ] **Step 2: Confirm no dead file remains**

```powershell
python -c "
import glob,os
for p in glob.glob('app/**/*.php', recursive=True):
    if os.path.getsize(p) == 0: print('ZERO BYTES:', p)
"
```

Expected: no output.

- [ ] **Step 3: Confirm the scheduler still registers 16 events**

```powershell
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
```

Expected: `16`. Do not parse `schedule:list`; it is truncated and undercounts.

- [ ] **Step 4: Commit any residual work**

Commit: `chore: phase 0r verification`

---

## Exit criteria

- The three dead files are gone and the suite is unchanged at 141 passing.
- `REPORTING_COMPLETE.md` makes no unsupported claim.
- Guest-request authorization exists, is registered, is enforced, and is tested.
- The spec names tables and counts that actually exist.

Next: [Phase 1R — Postgres portability and the parity gate](2026-10-05-phase-1r-postgres-portability-and-parity-gate.md).
