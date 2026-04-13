<?php

namespace Tests\Feature\Staff;

use App\Models\Booking;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontdesk_can_create_a_kitchen_order_for_an_occupied_room(): void
    {
        $frontdesk = $this->staffUserWithRole('frontdesk');
        [$room, $booking] = $this->occupiedRoomFixture();
        $item = $this->menuItem('kitchen', 'Club Sandwich', 8500);

        $response = $this->actingAs($frontdesk)->post(route('staff.kitchen.orders.store'), [
            'room_id' => $room->id,
            'notes' => 'Guest called from the suite phone.',
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'quantity' => 2,
                    'note' => 'Extra fries',
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame($booking->id, $order->booking_id);
        $this->assertSame($room->id, $order->room_id);
        $this->assertSame('kitchen', $order->service_area);
        $this->assertSame('pending_selection', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);
        $this->assertEquals(17000.00, (float) $order->total);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'item_name' => 'Club Sandwich',
            'qty' => 2,
            'note' => 'Extra fries',
        ]);

        $this->assertDatabaseHas('charges', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'billable_type' => $order->getMorphClass(),
            'billable_id' => $order->id,
            'status' => 'unpaid',
            'type' => 'kitchen',
        ]);
    }

    public function test_frontdesk_can_update_bar_order_payment_status_and_method(): void
    {
        $frontdesk = $this->staffUserWithRole('frontdesk');
        [$room, $booking] = $this->occupiedRoomFixture();

        $order = Order::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'order_code' => 'ORD-BAR-1',
            'service_area' => 'bar',
            'status' => 'pending',
            'total' => 6000,
            'payment_method' => 'pending_selection',
            'payment_status' => 'pending',
        ]);

        $order->items()->create([
            'item_name' => 'Virgin Mojito',
            'qty' => 2,
            'price' => 3000,
        ]);

        $order->charge()->create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'description' => 'Bar Order ORD-BAR-1',
            'amount' => 6000,
            'status' => 'unpaid',
            'payment_mode' => 'postpaid',
            'charge_date' => now()->toDateString(),
            'type' => 'bar',
        ]);

        $response = $this->actingAs($frontdesk)->patch(route('staff.bar.orders.updatePayment', $order), [
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertNotEmpty($order->payment_reference);

        $this->assertDatabaseHas('charges', [
            'billable_type' => $order->getMorphClass(),
            'billable_id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'reference' => $order->payment_reference,
            'method' => 'cash',
            'amount' => 6000,
            'amount_paid' => 6000,
            'provider' => 'manual',
            'payment_type' => 'order',
            'status' => 'completed',
        ]);
    }

    protected function occupiedRoomFixture(): array
    {
        $property = Property::create([
            'name' => 'Moorelife Resort',
            'location' => 'Lagos',
            'amenities' => [],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive Suite',
            'max_occupancy' => 2,
            'base_price' => 120000,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 401',
            'status' => 'occupied',
            'meta' => [],
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'BOOK-401',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 2,
            'guest_name' => 'Ada Obi',
            'guest_email' => 'ada@example.com',
            'guest_phone' => '08030000000',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => 120000,
            'total_amount' => 120000,
            'status' => 'checked_in',
            'payment_status' => 'pending',
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'active',
            'checked_in_at' => now(),
        ]);

        return [$room, $booking];
    }

    protected function menuItem(string $area, string $name, float $price): MenuItem
    {
        $category = MenuCategory::create([
            'name' => ucfirst($area) . ' Specials',
            'type' => $area,
            'is_active' => true,
        ]);

        return MenuItem::create([
            'menu_category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'is_available' => true,
            'service_area' => $area,
        ]);
    }

    protected function staffUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ], [
            'slug' => $roleName,
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
