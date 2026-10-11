<?php

namespace Tests\Feature\Bookings;

use App\Exceptions\ArrivalInsideLocalWindow;
use App\Exceptions\ArrivalOutsideLocalWindow;
use App\Models\Setting;
use App\Services\Booking\BookingAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingAuthorityTest extends TestCase
{
    use RefreshDatabase;

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
        // Regression guard for Decision 2's end-of-day rule.
        //
        // windowEnd() is now() + 48h. If anyone changes it back to
        // ->startOfDay(), then at 14:00 the window becomes 10-08 00:00 and the
        // day after tomorrow (a 48-72h arrival) is claimed by the front desk
        // instead of the cloud. Run this at a few different times of day,
        // because the answer must not depend on the clock.
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

        $this->assertTrue(true);
    }

    public function test_a_local_node_still_sells_inside_the_window_online(): void
    {
        config(['sync.node_type' => 'lan_server']);

        // A terminal acting as its own front desk must not refuse itself.
        app(BookingAuthority::class)->assertOnlineBookable(now());

        $this->assertTrue(true);
    }
}
