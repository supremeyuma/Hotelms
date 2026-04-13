<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Property;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportDashboardBookingDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_detail_outstanding_excludes_nightly_room_charges_from_extra_charges(): void
    {
        $mdRole = Role::create([
            'name' => 'md',
            'slug' => 'md',
            'guard_name' => 'web',
        ]);

        $superUserRole = Role::create([
            'name' => 'superuser',
            'slug' => 'superuser',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->syncRoles([$superUserRole->name, $mdRole->name]);

        $property = Property::create([
            'name' => 'Moorelife Resort',
            'location' => 'Lagos',
            'amenities' => ['wifi'],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 50000,
            'features' => ['wifi'],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Royal 101',
            'display_name' => 'Royal 101',
            'code' => 'R101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'BK-3001',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 2,
            'total_amount' => 50000,
            'status' => 'checked_in',
            'payment_method' => 'card',
            'payment_status' => 'partial',
            'nightly_rate' => 50000,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'checked_in',
            'rate_override' => 50000,
            'checked_in_at' => now()->subHours(4),
            'checked_out_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'description' => 'Nightly room charge',
            'amount' => 50000,
            'status' => 'unpaid',
            'payment_mode' => 'postpaid',
            'type' => 'nightly',
            'charge_date' => now()->toDateString(),
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => 50000,
            'amount_paid' => 50000,
            'currency' => 'NGN',
            'method' => 'card',
            'reference' => 'PAY-3001',
            'transaction_ref' => 'PAY-3001',
            'status' => 'completed',
            'provider' => 'manual',
            'payment_type' => 'booking',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.reports.dashboard', [
            'mode' => 'day',
            'day' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Hub')
            ->has('bookings', 1)
            ->where('bookings.0.booked_amount', 50000.0)
            ->where('bookings.0.extra_charges', 0.0)
            ->where('bookings.0.payments_received', 50000.0)
            ->where('bookings.0.outstanding_balance', 0.0)
        );
    }
}
