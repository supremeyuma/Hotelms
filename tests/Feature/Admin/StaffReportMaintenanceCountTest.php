<?php

namespace Tests\Feature\Admin;

use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffReportMaintenanceCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_loaded_with_maintenance_task_counts(): void
    {
        $staff = User::create([
            'name' => 'Maintenance User',
            'email' => 'maintenance@example.com',
            'password' => 'password',
        ]);

        $property = Property::create([
            'name' => 'Test Hotel',
            'location' => 'Lagos',
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Deluxe',
            'max_occupancy' => 2,
            'base_price' => 50000,
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Room 101',
            'status' => 'available',
        ]);

        MaintenanceTicket::create([
            'room_id' => $room->id,
            'staff_id' => $staff->id,
            'title' => 'Air conditioner issue',
            'description' => 'Cooling is intermittent.',
            'status' => 'open',
            'meta' => [],
        ]);

        $staffWithCount = User::query()
            ->withCount('maintenanceTasks')
            ->findOrFail($staff->id);

        $this->assertSame(1, $staffWithCount->maintenance_tasks_count);
    }
}
