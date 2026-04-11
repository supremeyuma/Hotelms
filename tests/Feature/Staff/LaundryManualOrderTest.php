<?php

namespace Tests\Feature\Staff;

use App\Models\Booking;
use App\Events\LaundryOrderUpdated;
use App\Models\LaundryItem;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaundryManualOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_laundry_staff_can_create_manual_laundry_order_for_active_booking(): void
    {
        Event::fake([LaundryOrderUpdated::class]);

        $staffUser = $this->laundryUser();

        $property = Property::create([
            'name' => 'Moorelife Resort',
            'location' => 'Lagos',
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 120000,
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'E101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'booking_code' => 'BKG-LAUNDRY-1001',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 120000,
            'total_amount' => 240000,
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'guest_name' => 'Ada Guest',
            'guest_email' => 'ada@example.com',
            'guest_phone' => '08000000000',
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'active',
            'checked_in_at' => now()->subDay(),
        ]);

        $shirt = LaundryItem::create([
            'name' => 'Shirt',
            'price' => 2500,
            'description' => 'Pressed shirt',
        ]);

        $trouser = LaundryItem::create([
            'name' => 'Trouser',
            'price' => 3000,
            'description' => 'Pressed trouser',
        ]);

        $response = $this->actingAs($staffUser)->post(route('staff.laundry.store'), [
            'room_id' => $room->id,
            'payment_mode' => 'postpaid',
            'items' => [
                [
                    'laundry_item_id' => $shirt->id,
                    'quantity' => 2,
                ],
                [
                    'laundry_item_id' => $trouser->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('laundry_orders', 1);
        $this->assertDatabaseHas('laundry_orders', [
            'room_id' => $room->id,
            'status' => 'requested',
            'total_amount' => 8000,
        ]);
        $this->assertDatabaseHas('laundry_order_items', [
            'laundry_item_id' => $shirt->id,
            'quantity' => 2,
            'subtotal' => 5000,
        ]);
        $this->assertDatabaseHas('laundry_order_items', [
            'laundry_item_id' => $trouser->id,
            'quantity' => 1,
            'subtotal' => 3000,
        ]);
        $this->assertDatabaseHas('charges', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => 8000,
            'payment_mode' => 'postpaid',
            'type' => 'laundry',
        ]);
        $this->assertDatabaseHas('guest_requests', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'type' => 'laundry',
            'status' => 'requested',
        ]);
    }

    protected function laundryUser(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'laundry',
            'guard_name' => 'web',
        ], [
            'slug' => 'laundry',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
