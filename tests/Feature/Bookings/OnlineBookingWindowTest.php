<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineBookingWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

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
        $this->roomFixture();

        $this->getJson('/booking/rooms?check_in=' . now()->addDays(5)->toDateString()
            . '&check_out=' . now()->addDays(7)->toDateString() . '&adults=1')
            ->assertOk();
    }

    public function test_a_local_node_still_books_inside_the_window(): void
    {
        config(['sync.node_type' => 'lan_server']);
        $this->roomFixture();

        $this->getJson('/booking/rooms?check_in=' . now()->toDateString()
            . '&check_out=' . now()->addDay()->toDateString() . '&adults=1')
            ->assertOk();
    }

    public function test_the_notice_shows_the_property_contact_details(): void
    {
        config(['sync.node_type' => 'cloud']);
        Setting::set('hotel_phone', '+234 800 000 0000');

        $this->get('/booking/local-window?check_in=' . now()->toDateString())
            ->assertOk()
            ->assertSee('+234 800 000 0000');
    }

    public function test_the_notice_redirects_when_the_date_is_beyond_the_window(): void
    {
        config(['sync.node_type' => 'cloud']);

        $this->get('/booking/local-window?check_in=' . now()->addDays(10)->toDateString())
            ->assertRedirect();
    }

    protected function roomFixture(): Room
    {
        $property = Property::create([
            'name' => 'Moorelife Resort',
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
            'status' => 'available',
            'meta' => [],
        ]);
    }
}
