# Phase 5 — Booking Authority Partition

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 4R](2026-10-05-phase-4r-sync-node-runtime-and-transport.md) complete.

**Goal:** Make one component decide which node owns a booking, and make every booking write path obey that decision.

**Architecture:** A single `BookingAuthority` service resolves a booking's owning side from the arrival date and a configurable cutoff. The partition is enforced on both channels, which is what removes the split-authority race: the public online path refuses to sell inside the local window and directs the guest to the front desk, while the front-desk path refuses arrivals beyond the window and hands the guest to the online channel. Both sides also re-check availability before attaching rooms. Because authority is decided before sync sees a change, Phase 4S Class 4 rarely has to escalate a hard conflict.

**Tech Stack:** Laravel 12, PHP 8.2+, Carbon.

**Spec:** §4.1 (authority per node), §4.2 (cutover behaviour), §10 (migration path).

**Closes:** G31, G32, G33, G34, G35, G36, G46, G47, G48, G49, G50.

---

## The gap this closes

There is no authority-cutoff rule anywhere in the codebase. Every time comparison in the
booking domain is day-granularity:

```
BookingController::availableRooms   check_in => 'required|date|after_or_equal:today'
FrontDesk\BookingsController::checkOut   $checkoutDay->lte(now()->startOfDay())
BookingAdminController::checkOut     same day-granularity comparison
NightlyRoomChargeService::finalize   startOfDay comparison
```

The only hour-based rules that exist are unrelated: the 45-minute
`pending_payment` hold (`BookingService.php:116`), room access tokens expiring one
hour after checkout (`Booking.php:121`), and `LateCheckoutChargeService` measuring
hours late.

Worse, the local-authority path does not re-check availability at all.
`FrontDesk\BookingsController::store` (L104) and `update` (L228) validate that
selected rooms exist and share a room type (`resolveSelectedRooms`, L446-475),
then attach them. No overlap check. On a LAN server that is authoritative for
near-term arrivals, the front desk can double-book a room.

---

## Decisions this plan makes

Four choices are not fully determined by the spec. Each is implemented behind a
single service method so it can be changed in one place. **Confirm these before
starting Task 2.**

**1. The cutoff is a settings key, not a `class` column.** Spec §6 Class 5 says
cloud-authoritative configuration. The `settings` table is
`id, key, value, meta` (`2025_12_08_092447_create_settings_table.php`) with no
`class`, `group`, or `category` column, and no `Class 5` convention exists
anywhere in the repository. So the cutoff becomes a settings key,
`booking_local_authority_cutoff_hours`, default `48` (widened from the spec's 36 to absorb date-only imprecision — see Decision 2).

**2. The cutoff is evaluated at date granularity, and widened to 48 hours to
absorb the ambiguity.** `bookings.check_in` is cast to `date`
(`Booking.php:45-51`) — there is no arrival timestamp. This is the decision that
sets the default to `48` rather than `36`, so it is worth being precise about why.

A date-only value carries up to a full day of imprecision. A guest arriving at
23:00 today and a guest arriving at 01:00 tomorrow are both "inside the next day"
in the data, but they are one hour apart in reality. With a literal 36-hour
cutoff evaluated against dates, that imprecision is large enough to flip the
answer: an arrival 40 hours out whose date reads as "tomorrow" would be claimed
by the front desk, and an arrival 30 hours out whose date reads as "tomorrow"
would be refused to the front desk and pushed online — the opposite of the rule's
intent.

So the cutoff default is `48`, and `windowEnd()` evaluates the arrival date at
**end of day** (`endOfDay()`), which is the most inclusive reading available from
date-only data. The result:

| Arrival date | Real distance, worst case | Authority |
|---|---|---|
| today | 0–24h | front desk |
| tomorrow | 24–48h | front desk |
| day after tomorrow | 48–72h | cloud |

The front desk window is therefore **48 to 72 hours** depending on time of day,
never narrower than 48h. That is deliberately wider than the 36 hours §8
imagines, and the consequence is explicit:

- **The front desk refuses arrivals beyond roughly 72 hours** and hands the guest
  to online booking. Under a literal 36-hour rule that band was 36–48h.
- **The online funnel is not harmed.** Online booking accepts anything outside the
  window, so a guest wanting a room in six weeks books online exactly as before.
  Only the 48–72h band moves from "self-service" to "front desk hands off".

This was chosen over adding an `arrival_at` datetime column, which would thread
a new field through the entire public booking form, availability search, and
every booking screen, in exchange for a window that is a business preference
rather than a physical constraint.

Two things follow for the operator, and neither is optional:

- The default lives in `BookingAuthority::DEFAULT_CUTOFF_HOURS` and the setting
  `booking_local_authority_cutoff_hours`, so the window is tunable at runtime if
  the 48–72h band proves wrong in practice.
- `sync:status` and the health output should report the effective window, not
  just the number, so a reader is never misled into thinking the window is
  exactly 48 hours.

If the exact arrival hour is later judged to matter — for example if same-day
turnover planning depends on it — revisit this. The setting is already in place,
so narrowing it later is a value change plus possibly an `arrival_at` column.

**3. The cutoff is enforced on both channels, not online only.** Spec §8 is
explicit: the front desk may book "now" and "tomorrow"; online booking is closed
for both. Beyond the cutoff the partition reverses — online may book, the front
desk may not. Spec §8 gives the reason: if the desk can book three weeks out, a
later online booking of the same room produces exactly the Class 4 conflict the
rule exists to prevent, with the guest already holding a confirmation.

Neither side refuses silently. Both explain the other channel and how to reach it.

**4. The spec's stated escape hatch is not implemented here.** Spec §8 says the
realistic desk handling for a future booking beyond the cutoff is to take the
guest's details and either walk them through online booking or take a deposit and
finalize on the cloud, and names a provisional-hold flow as "a clean later
addition". This plan implements the refusal and the hand-off message, not the
provisional hold. Adding a hold flow means deciding who owns an un-finalized
reservation, which is a new Class 4 row type. Raise it if the desk cannot work
without it.

**5. Walk-ins are unaffected.** Spec §8: a walk-in's arrival date is today, so it
falls inside the front-desk window and the existing LAN path already covers it. No
walk-in code path changes.

---

## File Structure

**Created:**
- `app/Services/Booking/BookingAuthority.php` — the single decision.
- `app/Http/Controllers/Booking/LocalWindowNoticeController.php` — guest-facing notice.
- `app/Exceptions/ArrivalInsideLocalWindow.php`
- `app/Exceptions/ArrivalOutsideLocalWindow.php`
- `resources/views/booking/outside-local-window.blade.php` — front-desk hand-off notice.
- `tests/Feature/Bookings/BookingAuthorityTest.php`
- `tests/Feature/Bookings/FrontDeskAvailabilityRecheckTest.php`

**Modified:**
- `app/Http/Controllers/BookingController.php` — refuse inside the window; fix `cancelBooking`; route `checkAvailability`.
- `app/Http/Controllers/FrontDesk/BookingsController.php` — re-check availability; date-filter room lists.
- `app/Services/RoomAvailabilityService.php` — a reusable single-room overlap assertion.
- `app/Services/BookingService.php` — route room assignment through one place.
- `app/Http/Controllers/Admin/BookingAdminController.php` — fix `reassignRoom` to write the pivot; register routes.
- `database/seeders/SettingsSeeder.php` — seed the cutoff key.
- `routes/public.php`, `routes/admin.php`

**Responsibility note:** decides who owns a booking and makes the booking write
paths obey. Does not change the sync engine.

---

## Task 1: Fix the room-assignment inconsistency

> **Status:** COMPLETE 2026-10-11 (commit `2acf4a83`). `assignRooms()` preserves
> the pivot state (status, check-in/out, rate_override) of retained rooms rather
> than resetting them to `reserved`, so an in-house booking keeps its `active`
> pivot. Verified by `tests/Feature/Bookings/BookingRoomAssignmentTest.php`.

This comes first because authority depends on every node seeing the same room
assignment, and today it does not.

**Files:**
- Modify: `app/Http/Controllers/Admin/BookingAdminController.php`
- Modify: `app/Services/BookingService.php`

**Interfaces:**
- Consumes: `BookingService::updateBooking`, `Booking::rooms()`.
- Produces: `BookingService::assignRooms(Booking $booking, array $roomIds): Booking` as the only writer of `booking_rooms`.

- [x] **Step 1: Confirm the defect**

```powershell
rg -n -A 12 "function reassignRoom" app/Http/Controllers/Admin/BookingAdminController.php
rg -n "'room_id'" app/Services/BookingService.php app/Http/Controllers/FrontDesk/BookingsController.php
```

`reassignRoom` (L315-326) writes only the legacy `bookings.room_id` scalar. It
never touches `booking_rooms`. Every availability query, check-in, and check-out
reads the pivot. So after a manager reassigns a room, `bookings.room_id` and
`booking_rooms` disagree, and the reassignment has no effect on the actual room
the guest occupies.

Both `BookingService::createBooking` (L104) and `FrontDesk\BookingsController::store`
(L174) / `update` (L271) also write `room_id` as `$selectedRooms->first()->id`
alongside the pivot, which is the same duplication in a different shape.

- [x] **Step 2: Write one assignment method**

```php
<?php

namespace App\Services;

/**
 * The only writer of a booking's room assignment.
 *
 * The pivot is authoritative. bookings.room_id is a legacy scalar kept in sync
 * for reporting queries that have not been migrated yet, and must never be the
 * source of truth.
 */
public function assignRooms(Booking $booking, array $roomIds): Booking
{
    $rooms = Room::whereIn('id', $roomIds)->get();

    if ($rooms->count() !== count(array_unique($roomIds))) {
        throw ValidationException::withMessages([
            'selected_room_ids' => 'One or more selected rooms do not exist.',
        ]);
    }

    $roomIds = $rooms->pluck('id')->all();

    // Preserve pivot state that check-in and check-out depend on.
    $existing = $booking->rooms()->pluck('booking_rooms.room_id')->all();
    $retained = array_values(array_intersect($roomIds, $existing));
    $added = array_values(array_diff($roomIds, $existing));
    $removed = array_values(array_diff($existing, $roomIds));

    if ($removed !== []) {
        $booking->rooms()->detach($removed);

        Room::whereIn('id', $removed)
            ->whereIn('status', ['reserved', 'occupied'])
            ->update(['status' => 'available']);
    }

    if ($added !== []) {
        $booking->rooms()->attach(array_fill_keys($added, ['status' => 'reserved']));
    }

    if ($retained !== []) {
        $booking->rooms()->syncWithoutDetaching(
            collect($retained)->mapWithKeys(fn ($id) => [$id => ['status' => 'reserved']])->all(),
        );
    }

    $booking->forceFill([
        'room_id'       => $rooms->first()->id,
        'quantity'      => count($roomIds),
        'room_type_id'  => $rooms->first()->room_type_id,
    ])->save();

    return $booking->refresh();
}
```

- [x] **Step 3: Route every caller through it**

Replace the three duplicated attach sites (`BookingService::createBooking` L128,
`FrontDesk\BookingsController::store` L174, `update` L271) and
`BookingAdminController::reassignRoom` L321 with calls to `assignRooms`.

`updateBooking` (L431-477) already does a careful reallocation preserving pivot
columns. Replace its body with `assignRooms` after its date re-validation
(L416-429), so there is one implementation rather than two.

- [x] **Step 4: Register the unrouted admin methods**

`reassignRoom` and `destroy` exist but have no route:

```php
Route::post('/bookings/{booking}/reassign-room', [BookingAdminController::class, 'reassignRoom'])
    ->name('admin.bookings.reassignRoom');
Route::delete('/bookings/{booking}', [BookingAdminController::class, 'destroy'])
    ->name('admin.bookings.destroy');
```

Inside the existing `auth` + `role:manager|md|superuser` group in `routes/admin.php`.

- [x] **Step 5: Test**

```php
public function test_reassigning_a_room_updates_the_pivot_not_only_the_legacy_scalar(): void
{
    $booking = $this->bookingFixture();
    $newRoom = $this->roomFixture();

    $this->actingAs($this->adminUser())
        ->postJson("/admin/bookings/{$booking->id}/reassign-room", ['room_id' => $newRoom->id])
        ->assertOk();

    $this->assertDatabaseHas('booking_rooms', [
        'booking_id' => $booking->id,
        'room_id'    => $newRoom->id,
    ]);

    $this->assertSame($newRoom->id, $booking->refresh()->room_id);
}

public function test_removing_a_room_releases_it(): void
{
    // booking has rooms A and B; assign only A; B returns to available
}
```

Commit: `fix: make the booking room pivot the single source of assignment truth`

---

## Task 2: Build the authority service

> **Status:** COMPLETE 2026-10-11. Fixed a precedence bug in the plan's
> `localAuthority()` (`(bool) config(...) !== 'cloud'` was always true); the
> implemented version returns `config('sync.node_type') !== 'cloud'`.

**Files:**
- Create: `app/Services/Booking/BookingAuthority.php`
- Create: `app/Exceptions/ArrivalInsideLocalWindow.php`
- Modify: `database/seeders/SettingsSeeder.php`

**Interfaces:**
- Consumes: `Setting::get('booking_local_authority_cutoff_hours', 48)`.
- Produces: `cutoffHours()`, `windowEnd()`, `isLocalWindow(Carbon $checkIn)`, `authorityFor(Carbon $checkIn)`, `effectiveWindowDescription()`.

- [x] **Step 1: Write both exceptions**

```php
<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;

/**
 * Raised when an online attempt targets an arrival the front desk owns.
 */
class ArrivalInsideLocalWindow extends \DomainException
{
    public function __construct(public readonly CarbonInterface $checkIn, public readonly int $cutoffHours)
    {
        parent::__construct("Arrivals within {$cutoffHours} hours are handled by the front desk.");
    }
}
```

```php
<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;

/**
 * Raised when a front-desk attempt targets an arrival the cloud owns.
 */
class ArrivalOutsideLocalWindow extends \DomainException
{
    public function __construct(public readonly CarbonInterface $checkIn, public readonly int $cutoffHours)
    {
        parent::__construct("Arrivals beyond {$cutoffHours} hours are taken on the cloud.");
    }
}
```

Two classes rather than one with a flag: the two directions have different
recipients, different remediation text, and different tests. A boolean on one
exception makes the wrong message easy to render.

- [x] **Step 2: Write the service**

```php
<?php

namespace App\Services\Booking;

use App\Exceptions\ArrivalInsideLocalWindow;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Single authority decision for a booking.
 *
 * Spec section 4.2: arrivals inside the cutoff belong to the LAN/front desk;
 * later arrivals route online. Every booking write path asks this service
 * rather than computing a cutoff of its own.
 */
class BookingAuthority
{
    public const SETTING_KEY = 'booking_local_authority_cutoff_hours';

    public const DEFAULT_CUTOFF_HOURS = 48;

    /**
     * Authority of this node. Read from configuration so one code path serves
     * cloud and LAN deployments.
     */
    public function localAuthority(): bool
    {
        return (bool) config('sync.node_type') !== 'cloud';
    }

    public function cutoffHours(): int
    {
        $hours = (int) Cache::remember(
            'booking_authority_cutoff_hours',
            300,
            fn () => Setting::get(self::SETTING_KEY, self::DEFAULT_CUTOFF_HOURS),
        );

        return $hours > 0 ? $hours : self::DEFAULT_CUTOFF_HOURS;
    }

    /**
     * The latest instant that is still inside the local window.
     *
     * bookings.check_in is cast to date, so the arrival has no time component.
     * End of day is the most inclusive reading available: it claims the whole
     * day for the local channel rather than guessing an hour. See Decision 2.
     *
     * Do NOT startOfDay() this. With cutoff 48 and now = 14:00, that yields
     * 10-08 00:00, which also claims the day after tomorrow (a 48-72h arrival
     * that belongs to the cloud). endOfDay on the arrival side is what makes
     * the comparison correct.
     */
    public function windowEnd(): CarbonInterface
    {
        return now()->addHours($this->cutoffHours());
    }

    public function isLocalWindow(CarbonInterface $checkIn): bool
    {
        return $checkIn->copy()->endOfDay()->lte($this->windowEnd());
    }

    /**
     * A human-readable description of the effective window.
     *
     * The window is 48-72h with the default cutoff, not 48h exactly, because
     * the arrival date has no time. Operators reading sync:status or the health
     * output need the range, not the setting value.
     */
    public function effectiveWindowDescription(): string
    {
        return $this->cutoffHours() . '-' . ($this->cutoffHours() + 24) . ' hours (date-only check_in)';
    }

    public function authorityFor(CarbonInterface $checkIn): string
    {
        return $this->isLocalWindow($checkIn) ? 'local' : 'cloud';
    }

    /**
     * Assert an online booking is allowed to be created.
     *
     * @throws ArrivalInsideLocalWindow
     */
    public function assertOnlineBookable(CarbonInterface $checkIn): void
    {
        if ($this->localAuthority() || ! $this->isLocalWindow($checkIn)) {
            return;
        }

        throw new ArrivalInsideLocalWindow($checkIn, $this->cutoffHours());
    }

    /**
     * Assert a front-desk booking is allowed to be created.
     *
     * Spec section 8: the desk books now and tomorrow; beyond the cutoff the
     * online channel is authoritative and the desk hands the guest over.
     *
     * @throws ArrivalOutsideLocalWindow
     */
    public function assertFrontDeskBookable(CarbonInterface $checkIn): void
    {
        if ($this->isLocalWindow($checkIn)) {
            return;
        }

        throw new ArrivalOutsideLocalWindow($checkIn, $this->cutoffHours());
    }

    public function forget(): void
    {
        Cache::forget('booking_authority_cutoff_hours');
    }
}
```

The cache mirrors `SettingService::all()`'s 1-hour TTL but at 5 minutes, because
the cutoff affects booking availability and should not take an hour to propagate.

- [x] **Step 3: Seed the setting**

Add to `SettingsSeeder`:

```php
Setting::updateOrCreate(
    ['key' => BookingAuthority::SETTING_KEY],
    ['value' => BookingAuthority::DEFAULT_CUTOFF_HOURS, 'meta' => ['type' => 'integer', 'label' => 'Front desk arrival cutoff (hours)'], 'updated_at' => now(), 'created_at' => now()],
);
```

The `settings` table has no `class` column, so the grouping goes in `meta`. The
admin settings screen groups by hard-coded section; add the cutoff to the
booking section in `resources/js/js/Pages/Admin/Settings/Index.vue` rather than
inventing a new classification system.

- [x] **Step 4: Test the service**

```php
public function test_arrivals_inside_the_window_belong_to_the_front_desk(): void
{
    config(['sync.node_type' => 'cloud']);
    $authority = app(BookingAuthority::class);

    $this->assertTrue($authority->isLocalWindow(now()));
    $this->assertTrue($authority->isLocalWindow(now()->addDay()));
    $this->assertSame('local', $authority->authorityFor(now()));
}

public function test_later_arrivals_route_online(): void
{
    config(['sync.node_type' => 'cloud']);
    $authority = app(BookingAuthority::class);

    $this->assertFalse($authority->isLocalWindow(now()->addDays(3)));
    $this->assertSame('cloud', $authority->authorityFor(now()->addDays(3)));
}

public function test_the_day_after_tomorrow_is_never_local(): void
{
    // This is the regression guard for Decision 2's end-of-day rule.
    //
    // windowEnd() is now() + 48h. If anyone changes it back to
    // ->startOfDay(), then at 14:00 the window becomes 10-08 00:00 and the day
    // after tomorrow (a 48-72h arrival) is claimed by the front desk instead of
    // the cloud. Run this at a few different times of day, because the answer
    // must not depend on the clock.
    config(['sync.node_type' => 'cloud']);
    $authority = app(BookingAuthority::class);

    foreach (['00:05', '09:30', '14:00', '23:50'] as $time) {
        $this->travelTo(now()->copy()->setTimeFromTimeString($time));

        $this->assertFalse(
            $authority->isLocalWindow(now()->addDays(2)),
            "Arrival two days out was claimed by the front desk at {$time}."
        );

        $this->assertSame('cloud', $authority->authorityFor(now()->addDays(2)));
    }
}

public function test_today_and_tomorrow_are_always_local_at_the_default_cutoff(): void
{
    // The other half of the same boundary: widening the cutoff must not push
    // the near dates to the cloud.
    config(['sync.node_type' => 'cloud']);
    $authority = app(BookingAuthority::class);

    foreach (['00:05', '09:30', '14:00', '23:50'] as $time) {
        $this->travelTo(now()->copy()->setTimeFromTimeString($time));

        $this->assertTrue($authority->isLocalWindow(now()), "today at {$time}");
        $this->assertTrue($authority->isLocalWindow(now()->addDay()), "tomorrow at {$time}");
    }
}

public function test_the_effective_window_is_reported_as_a_range(): void
{
    $authority = app(BookingAuthority::class);

    // Not "48 hours" — a date-only check_in makes the real window 48-72h, and
    // an operator reading sync:status should not be misled.
    $this->assertSame(
        '48-72 hours (date-only check_in)',
        $authority->effectiveWindowDescription(),
    );
}

public function test_the_cutoff_is_configurable(): void
{
    Setting::set(BookingAuthority::SETTING_KEY, 12);
    app(BookingAuthority::class)->forget();

    config(['sync.node_type' => 'cloud']);

    $this->assertSame(12, app(BookingAuthority::class)->cutoffHours());
    $this->assertFalse(app(BookingAuthority::class)->isLocalWindow(now()->addDay()));
}

public function test_online_booking_is_refused_inside_the_window(): void
{
    config(['sync.node_type' => 'cloud']);

    $this->expectException(ArrivalInsideLocalWindow::class);

    app(BookingAuthority::class)->assertOnlineBookable(now());
}

public function test_front_desk_booking_is_refused_beyond_the_window(): void
{
    config(['sync.node_type' => 'lan_server']);

    $this->expectException(ArrivalOutsideLocalWindow::class);

    app(BookingAuthority::class)->assertFrontDeskBookable(now()->addDays(21));
}

public function test_front_desk_booking_is_allowed_inside_the_window(): void
{
    config(['sync.node_type' => 'lan_server']);

    app(BookingAuthority::class)->assertFrontDeskBookable(now());
    app(BookingAuthority::class)->assertFrontDeskBookable(now()->addDay());

    $this->assertTrue(true);   // no exception thrown
}
```

- [x] **Step 5: Confirm the default without configuration**

```powershell
php artisan tinker --execute="var_dump(app(App\Services\Booking\BookingAuthority::class)->cutoffHours());"
```

Expected: `int(48)`.

Commit: `feat: add a single booking authority decision service`

---

## Task 3: Make the front desk re-check availability

> **Status:** COMPLETE 2026-10-11 (commit pending). Deviations from the plan:
> `assertRoomsAssignable` and the overlap checks use the pivot (`bookings.rooms`)
> instead of `bookings.room_id` so multi-room bookings are detected, and the
> half-open overlap now uses `whereDate` because sqlite stores date-cast columns
> as `YYYY-MM-DD 00:00:00` and raw string compares tripped on boundary days.
> Checkout yields `dirty`: `BookingRoom::checkOut` now matches
> `BookingService::checkOut` (Decision 3). Inertia submissions get inline form
> errors; plain/JSON requests get the hand-off view.

This is the correctness half of the phase. The front desk is authoritative inside
the window, so it must never attach a room that is already reserved.

**Files:**
- Modify: `app/Services/RoomAvailabilityService.php`
- Modify: `app/Http/Controllers/FrontDesk/BookingsController.php`

**Interfaces:**
- Consumes: Task 1's `assignRooms`, existing `activeReservationQuery` (L38-57).
- Produces: `RoomAvailabilityService::assertRoomsAssignable(array $roomIds, Carbon $checkIn, Carbon $checkOut, ?Booking $ignore = null): void`.

- [x] **Step 1: Confirm the defect**

```powershell
rg -n -A 20 "function store" app/Http/Controllers/FrontDesk/BookingsController.php
```

`store` (L104-193) validates `selected_room_ids.* => integer|exists:rooms,id` (L112)
and calls `resolveSelectedRooms` (L446-475), which checks existence and that all
rooms share one room type. There is no overlap query. `update` (L228) is the same.
`create` (L80-84) lists `Room::where('status','available')` with no date filter, so
the picker shows rooms that are booked for those dates.

- [x] **Step 2: Add the assertion**

```php
<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Assert the given rooms are free for the whole stay.
 *
 * The half-open overlap test mirrors activeReservationQuery: a stay ending on
 * the same day another begins is not an overlap.
 */
public function assertRoomsAssignable(
    array $roomIds,
    CarbonInterface $checkIn,
    CarbonInterface $checkOut,
    ?Booking $ignore = null,
): void {
    $conflicts = $this->conflictingBookings($roomIds, $checkIn, $checkOut, $ignore);

    if ($conflicts->isEmpty()) {
        return;
    }

    $numbers = Room::whereIn('id', $roomIds)->pluck('code', 'id');

    throw ValidationException::withMessages([
        'selected_room_ids' => 'These rooms are already reserved for those dates: '
            . $conflicts->map(fn (Booking $b) => ($numbers[$b->room_id] ?? $b->room_id)
                . ' (' . $b->guest_name . ', ' . $b->check_in->format('j M') . ')')
            ->implode('; '),
    ]);
}

private function conflictingBookings(
    array $roomIds,
    CarbonInterface $checkIn,
    CarbonInterface $checkOut,
    ?Booking $ignore,
) {
    return $this->activeReservationQuery($checkIn, $checkOut)
        ->whereIn('bookings.room_id', $roomIds)
        ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
        ->get();
}
```

- [x] **Step 3: Call it from the front-desk write paths**

In `store`, after `resolveSelectedRooms` and before `Booking::create`:

```php
$this->roomAvailability->assertRoomsAssignable($roomIds, $checkIn, $checkOut);
```

In `update`, after date re-validation, with the booking itself ignored:

```php
$this->roomAvailability->assertRoomsAssignable($roomIds, $checkIn, $checkOut, $booking);
```

- [x] **Step 4: Date-filter the room pickers**

`create` (L80-84) and `edit` (L200) should accept `check_in` and `check_out`
query parameters and list only rooms available for that range. When the dates are
absent, fall back to the current behaviour rather than showing nothing.

- [x] **Step 5: Refuse arrivals beyond the cutoff**

Spec §8: the front desk books now and tomorrow; beyond the cutoff the online
channel is authoritative. Guard `store` (L104) and `update` (L228) after
validation:

```php
$this->authority->assertFrontDeskBookable(Carbon::parse($validated['check_in']));
```

`update` must not re-apply this when the arrival date is unchanged — extending a
stay on a booking created inside the window is routine and must keep working. So:

```php
if ($this->authority->authorityFor(Carbon::parse($validated['check_in']))
    !== $this->authority->authorityFor($booking->check_in)) {
    $this->authority->assertFrontDeskBookable(Carbon::parse($validated['check_in']));
}
```

That comparison is the whole point of §8: it stops a *new* booking crossing the
line without blocking an *existing* one from being maintained.

- [x] **Step 6: Render the hand-off, not an error**

```php
private function outsideWindowResponse(ArrivalOutsideLocalWindow $e): Response
{
    return response()->view('booking.outside-local-window', [
        'checkIn'     => $e->checkIn->toFormattedDateString(),
        'cutoffHours' => $e->cutoffHours,
        'bookingUrl'  => route('booking.search', ['check_in' => $e->checkIn->toDateString()]),
        'hotelPhone'  => Setting::get('hotel_phone'),
    ], 422);
}
```

The view tells staff the online channel is authoritative for that arrival, links
to the public booking flow pre-filled with the arrival date, and shows the
property phone. Spec §8 says the realistic handling is walking the guest through
online booking or taking a deposit and finalizing on the cloud, so the copy must
give staff something to do rather than only a refusal.

The date filter on `create`/`edit` should also stop offering far-future dates in
the picker when they would be refused, so staff see the constraint before they
fill the form.

- [x] **Step 7: Reconcile the `dirty` inconsistency**
`bookableStatuses()` (L28-31) permits `available` and `dirty`. But
`lockRoomsForCheckIn` (L165) filters `status = 'available'` only, and
`BookingService::checkOut` sets `dirty` (L657/L687) while `BookingRoom::checkOut`
sets `available` (L69-71). So a room just vacated by check-out is bookable per
`bookableStatuses` but not per `lockRoomsForCheckIn`, and the two checkout methods
disagree on the resulting state.

Pick one. The safer rule is that checkout yields `dirty` and a housekeeper or
`checkIn` transition returns it to `available`, but this changes check-in
behaviour, so **confirm which is intended before changing it.** Whichever is
chosen, make `BookingRoom::checkOut` and `BookingService::checkOut` agree and add
a test.

- [x] **Step 6: Test**

```php
public function test_front_desk_cannot_assign_a_room_already_reserved_for_those_dates(): void
{
    $room = $this->roomFixture();
    $this->bookingFixture(room: $room, checkIn: now()->addDay(), checkOut: now()->addDays(3));

    $this->actingAs($this->frontdeskUser())
        ->postJson('/frontdesk/bookings', [
            'guest_name'         => 'Walk In',
            'guest_email'        => 'walkin@example.test',
            'selected_room_ids'  => [$room->id],
            'check_in'           => now()->addDay()->toDateString(),
            'check_out'          => now()->addDays(2)->toDateString(),
            'adults'             => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('selected_room_ids');
}

public function test_a_stay_ending_the_day_another_begins_is_not_a_conflict(): void
{
    $room = $this->roomFixture();
    $this->bookingFixture(room: $room, checkIn: now()->addDay(), checkOut: now()->addDays(3));

    $this->actingAs($this->frontdeskUser())
        ->postJson('/frontdesk/bookings', [
            'guest_name'        => 'Next Guest',
            'guest_email'       => 'next@example.test',
            'selected_room_ids' => [$room->id],
            'check_in'          => now()->addDays(3)->toDateString(),
            'check_out'         => now()->addDays(5)->toDateString(),
            'adults'            => 1,
        ])
        ->assertCreated();
}

public function test_updating_dates_to_overlap_another_booking_is_rejected(): void
{
    // booking B moves its dates onto booking A's room
}
```

- [x] **Step 7: Test the far-future refusal**

```php
public function test_front_desk_is_refused_a_booking_beyond_the_cutoff(): void
{
    config(['sync.node_type' => 'lan_server']);

    $this->actingAs($this->frontdeskUser())
        ->postJson('/frontdesk/bookings', [
            'guest_name'        => 'Three Weeks Out',
            'guest_email'       => 'future@example.test',
            'selected_room_ids' => [$this->roomFixture()->id],
            'check_in'          => now()->addDays(21)->toDateString(),
            'check_out'         => now()->addDays(23)->toDateString(),
            'adults'            => 1,
        ])
        ->assertStatus(422);
}

public function test_extending_a_stay_past_the_cutoff_still_works(): void
{
    config(['sync.node_type' => 'lan_server']);

    // booking created for tomorrow, extended three weeks out. The arrival date
    // did not change, so the authority window did not change and the guard must
    // not fire.
}
```

That second test is the one most likely to be written wrong. Assert it explicitly.

Commit: `fix: enforce the booking cutoff on both channels and re-check availability`

---

## Task 4: Enforce the online half of the partition

> **Status:** COMPLETE 2026-10-11. `availableRooms`, `selectRoom` and`r
> `createBooking` refuse arrivals inside the window and render
> `booking.local-window`; the shareable `booking/local-window` route renders the
> same notice (200) or redirects to search when the date is outside the window.
> Note: the notice page depends on `assertOnlineBookable`, so on a local node
> (which may book inside the window online) it redirects instead of showing � the
> plan's contact-details test was updated to set `sync.node_type = cloud`.

**Files:**
- Modify: `app/Http/Controllers/BookingController.php`
- Create: `app/Http/Controllers/Booking/LocalWindowNoticeController.php`
- Modify: `routes/public.php`

**Interfaces:**
- Consumes: Task 2's `BookingAuthority::assertOnlineBookable`.
- Produces: a 422 on availability and creation inside the window, plus a guest-facing notice page.

- [x] **Step 1: Confirm where to intervene**

Three public steps read the arrival date: `availableRooms` (L91, validation
L93-98), `selectRoom` (L155, validation L157-165), and `createBooking` (L291).
Refusing only at `createBooking` wastes the guest's time filling in the form, so
refuse at search time and again at creation.

- [x] **Step 2: Add the guard to availability search**

```php
$this->authority->assertOnlineBookable(Carbon::parse($validated['check_in']));
```

placed immediately after validation in `availableRooms` and `selectRoom`.

- [x] **Step 3: Render a useful notice, not an error page**

Convert the exception into a view rather than a 500. Add a `local-window`
handler in the controller:

```php
private function localWindowResponse(ArrivalInsideLocalWindow $e): Response
{
    return response()->view('booking.local-window', [
        'checkIn'     => $e->checkIn->toFormattedDateString(),
        'cutoffHours' => $e->cutoffHours,
        'hotelName'   => Setting::get('hotel_name'),
        'hotelPhone'  => Setting::get('hotel_phone'),
        'whatsapp'    => Setting::get('site_whatsapp'),
    ], 422);
}
```

The view states plainly that arrivals within the cutoff are arranged at the front
desk, gives the arrival date, and shows the phone and WhatsApp numbers from
existing settings keys. No new setting keys are needed.

- [x] **Step 4: Wire the route**

```php
Route::get('/booking/local-window', [LocalWindowNoticeController::class])
    ->name('booking.localWindow');
```

with its controller re-running `assertOnlineBookable` from a `check_in` query
parameter, so the link is safe to share.

- [x] **Step 5: Respect local authority**

`assertOnlineBookable` returns early when `localAuthority()` is true, so a LAN
terminal running the same public routes still books inside the window. That is
the intended behaviour for a node acting as its own front desk. Confirm this is
what the setup wizard should configure in Phase 6.

- [x] **Step 6: Test**

```php
public function test_online_availability_refuses_arrivals_inside_the_local_window(): void
{
    config(['sync.node_type' => 'cloud']);

    $this->getJson('/booking/rooms?check_in=' . now()->toDateString()
        . '&check_out=' . now()->addDays(2)->toDateString() . '&adults=1')
        ->assertStatus(422);
}

public function test_online_availability_allows_arrivals_beyond_the_window(): void
{
    config(['sync.node_type' => 'cloud']);

    $this->getJson('/booking/rooms?check_in=' . now()->addDays(5)->toDateString()
        . '&check_out=' . now()->addDays(7)->toDateString() . '&adults=1')
        ->assertOk();
}

public function test_a_local_node_still_books_inside_the_window(): void
{
    config(['sync.node_type' => 'lan_server']);
    // same request, asserts no 422
}

public function test_the_notice_shows_the_property_contact_details(): void
{
    Setting::set('hotel_phone', '+234 800 000 0000');

    $this->get('/booking/local-window?check_in=' . now()->toDateString())
        ->assertOk()
        ->assertSee('+234 800 000 0000');
}
```

Commit: `feat: refuse online bookings inside the front desk arrival window`

---

## Task 5: Repair the booking write paths this phase exercises

Three pre-existing defects sit directly on the paths a local terminal will use.
Each is a fatal error, not a style problem.

**Files:**
- Modify: `app/Http/Controllers/BookingController.php`, `app/Services/BookingService.php`

- [ ] **Step 1: `BookingController::cancelBooking` fatal (L685-693)**

```powershell
rg -n "this->service" app/Http/Controllers/BookingController.php
```

L32 declares `protected BookingService $service;` and L689 calls
`$this->service->cancelBooking(...)`, but the constructor (L46-61) assigns only
`$this->bookingService`. Any call is a fatal on a null property. `cancelBooking`
is also unrouted — `routes/public.php` L35 has the related route commented out —
so it is dead code that happens to be broken.

Decide: wire it or delete it. Cancellation is in scope for a local terminal (a
guest cancels at the front desk), so this plan wires it:

```php
Route::delete('/booking/{booking}', [BookingController::class, 'cancelBooking'])
    ->middleware('signed')
    ->name('booking.cancel');
```

Signed, because the guest has no session, and the guest must not cancel another
guest's booking by guessing an id. Add the rule and keep the existing
`$this->authorize('delete', $booking)` check at L687.

- [ ] **Step 2: `BookingService::extendStay` fatal (L715-719)**

```powershell
rg -n "isEditable" app/Models/Booking.php app/Services/BookingService.php
```

L719 calls `$booking->isEditable()`, which does not exist on `Booking`. The only
other reference is commented out at L396. So extending a stay — a routine
front-desk action — fatals.

Add the scope as a real method rather than inventing an inline condition, so
`updateBooking` can use it too:

```php
public function isEditable(): bool
{
    return ! in_array($this->status, ['checked_out', 'cancelled'], true)
        && $this->check_out->isFuture();
}

public function scopeEditable(Builder $query): Builder
{
    return $query->whereNotIn('status', ['checked_out', 'cancelled'])
        ->where('check_out', '>', now()->toDateString());
}
```

- [ ] **Step 3: `Room::$fillable` references a dropped column**

```powershell
rg -n "room_number" app/ database/migrations/
```

Migration `2026_01_08_163319_remove_room_number_from_rooms_table.php` drops
`room_number`, but `Room::$fillable` L18 still lists it and
`FrontDesk\BookingsController` reads it at L91, L216, L328. Any mass assignment
of `room_number` now writes a column that does not exist.

`Room` uses `code` and `name`. Replace the three references with `code`, and
remove `room_number` from `$fillable`. Verify no other consumer reads it.

- [ ] **Step 4: `bookings.checked_in_at` is written but has no column**

```powershell
rg -n "checked_in_at|checked_out_at" database/migrations/
```

`BookingService::checkIn` (L607) and `checkOut` (L693) write `checked_in_at` and
`checked_out_at` on `bookings`. No migration adds them; only
`booking_rooms.checked_in_at` exists. On a strict engine this fatals; on SQLite
it may be silently ignored, which is worse.

Either add the columns in a new migration or remove the writes. Because
Phase 4S syncs `booking_rooms` — which has the per-room timestamps that actually
matter — prefer removing the `bookings`-level writes unless a report reads them:

```powershell
rg -n "bookings.checked_in_at|->checked_in_at" app/Reporting/ app/Http/Controllers/
```

If nothing reads them, delete the writes and add a test asserting check-in
succeeds on Postgres.

- [ ] **Step 5: Test each repair**

```php
public function test_extending_a_stay_works(): void
{
    $booking = $this->bookingFixture(checkOut: now()->addDays(2));

    $this->actingAs($this->frontdeskUser())
        ->postJson("/frontdesk/bookings/{$booking->id}/extend", [
            'new_checkout' => now()->addDays(4)->toDateString(),
        ])
        ->assertOk();

    $this->assertSame(now()->addDays(4)->toDateString(), $booking->refresh()->check_out->toDateString());
}

public function test_a_guest_can_cancel_their_own_booking_with_a_signed_url(): void
{
    // signed URL: ok. Tampered booking id: 403.
}
```

Commit: `fix: repair fatal defects in the booking write paths`

---

## Task 6: Communicate the change to front-desk staff

Spec §12 Phase 5: "Ship the staff communication this requires in the same phase —
this changes how the desk works." Spec §8: "This is a business change as well as a
code change and must be communicated to front-desk staff before it ships."

This is not optional polish. A refusal the staff were not told about will be
worked around, and a workaround that re-books a guest online reintroduces the
conflict the partition exists to prevent.

**Files:**
- Modify: `resources/js/js/Pages/FrontDesk/Bookings/Create.vue`
- Modify: `resources/js/js/Pages/FrontDesk/Bookings/Edit.vue`
- Create: `docs/FRONT_DESK_CUTOFF.md`
- Modify: `database/seeders/SettingsSeeder.php`

**Interfaces:**
- Consumes: `BookingAuthority::cutoffHours()`.
- Produces: in-product guidance and an operator-facing note.

- [ ] **Step 1: Explain the rule inside the form, before the refusal fires**

A staff member should learn the constraint while filling the form, not from a 422.
Add an inline notice to the front-desk booking create and edit screens:

```vue
<script setup>
import { computed } from 'vue'

const props = defineProps({ cutoffHours: Number, booking: Object })

// Only warn once the arrival leaves the window, and never for an existing
// booking whose arrival is unchanged.
const showNotice = computed(() => {
  if (!props.cutoffHours || !props.arrivalDate) return false
  const arrival = new Date(props.arrivalDate)
  const windowEnd = new Date()
  windowEnd.setHours(windowEnd.getHours() + props.cutoffHours)
  windowEnd.setHours(0, 0, 0, 0)
  return arrival > windowEnd
})
</script>

<template>
  <div
    v-if="showNotice"
    role="status"
    class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
  >
    <p class="font-semibold">
      Arrivals more than {{ cutoffHours }} hours out are taken on the online booking channel.
    </p>
    <p class="mt-1">
      Take the guest's details, then either help them complete the booking online or take a
      deposit and finalise it on the cloud. Today's cutoff is
      {{ new Date().toLocaleString(undefined, { dateStyle: 'full' }) }}.
    </p>
  </div>
</template>
```

Pass `cutoff_hours` from the controller into the page via the existing Inertia
props. The server remains the authority — this notice is guidance, and the guard
in Task 3 is the enforcement.

- [ ] **Step 2: Make the front desk see the cutoff without guessing**

Show the window end date on the bookings index, so a staff member looking at the
list knows at a glance which arrivals belong to the desk.

- [ ] **Step 3: Make the cutoff adjustable without a deploy**

The setting is Class 5, pushed down from the cloud. Add it to the booking section
of `resources/js/js/Pages/Admin/Settings/Index.vue` with a numeric input and a
clear label. A property with a long drive from the airport may want 24 hours; one
across town may want 48. Requiring a code change for that is wrong.

- [ ] **Step 4: Write the operator note**

`docs/FRONT_DESK_CUTOFF.md` covers, in the words the owner would use with staff:

- What changed and why the hotel now refuses some bookings.
- The §8 table: now and tomorrow are booked at the desk; further out is online.
- Why: the front desk holds the freshest room state, and one authority per arrival
  prevents two people selling the same room.
- What to do when a guest wants a room further out: walk them through online
  booking, or take a deposit and finalise on the cloud.
- How to check or change the cutoff.
- Who to tell when a guest is upset about it.

Include the walk-in reassurance from spec §8: walk-ins are unaffected and get the
better path, because the LAN server holds the freshest room state.

- [ ] **Step 5: Have someone who works the desk read it**

Before this ships. A member of front-desk staff should be able to read the note and
answer "what do I tell a guest who wants next month" without asking a developer.

- [ ] **Step 6: Roll it out behind a visible marker until it is understood**

Consider seeding the cutoff setting only after the note has been circulated, so
the refusal does not surprise the desk on day one. If the property prefers to
enable it immediately, remove the marker — but do not skip this step silently.

Commit: `feat: communicate the arrival cutoff to front desk staff`

---

## Task 7: Prove authority end to end

**Files:**
- Modify: `tests/Feature/Bookings/BookingAuthorityTest.php`

- [ ] **Step 1: Write the cross-node test**

Two nodes, one authority decision, no conflict:

```php
public function test_a_booking_created_on_the_lan_server_is_not_contested_by_the_cloud(): void
{
    config(['sync.node_type' => 'lan_server']);
    $booking = $this->createBooking(['check_in' => now()->addDay(), 'check_out' => now()->addDays(2)]);

    config(['sync.node_type' => 'cloud']);

    $this->assertSame('local', app(BookingAuthority::class)->authorityFor($booking->check_in));
}

public function test_the_cloud_refuses_to_sell_inside_a_window_the_lan_server_owns(): void
{
    config(['sync.node_type' => 'cloud']);

    $this->expectException(ArrivalInsideLocalWindow::class);

    app(BookingAuthority::class)->assertOnlineBookable(now()->addDay());
}

public function test_beyond_the_window_the_online_path_is_authoritative(): void
{
    config(['sync.node_type' => 'cloud']);

    $booking = $this->createBooking(['check_in' => now()->addDays(10), 'check_out' => now()->addDays(12)]);

    $this->assertSame('cloud', app(BookingAuthority::class)->authorityFor($booking->check_in));
}
```

- [ ] **Step 2: Verify existing booking tests still pass**

```powershell
php artisan test --filter=Booking
```

All of `tests/Feature/Bookings/`, `tests/Feature/FrontDesk/`,
`tests/Feature/Payments/BookingPaymentTest.php` must stay green. The pivot
change in Task 1 is the highest-risk edit in this phase; these tests are the
safety net.

- [ ] **Step 3: Verify the suite**

```powershell
php artisan migrate:fresh --force
php artisan test
```

Commit: `test: cover booking authority across both node types`

---

## Exit criteria

- One `BookingAuthority` service decides authority; no controller computes a
  cutoff of its own.
- The cutoff is a settings key with a 48-hour default and no `class` column
  required.
- The partition is enforced on both channels: online refuses inside the window,
  the front desk refuses beyond it.
- An existing booking can still be extended or maintained after the cutoff passes
  it; only a change of arrival across the line is refused.
- Neither refusal is a bare error page; each explains the other channel.
- The front desk re-checks availability on both create and update.
- `booking_rooms` is the only room-assignment writer; `bookings.room_id` follows
  it.
- The three fatal booking-path defects are fixed and covered.
- The full suite is green.

Phase 7 verifies the front-desk half against a real LAN server, per spec §12.

Next: [Phase 6 — desktop shell and setup wizard](2026-10-05-phase-6-tauri-desktop-and-setup-wizard.md).
