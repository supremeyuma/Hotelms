<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superuser_with_md_access_can_open_finance_dashboard_with_room_balances(): void
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
            'booking_code' => 'BK-1001',
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
            'description' => 'Room charge',
            'amount' => 50000,
            'status' => 'unpaid',
            'payment_mode' => 'postpaid',
            'type' => 'manual',
            'charge_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('finance.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Dashboard')
            ->where('mode', 'finance')
            ->where('kpis.outstanding.count', 1)
        );
    }

    public function test_superuser_with_md_access_can_open_outstanding_balances_room_view(): void
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
            'title' => 'Standard',
            'max_occupancy' => 2,
            'base_price' => 30000,
            'features' => ['wifi'],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Amber 201',
            'display_name' => 'Amber 201',
            'code' => 'A201',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'BK-2001',
            'check_in' => now()->subDays(2)->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 1,
            'total_amount' => 30000,
            'status' => 'active',
            'payment_method' => 'transfer',
            'payment_status' => 'partial',
            'nightly_rate' => 30000,
            'guest_name' => 'Another Guest',
            'guest_email' => 'another@example.com',
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'checked_in',
            'rate_override' => 30000,
            'checked_in_at' => now()->subDay(),
            'checked_out_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'description' => 'Mini bar charge',
            'amount' => 12000,
            'status' => 'unpaid',
            'payment_mode' => 'postpaid',
            'type' => 'manual',
            'charge_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('finance.outstanding-balances.index', ['view' => 'room']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/OutstandingBalances')
            ->where('view', 'room')
            ->where('summary.total_rooms', 1)
        );
    }
}
