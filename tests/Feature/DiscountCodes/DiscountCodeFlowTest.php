<?php

namespace Tests\Feature\DiscountCodes;

use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiscountCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_discount_code(): void
    {
        $manager = $this->managerUser();

        $response = $this->actingAs($manager)->post(route('admin.discount-codes.store'), [
            'name' => 'Weekend Saver',
            'code' => 'SAVE10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subHour()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDay()->format('Y-m-d H:i:s'),
            'max_rooms' => 5,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.discount-codes.index'));

        $this->assertDatabaseHas('discount_codes', [
            'name' => 'Weekend Saver',
            'code' => 'SAVE10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
        ]);
    }

    public function test_manager_can_extend_discount_code_expiration(): void
    {
        $manager = $this->managerUser();
        $code = DiscountCode::create([
            'name' => 'Weekend Saver',
            'code' => 'SAVE10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 5,
            'is_active' => true,
        ]);

        $newExpiry = now()->addDays(5)->startOfMinute();

        $response = $this->actingAs($manager)->patch(route('admin.discount-codes.extend', $code), [
            'valid_until' => $newExpiry->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('discount_codes', [
            'id' => $code->id,
            'valid_until' => $newExpiry->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_superuser_can_extend_discount_code_expiration(): void
    {
        $superuser = $this->superuserUser();
        $code = DiscountCode::create([
            'name' => 'Weekend Saver',
            'code' => 'SAVE10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 5,
            'is_active' => true,
        ]);

        $newExpiry = now()->addDays(7)->startOfMinute();

        $response = $this->actingAs($superuser)->patch(route('admin.discount-codes.extend', $code), [
            'valid_until' => $newExpiry->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('discount_codes', [
            'id' => $code->id,
            'valid_until' => $newExpiry->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_manager_can_delete_unused_discount_code(): void
    {
        $manager = $this->managerUser();
        $code = DiscountCode::create([
            'name' => 'Weekend Saver',
            'code' => 'SAVE10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->delete(route('admin.discount-codes.destroy', $code));

        $response->assertRedirect(route('admin.discount-codes.index'));
        $this->assertDatabaseMissing('discount_codes', [
            'id' => $code->id,
        ]);
    }

    public function test_superuser_can_delete_unused_discount_code(): void
    {
        $superuser = $this->superuserUser();
        $code = DiscountCode::create([
            'name' => 'Weekend Saver',
            'code' => 'SAVE10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 5,
            'is_active' => true,
        ]);

        $response = $this->actingAs($superuser)->delete(route('admin.discount-codes.destroy', $code));

        $response->assertRedirect(route('admin.discount-codes.index'));
        $this->assertDatabaseMissing('discount_codes', [
            'id' => $code->id,
        ]);
    }

    public function test_discount_code_with_redemption_history_cannot_be_deleted(): void
    {
        [$property, $roomType, $room] = $this->roomFixture();
        $manager = $this->managerUser();

        $code = DiscountCode::create([
            'name' => 'Protected Saver',
            'code' => 'KEEP10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 1,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'BKG-CODE-DELETE',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guests' => 1,
            'guest_name' => 'Guest One',
            'guest_email' => 'one@example.com',
            'guest_phone' => '08011111111',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => 90000,
            'total_amount' => 91260,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        $booking->discountRedemption()->create([
            'discount_code_id' => $code->id,
            'scope' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'status' => 'reserved',
            'rooms_used' => 1,
            'base_amount' => 90000,
            'discount_amount' => 4500,
        ]);

        $response = $this->actingAs($manager)->delete(route('admin.discount-codes.destroy', $code));

        $response->assertSessionHasErrors('discount_code');
        $this->assertDatabaseHas('discount_codes', [
            'id' => $code->id,
        ]);
    }

    public function test_guest_can_apply_discount_to_booking_before_payment(): void
    {
        [$property, $roomType, $room] = $this->roomFixture();

        DiscountCode::create([
            'name' => 'Room Saver',
            'code' => 'ROOM10',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 2,
            'is_active' => true,
        ]);

        $this->withSession([
            'booking' => [
                'room_type_id' => $roomType->id,
                'quantity' => 1,
                'selected_room_ids' => [$room->id],
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(3)->toDateString(),
                'adults' => 2,
                'children' => 0,
                'guest_name' => 'Guest Tester',
                'guest_email' => 'guest@example.com',
                'guest_phone' => '08000000000',
                'emergency_contact_name' => 'Backup Guest',
                'emergency_contact_phone' => '08099999999',
                'purpose_of_stay' => 'Business',
            ],
        ])->post(route('booking.discount.apply'), [
            'discount_code' => 'ROOM10',
        ])->assertRedirect();

        $response = $this->withSession(session()->all())->post(route('booking.create'));

        $booking = Booking::query()->latest('id')->first();

        $response->assertRedirect(route('booking.payment', $booking));
        $this->assertNotNull($booking);
        $this->assertEquals(180000.00, (float) $booking->total_amount);
        $this->assertSame('Backup Guest', $booking->emergency_contact_name);
        $this->assertSame('08099999999', $booking->emergency_contact_phone);
        $this->assertSame('Business', $booking->purpose_of_stay);
        $this->assertSame('ROOM10', data_get($booking->details, 'discount.code'));
        $this->assertDatabaseHas('discount_code_redemptions', [
            'discount_code_id' => DiscountCode::where('code', 'ROOM10')->value('id'),
            'redeemable_type' => $booking->getMorphClass(),
            'redeemable_id' => $booking->id,
            'rooms_used' => 1,
            'status' => 'reserved',
        ]);
    }

    public function test_room_cap_blocks_excess_discount_usage(): void
    {
        [$property, $roomType, $room] = $this->roomFixture();

        $code = DiscountCode::create([
            'name' => 'Single Use',
            'code' => 'ONEONLY',
            'applies_to' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'discount_type' => DiscountCode::TYPE_PERCENTAGE,
            'discount_value' => 5,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'max_rooms' => 1,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'BKG-CODE-1',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guests' => 1,
            'guest_name' => 'Guest One',
            'guest_email' => 'one@example.com',
            'guest_phone' => '08011111111',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => 90000,
            'total_amount' => 91260,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        $booking->rooms()->attach($room->id, ['status' => 'reserved']);
        $booking->discountRedemption()->create([
            'discount_code_id' => $code->id,
            'scope' => DiscountCode::APPLIES_TO_ROOM_RATE,
            'status' => 'reserved',
            'rooms_used' => 1,
            'base_amount' => 90000,
            'discount_amount' => 4500,
        ]);

        $secondBooking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'BKG-CODE-2',
            'check_in' => now()->addDays(3)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'guests' => 1,
            'guest_name' => 'Guest Two',
            'guest_email' => 'two@example.com',
            'guest_phone' => '08022222222',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => 90000,
            'total_amount' => 91260,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        $secondBooking->rooms()->attach($room->id, ['status' => 'reserved']);

        $response = $this->post(route('booking.payment.discount.apply', $secondBooking), [
            'discount_code' => 'ONEONLY',
        ]);

        $response->assertSessionHasErrors('discount_code');
    }

    protected function roomFixture(): array
    {
        $property = Property::create([
            'name' => 'Moorelife Resort',
            'location' => 'Lagos',
            'amenities' => [],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Deluxe',
            'max_occupancy' => 2,
            'base_price' => 100000,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Deluxe 101',
            'status' => 'available',
            'meta' => [],
        ]);

        return [$property, $roomType, $room];
    }

    protected function managerUser(): User
    {
        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ], [
            'slug' => 'manager',
        ]);

        $user = User::factory()->create();
        $user->assignRole($managerRole);

        return $user;
    }

    protected function superuserUser(): User
    {
        $superuserRole = Role::firstOrCreate([
            'name' => 'superuser',
            'guard_name' => 'web',
        ], [
            'slug' => 'superuser',
        ]);

        $user = User::factory()->create();
        $user->assignRole($superuserRole);

        return $user;
    }
}
