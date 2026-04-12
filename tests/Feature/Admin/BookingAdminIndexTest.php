<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingAdminIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_search_admin_bookings_by_guest_code_and_room(): void
    {
        $managerRole = Role::create([
            'name' => 'manager',
            'slug' => 'manager',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($managerRole);

        $property = Property::create([
            'name' => 'Summit Hotel',
            'location' => 'Abuja',
            'amenities' => ['wifi'],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Deluxe',
            'max_occupancy' => 2,
            'base_price' => 65000,
            'features' => ['wifi'],
        ]);

        $matchingRoom = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Coral 201',
            'display_name' => 'Coral 201',
            'code' => 'CR-201',
            'status' => 'reserved',
        ]);

        $otherRoom = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Palm 305',
            'display_name' => 'Palm 305',
            'code' => 'PM-305',
            'status' => 'reserved',
        ]);

        $matchingBooking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $matchingRoom->id,
            'room_type_id' => $roomType->id,
            'booking_code' => 'ADM-1001',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'guests' => 2,
            'adults' => 2,
            'children' => 0,
            'total_amount' => 130000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'guest_name' => 'Miriam Okoro',
            'guest_email' => 'miriam@example.com',
            'guest_phone' => '08030000001',
        ]);

        $matchingBooking->rooms()->attach($matchingRoom->id, [
            'status' => 'reserved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherBooking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $otherRoom->id,
            'room_type_id' => $roomType->id,
            'booking_code' => 'ADM-2002',
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'guests' => 1,
            'adults' => 1,
            'children' => 0,
            'total_amount' => 65000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'guest_name' => 'Tunde Bello',
            'guest_email' => 'tunde@example.com',
            'guest_phone' => '08030000002',
        ]);

        $otherBooking->rooms()->attach($otherRoom->id, [
            'status' => 'reserved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.bookings.index', [
            'search' => 'Coral 201',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Bookings/Index')
            ->where('filters.search', 'Coral 201')
            ->where('filters.dateType', 'check_in')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.booking_code', 'ADM-1001')
        );
    }
}
