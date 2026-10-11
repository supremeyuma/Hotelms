<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use App\Services\RoomBalanceService;
use App\Events\RoomBillingUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 5 Task 5: the booking write paths a local terminal uses must not fatal.
 *
 * - cancelBooking is wired to a signed route and uses the injected service.
 * - extendStay uses the real Booking::isEditable().
 * - orders no longer write checked_in_at/checked_out_at on bookings.
 */
class BookingWritePathRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_extending_a_stay_works(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        $this->actingAs($this->frontdeskUser())
            ->from(route('frontdesk.dashboard'))
            ->post(route('frontdesk.bookings.extendStay', $booking), [
                'new_checkout' => now()->addDays(4)->toDateString(),
            ])
            ->assertRedirect(route('frontdesk.dashboard'))
            ->assertSessionHas('success');

        $this->assertSame(
            now()->addDays(4)->toDateString(),
            $booking->refresh()->check_out->toDateString()
        );
    }

    public function test_extending_a_checked_out_booking_is_rejected(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));
        $booking->update(['status' => 'checked_out']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot extend a checked-in or completed booking.');

        app(BookingService::class)->extendStay($booking, now()->addDays(4)->toDateString());
    }

    public function test_a_valid_signed_url_lets_a_manager_cancel_their_booking(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        $url = URL::signedRoute('booking.cancel', ['booking' => $booking->id]);

        $this->actingAs($this->managerUser())
            ->from('/')
            ->delete($url)
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $booking->refresh()->status);
        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
    }

    public function test_a_valid_signature_with_a_wrong_booking_is_rejected(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        // Build a signature for the wrong booking id.
        $url = URL::signedRoute('booking.cancel', ['booking' => $booking->id + 99999]);

        $this->actingAs($this->managerUser())
            ->delete($url)
            ->assertNotFound();

        $this->assertNotSoftDeleted('bookings', ['id' => $booking->id]);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        $url = URL::signedRoute('booking.cancel', ['booking' => $booking->id]);
        $query = parse_url($url, PHP_URL_QUERY);
        $tampered = str_replace(substr($query ?? '', strrpos((string) $query, '=') + 1), 'badsignature', $url);

        $this->actingAs($this->managerUser())
            ->delete($tampered)
            ->assertForbidden();
    }

    public function test_an_unauthenticated_guest_can_cancel_with_a_valid_signed_url(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        $url = URL::signedRoute('booking.cancel', ['booking' => $booking->id]);

        $this->from('/')
            ->delete($url)
            ->assertRedirect('/')
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $booking->refresh()->status);
        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'booking_cancelled',
            'user_id' => null,
        ]);
    }

    public function test_an_unauthenticated_cancel_without_a_valid_signature_is_forbidden(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        $this->from('/')
            ->delete(route('booking.cancel', $booking))
            ->assertForbidden();

        $this->assertSame('confirmed', $booking->refresh()->status);
    }

    public function test_check_in_writes_per_room_timestamps_only_on_the_pivot(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));

        app(BookingService::class)->checkIn($booking, 1);

        $this->assertSame('checked_in', $booking->refresh()->status);
        $this->assertDatabaseHas('booking_rooms', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'status' => 'active',
        ]);

        $pivot = $booking->rooms->first()->pivot;
        $this->assertNotNull($pivot->checked_in_at);
        $this->assertSame('occupied', $room->fresh()->status);
    }

    public function test_check_out_no_longer_writes_records_to_a_missing_bookings_column(): void
    {
        Event::fake([RoomBillingUpdated::class]);

        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(2));
        $booking->update(['status' => 'checked_in', 'check_in' => now()->subDay()->toDateString()]);
        $booking->rooms()->updateExistingPivot($room->id, [
            'status' => 'active',
            'checked_in_at' => now()->subDay(),
        ]);

        $roomBalanceMock = Mockery::mock(RoomBalanceService::class);
        $roomBalanceMock->shouldReceive('roomCanCheckout')
            ->andReturnTrue();
        $this->app->instance(RoomBalanceService::class, $roomBalanceMock);

        app(BookingService::class)->checkOut($booking);

        $this->assertSame('checked_out', $booking->refresh()->status);
        $this->assertSame('dirty', $room->fresh()->status);
        $this->assertDatabaseHas('booking_rooms', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'status' => 'checked_out',
        ]);
    }

    protected function roomFixture(): Room
    {
        $property = Property::create([
            'name' => 'Repair Hotel',
            'location' => 'Abuja',
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
            'booking_code' => 'REPAIR-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'check_in' => \Carbon\Carbon::parse($checkIn)->toDateString(),
            'check_out' => \Carbon\Carbon::parse($checkOut)->toDateString(),
            'guest_name' => 'Repair Guest',
            'guest_email' => 'repair@example.com',
            'guest_phone' => '08030009999',
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

    protected function managerUser(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'web'],
            ['slug' => 'manager'],
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}