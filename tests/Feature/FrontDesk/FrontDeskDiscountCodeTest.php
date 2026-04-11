<?php

namespace Tests\Feature\FrontDesk;

use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontDeskDiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontdesk_can_apply_discount_code_when_creating_manual_booking(): void
    {
        $frontdesk = $this->frontdeskUser();
        [$property, $roomType, $room] = $this->roomFixture(80000);

        DiscountCode::create([
            'name' => 'Desk Saver',
            'code' => 'DESK10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($frontdesk)->post(route('frontdesk.bookings.store'), [
            'guest_name' => 'Manual Guest',
            'guest_email' => 'desk@example.com',
            'guest_phone' => '08030000000',
            'room_id' => $room->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'discount_code' => 'DESK10',
        ]);

        $booking = Booking::latest('id')->first();

        $response->assertRedirect(route('frontdesk.bookings.show', $booking));
        $this->assertSame('confirmed', $booking->status);
        $this->assertEquals(144000.00, (float) $booking->total_amount);
        $this->assertSame('DESK10', data_get($booking->details, 'discount.code'));
        $this->assertDatabaseHas('discount_code_redemptions', [
            'redeemable_type' => $booking->getMorphClass(),
            'redeemable_id' => $booking->id,
            'status' => 'applied',
        ]);
    }

    public function test_frontdesk_can_apply_discount_code_to_in_house_charge(): void
    {
        $frontdesk = $this->frontdeskUser();
        [$property, $roomType, $room] = $this->roomFixture(90000);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'FD-BOOK-1',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 1,
            'guest_name' => 'In House',
            'guest_email' => 'house@example.com',
            'guest_phone' => '08040000000',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => 90000,
            'total_amount' => 90000,
            'status' => 'checked_in',
            'payment_status' => 'pending',
        ]);
        $booking->rooms()->attach($room->id, ['status' => 'active']);

        DiscountCode::create([
            'name' => 'Bar Guest',
            'code' => 'BAR20',
            'applies_to' => DiscountCode::APPLIES_TO_BAR,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($frontdesk)->post(route('frontdesk.billing.charge', $booking), [
            'room_id' => $room->id,
            'description' => 'Rooftop cocktails',
            'amount' => 10000,
            'charge_type' => 'bar',
            'discount_code' => 'BAR20',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('charges', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'description' => 'Rooftop cocktails',
            'amount' => 10000,
            'type' => 'bar',
        ]);
        $this->assertDatabaseHas('charges', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'description' => 'Discount (BAR20) - Rooftop cocktails',
            'amount' => -2000,
            'type' => 'discount',
        ]);
    }

    protected function roomFixture(float $basePrice): array
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
            'base_price' => $basePrice,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 201',
            'status' => 'available',
            'meta' => [],
        ]);

        return [$property, $roomType, $room];
    }

    protected function frontdeskUser(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'frontdesk',
            'guard_name' => 'web',
        ], [
            'slug' => 'frontdesk',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
