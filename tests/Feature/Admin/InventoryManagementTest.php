<?php

namespace Tests\Feature\Admin;

use App\Models\Charge;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_location_backfills_zero_stock_rows_for_existing_items(): void
    {
        $manager = $this->managerUser();

        $item = InventoryItem::create([
            'name' => 'Dishwashing Liquid',
            'sku' => 'INV-DL-001',
            'unit' => 'litres',
            'low_stock_threshold' => 5,
        ]);

        $response = $this->actingAs($manager)->post(route('admin.inventory-locations.store'), [
            'name' => 'Kitchen Store',
            'type' => InventoryLocation::TYPE_KITCHEN,
        ]);

        $response->assertRedirect(route('admin.inventory-locations.index'));

        $location = InventoryLocation::where('name', 'Kitchen Store')->firstOrFail();

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location->id,
            'quantity' => 0,
        ]);
    }

    public function test_inventory_service_transfers_and_reconciles_stock_in_the_ledger(): void
    {
        $service = $this->app->make(InventoryService::class);

        $mainStore = InventoryLocation::create([
            'name' => 'Main Store',
            'type' => InventoryLocation::TYPE_MAIN_STORE,
        ]);

        $kitchen = InventoryLocation::create([
            'name' => 'Kitchen',
            'type' => InventoryLocation::TYPE_KITCHEN,
        ]);

        $item = InventoryItem::create([
            'name' => 'Rice',
            'sku' => 'INV-RICE-001',
            'unit' => 'kg',
            'low_stock_threshold' => 2.5,
        ]);

        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $mainStore->id,
            'quantity' => 10,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
        ]);

        InventoryStock::create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $kitchen->id,
            'quantity' => 0,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
        ]);

        $service->transferStock($item, $mainStore, $kitchen, 4.5, null, 'Restock kitchen');
        $service->reconcileStock($item, $kitchen, 3.75, null, 'Physical count after prep');

        $this->assertEquals(5.5, (float) InventoryStock::where('inventory_item_id', $item->id)->where('inventory_location_id', $mainStore->id)->value('quantity'));
        $this->assertEquals(3.75, (float) InventoryStock::where('inventory_item_id', $item->id)->where('inventory_location_id', $kitchen->id)->value('quantity'));

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $mainStore->id,
            'type' => InventoryMovement::TYPE_TRANSFER_OUT,
            'reason' => 'Restock kitchen',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $kitchen->id,
            'type' => InventoryMovement::TYPE_TRANSFER_IN,
            'reason' => 'Restock kitchen',
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $kitchen->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reason' => 'Physical count after prep',
        ]);
    }

    public function test_kitchen_orders_do_not_transition_to_preparing_when_prepaid_charge_is_unpaid(): void
    {
        $manager = $this->managerUser();

        $propertyId = DB::table('properties')->insertGetId([
            'name' => 'Test Hotel',
            'location' => 'Lagos',
            'amenities' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomTypeId = DB::table('room_types')->insertGetId([
            'property_id' => $propertyId,
            'title' => 'Deluxe',
            'max_occupancy' => 2,
            'base_price' => 120000,
            'features' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomId = DB::table('rooms')->insertGetId([
            'property_id' => $propertyId,
            'room_type_id' => $roomTypeId,
            'name' => 'Room 101',
            'status' => 'available',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('bookings')->insertGetId([
            'property_id' => $propertyId,
            'room_id' => $roomId,
            'user_id' => $manager->id,
            'booking_code' => 'BK-001',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 1,
            'total_amount' => 50000,
            'status' => 'confirmed',
            'details' => json_encode([]),
            'nightly_rate' => 50000,
            'payment_method' => 'offline',
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::create([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'user_id' => $manager->id,
            'order_code' => 'ORD-INV-001',
            'total' => 12000,
            'status' => 'pending',
            'service_area' => 'kitchen',
            'notes' => 'Testing prepaid guard',
            'payment_method' => 'prepaid',
            'payment_status' => 'pending',
        ]);

        Charge::create([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'description' => 'Kitchen order',
            'amount' => 12000,
            'status' => 'unpaid',
            'payment_mode' => 'prepaid',
            'charge_date' => now()->toDateString(),
            'type' => 'room_service',
            'billable_id' => $order->id,
            'billable_type' => Order::class,
        ]);

        $response = $this->actingAs($manager)->patch(route('staff.kitchen.orders.updateStatus', $order), [
            'status' => 'preparing',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status);
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
}
