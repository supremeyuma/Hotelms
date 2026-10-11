<?php

namespace Tests\Feature\FrontDesk;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use App\Services\Booking\BookingAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontDeskCutoffCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bookings_index_exposes_the_cutoff_window(): void
    {
        Setting::set(BookingAuthority::SETTING_KEY, '36');

        $response = $this->actingAs($this->frontdeskUser())->get(route('frontdesk.bookings.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontDesk/Bookings/Index')
            ->where('cutoffHours', 36)
        );
    }

    public function test_the_create_page_exposes_the_cutoff_window(): void
    {
        Setting::set(BookingAuthority::SETTING_KEY, '36');
        $this->roomFixture();

        $response = $this->actingAs($this->frontdeskUser())->get(route('frontdesk.bookings.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontDesk/Bookings/Create')
            ->where('cutoffHours', 36)
        );
    }

    public function test_the_edit_page_exposes_the_cutoff_window(): void
    {
        Setting::set(BookingAuthority::SETTING_KEY, '36');
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        $response = $this->actingAs($this->frontdeskUser())->get(route('frontdesk.bookings.edit', $booking));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FrontDesk/Bookings/Edit')
            ->where('cutoffHours', 36)
        );
    }

    protected function roomFixture(): Room
    {
        $property = Property::create([
            'name' => 'Cutoff Hotel',
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

    protected function bookingFixture(Room $room, $checkIn, $checkOut): Booking
    {
        $booking = Booking::create([
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'booking_code' => 'CUTOFF-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'check_in' => \Carbon\Carbon::parse($checkIn)->toDateString(),
            'check_out' => \Carbon\Carbon::parse($checkOut)->toDateString(),
            'guest_name' => 'Cutoff Guest',
            'guest_email' => 'cutoff@example.com',
            'guest_phone' => '08030008888',
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 80000,
            'total_amount' => 160000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
        ]);

        $booking->rooms()->attach($room->id, ['status' => 'reserved']);

        return $booking;
    }

    protected function frontdeskUser(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'frontdesk', 'guard_name' => 'web'],
            ['slug' => 'frontdesk'],
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}