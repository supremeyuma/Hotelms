<?php

namespace Tests\Feature\Bookings;

use App\Exceptions\ArrivalInsideLocalWindow;
use App\Exceptions\ArrivalOutsideLocalWindow;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
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

    public function test_a_booking_created_on_the_lan_server_is_not_contested_by_the_cloud(): void
    {
        config(['sync.node_type' => 'lan_server']);
        $booking = $this->createBooking([
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]);

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

        $booking = $this->createBooking([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
        ]);

        $this->assertSame('cloud', app(BookingAuthority::class)->authorityFor($booking->check_in));
    }

    protected function createBooking(array $overrides = []): Booking
    {
        $room = $this->roomFixture();

        $booking = Booking::create(array_merge([
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'booking_code' => 'AUTH-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guest_name' => 'Authority Guest',
            'guest_email' => 'authority@example.com',
            'guest_phone' => '08030007777',
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 80000,
            'total_amount' => 80000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
        ], $overrides));

        $booking->rooms()->attach($room->id, ['status' => 'reserved']);

        return $booking;
    }

    protected function roomFixture(): Room
    {
        $property = Property::create([
            'name' => 'Authority Hotel',
            'location' => 'Lagos',
            'amenities' => [],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 80000,
            'features' => [],
        ]);

        return Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 201',
            'code' => 'EXEC-201',
            'status' => 'available',
            'meta' => [],
        ]);
    }
}
