<?php

namespace Tests\Feature\FrontDesk;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontDeskManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_billing_route_redirects_to_booking_ledger_for_current_booking(): void
    {
        $frontdesk = $this->frontdeskUser();
        [$room, $booking] = $this->checkedInBookingFixture();

        $response = $this->actingAs($frontdesk)->get("/frontdesk/rooms/{$room->id}/billing");

        $response->assertRedirect(route('frontdesk.billing.show', $booking));
    }

    public function test_room_billing_payment_route_records_manual_payment_in_payments_table(): void
    {
        $frontdesk = $this->frontdeskUser();
        [$room, $booking] = $this->checkedInBookingFixture();

        $response = $this->actingAs($frontdesk)->post("/frontdesk/rooms/{$room->id}/billing/pay", [
            'amount' => 50000,
            'method' => 'Cash',
            'reference' => 'FD-ROOM-CASH-1',
            'notes' => 'Front desk cash receipt',
        ]);

        $response->assertRedirect(route('frontdesk.billing.show', $booking));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => 50000,
            'method' => 'Cash',
            'reference' => 'FD-ROOM-CASH-1',
            'status' => 'successful',
        ]);
        $this->assertDatabaseCount('room_payments', 0);

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('Cash', $booking->payment_method);
    }

    public function test_booking_billing_payment_route_updates_booking_payment_state(): void
    {
        $frontdesk = $this->frontdeskUser();
        [$room, $booking] = $this->checkedInBookingFixture(amount: 90000);

        $response = $this->actingAs($frontdesk)->post(route('frontdesk.billing.pay', $booking), [
            'room_id' => $room->id,
            'amount' => 30000,
            'method' => 'Transfer',
            'reference' => 'FD-BOOKING-TRANSFER-1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => 30000,
            'method' => 'Transfer',
            'reference' => 'FD-BOOKING-TRANSFER-1',
            'status' => 'successful',
        ]);

        $booking->refresh();
        $this->assertSame('partial', $booking->payment_status);
        $this->assertSame('Transfer', $booking->payment_method);
    }

    protected function checkedInBookingFixture(float $amount = 50000): array
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
            'base_price' => $amount,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 201',
            'status' => 'occupied',
            'meta' => [],
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'FD-PAY-' . strtoupper((string) fake()->unique()->bothify('####')),
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guest_name' => 'Front Desk Guest',
            'guest_email' => 'frontdesk@example.com',
            'guest_phone' => '08030000000',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => $amount,
            'total_amount' => $amount,
            'status' => 'checked_in',
            'payment_status' => 'pending',
            'adults' => 2,
            'children' => 0,
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'active',
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'rate_override' => null,
        ]);

        return [$room, $booking];
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
