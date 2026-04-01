<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class LocalHotelDemoSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = $this->seedRoles();
        $departments = $this->seedDepartments();
        $users = $this->seedUsers($roles, $departments);
        [$property, $roomTypes, $rooms] = $this->seedHotelStructure();

        $this->seedBookings($property->id, $roomTypes, $rooms, $users);
        $this->seedGuestRequests($rooms);
        $this->seedMaintenanceTickets($rooms);
        $this->seedRoomCleanings($rooms, $users);
        $this->seedOrders($rooms, $users);
    }

    protected function seedRoles(): array
    {
        $definitions = [
            'md' => 'Managing Director',
            'manager' => 'Hotel Manager',
            'frontdesk' => 'Front Desk',
            'clean' => 'Housekeeping',
            'laundry' => 'Laundry',
            'kitchen' => 'Kitchen',
            'bar' => 'Bar',
            'hr' => 'Human Resources',
            'accountant' => 'Accountant',
            'inventory' => 'Inventory',
            'staff' => 'Staff',
            'guest' => 'Guest',
        ];

        $roles = [];

        foreach ($definitions as $name => $label) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name],
                [
                    'slug' => $name,
                    'permissions' => json_encode(['label' => $label]),
                    'guard_name' => 'web',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $roles[$name] = DB::table('roles')->where('name', $name)->first();
        }

        return $roles;
    }

    protected function seedDepartments(): array
    {
        $names = [
            'Management',
            'Front Desk',
            'Housekeeping',
            'Laundry',
            'Kitchen',
            'Bar',
            'Human Resources',
            'Accounts',
            'Inventory',
        ];

        $departments = [];

        foreach ($names as $name) {
            $departments[$name] = Department::firstOrCreate(['name' => $name]);
        }

        return $departments;
    }

    protected function seedUsers(array $roles, array $departments): array
    {
        $definitions = [
            ['key' => 'md', 'name' => 'Executive Director', 'email' => 'md@email.com', 'role' => 'md', 'department' => 'Management', 'position' => 'Managing Director'],
            ['key' => 'manager', 'name' => 'Hotel Manager', 'email' => 'manager@email.com', 'role' => 'manager', 'department' => 'Management', 'position' => 'Operations Manager'],
            ['key' => 'frontdesk', 'name' => 'Front Desk Lead', 'email' => 'frontdesk@email.com', 'role' => 'frontdesk', 'department' => 'Front Desk', 'position' => 'Front Desk Supervisor'],
            ['key' => 'cleaner', 'name' => 'Housekeeping Lead', 'email' => 'clean@email.com', 'role' => 'clean', 'department' => 'Housekeeping', 'position' => 'Housekeeping Supervisor'],
            ['key' => 'laundry', 'name' => 'Laundry Lead', 'email' => 'laundry@email.com', 'role' => 'laundry', 'department' => 'Laundry', 'position' => 'Laundry Supervisor'],
            ['key' => 'kitchen', 'name' => 'Kitchen Lead', 'email' => 'kitchen@email.com', 'role' => 'kitchen', 'department' => 'Kitchen', 'position' => 'Kitchen Supervisor'],
            ['key' => 'bar', 'name' => 'Bar Lead', 'email' => 'bar@email.com', 'role' => 'bar', 'department' => 'Bar', 'position' => 'Bar Supervisor'],
            ['key' => 'hr', 'name' => 'HR Officer', 'email' => 'hr@email.com', 'role' => 'hr', 'department' => 'Human Resources', 'position' => 'HR Officer'],
            ['key' => 'accountant', 'name' => 'Account Officer', 'email' => 'accountant@email.com', 'role' => 'accountant', 'department' => 'Accounts', 'position' => 'Accountant'],
            ['key' => 'inventory', 'name' => 'Inventory Officer', 'email' => 'inventory@email.com', 'role' => 'inventory', 'department' => 'Inventory', 'position' => 'Inventory Officer'],
            ['key' => 'staff', 'name' => 'General Staff', 'email' => 'staff@email.com', 'role' => 'staff', 'department' => 'Management', 'position' => 'General Staff'],
            ['key' => 'guest1', 'name' => 'Guest One', 'email' => 'guest1@email.com', 'role' => 'guest'],
            ['key' => 'guest2', 'name' => 'Guest Two', 'email' => 'guest2@email.com', 'role' => 'guest'],
            ['key' => 'guest3', 'name' => 'Guest Three', 'email' => 'guest3@email.com', 'role' => 'guest'],
            ['key' => 'guest4', 'name' => 'Guest Four', 'email' => 'guest4@email.com', 'role' => 'guest'],
            ['key' => 'guest5', 'name' => 'Guest Five', 'email' => 'guest5@email.com', 'role' => 'guest'],
            ['key' => 'guest6', 'name' => 'Guest Six', 'email' => 'guest6@email.com', 'role' => 'guest'],
        ];

        $users = [];

        foreach ($definitions as $definition) {
            $user = User::withTrashed()->firstOrNew(['email' => $definition['email']]);

            $user->uuid = $user->uuid ?: (string) Str::uuid();
            $user->name = $definition['name'];
            $user->password = '11111111';
            $user->email_verified_at = now();
            $user->department_id = isset($definition['department']) ? $departments[$definition['department']]->id : null;
            $user->suspended_at = null;
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            $user->syncRoles([$definition['role']]);

            if (isset($definition['position'])) {
                $profile = StaffProfile::firstOrNew(
                    ['user_id' => $user->id],
                    [
                        'position' => $definition['position'],
                        'phone' => '+234800000' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                        'meta' => ['employment_status' => 'active'],
                    ]
                );

                $profile->storeActionCode('111111');
                $profile->save();
            }

            $users[$definition['key']] = $user;
        }

        return $users;
    }

    protected function seedHotelStructure(): array
    {
        $property = Property::updateOrCreate(
            ['name' => 'Local Demo Hotel'],
            [
                'location' => 'Ikeja, Lagos',
                'amenities' => ['wifi', 'pool', 'spa', 'restaurant', 'gym'],
            ]
        );

        $roomTypeDefinitions = [
            'Standard' => ['price' => 45000, 'occupancy' => 2, 'features' => ['wifi', 'tv', 'work desk']],
            'Deluxe' => ['price' => 70000, 'occupancy' => 2, 'features' => ['wifi', 'tv', 'balcony']],
            'Executive Suite' => ['price' => 120000, 'occupancy' => 4, 'features' => ['wifi', 'living area', 'minibar']],
        ];

        $roomTypes = [];

        foreach ($roomTypeDefinitions as $title => $definition) {
            $roomTypes[$title] = RoomType::updateOrCreate(
                ['property_id' => $property->id, 'title' => $title],
                [
                    'max_occupancy' => $definition['occupancy'],
                    'base_price' => $definition['price'],
                    'features' => $definition['features'],
                ]
            );
        }

        $roomDefinitions = [
            ['name' => 'Amber 101', 'code' => 'AMB101', 'type' => 'Standard', 'status' => 'occupied', 'floor' => 1],
            ['name' => 'Amber 102', 'code' => 'AMB102', 'type' => 'Standard', 'status' => 'occupied', 'floor' => 1],
            ['name' => 'Amber 103', 'code' => 'AMB103', 'type' => 'Standard', 'status' => 'available', 'floor' => 1],
            ['name' => 'Amber 104', 'code' => 'AMB104', 'type' => 'Standard', 'status' => 'available', 'floor' => 1],
            ['name' => 'Coral 201', 'code' => 'CRL201', 'type' => 'Deluxe', 'status' => 'occupied', 'floor' => 2],
            ['name' => 'Coral 202', 'code' => 'CRL202', 'type' => 'Deluxe', 'status' => 'available', 'floor' => 2],
            ['name' => 'Coral 203', 'code' => 'CRL203', 'type' => 'Deluxe', 'status' => 'available', 'floor' => 2],
            ['name' => 'Coral 204', 'code' => 'CRL204', 'type' => 'Deluxe', 'status' => 'occupied', 'floor' => 2],
            ['name' => 'Royal 301', 'code' => 'RYL301', 'type' => 'Executive Suite', 'status' => 'occupied', 'floor' => 3],
            ['name' => 'Royal 302', 'code' => 'RYL302', 'type' => 'Executive Suite', 'status' => 'available', 'floor' => 3],
            ['name' => 'Royal 303', 'code' => 'RYL303', 'type' => 'Executive Suite', 'status' => 'available', 'floor' => 3],
            ['name' => 'Royal 304', 'code' => 'RYL304', 'type' => 'Executive Suite', 'status' => 'available', 'floor' => 3],
        ];

        $rooms = [];

        foreach ($roomDefinitions as $definition) {
            $rooms[$definition['code']] = Room::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'display_name' => $definition['name'],
                    'slug' => Str::slug($definition['name']),
                    'property_id' => $property->id,
                    'room_type_id' => $roomTypes[$definition['type']]->id,
                    'status' => $definition['status'],
                    'floor' => $definition['floor'],
                    'meta' => ['wing' => str_contains($definition['code'], 'AMB') ? 'Amber' : (str_contains($definition['code'], 'CRL') ? 'Coral' : 'Royal')],
                ]
            );
        }

        return [$property, $roomTypes, $rooms];
    }

    protected function seedBookings(int $propertyId, array $roomTypes, array $rooms, array $users): void
    {
        $definitions = [
            [
                'code' => 'DEMO-1001',
                'room' => 'AMB101',
                'guest' => 'guest1',
                'status' => 'checked_in',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'check_in' => now()->subDay()->toDateString(),
                'check_out' => now()->addDays(2)->toDateString(),
                'created_at' => now()->subHours(8),
            ],
            [
                'code' => 'DEMO-1002',
                'room' => 'AMB102',
                'guest' => 'guest2',
                'status' => 'checked_in',
                'payment_status' => 'partial',
                'payment_method' => 'transfer',
                'check_in' => now()->subDays(2)->toDateString(),
                'check_out' => now()->toDateString(),
                'created_at' => now()->subHours(7),
            ],
            [
                'code' => 'DEMO-1003',
                'room' => 'CRL201',
                'guest' => 'guest3',
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'payment_method' => 'cash',
                'check_in' => now()->toDateString(),
                'check_out' => now()->addDays(2)->toDateString(),
                'created_at' => now()->subHours(6),
            ],
            [
                'code' => 'DEMO-1004',
                'room' => 'CRL204',
                'guest' => 'guest4',
                'status' => 'active',
                'payment_status' => 'unpaid',
                'payment_method' => 'transfer',
                'check_in' => now()->subDays(3)->toDateString(),
                'check_out' => now()->addDay()->toDateString(),
                'created_at' => now()->subHours(5),
            ],
            [
                'code' => 'DEMO-1005',
                'room' => 'RYL301',
                'guest' => 'guest5',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'check_in' => now()->toDateString(),
                'check_out' => now()->addDays(3)->toDateString(),
                'created_at' => now()->subHours(4),
            ],
            [
                'code' => 'DEMO-1006',
                'room' => 'CRL202',
                'guest' => 'guest6',
                'status' => 'confirmed',
                'payment_status' => 'unpaid',
                'payment_method' => 'transfer',
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(4)->toDateString(),
                'created_at' => now()->subHours(3),
            ],
        ];

        foreach ($definitions as $definition) {
            $room = $rooms[$definition['room']];
            $guest = $users[$definition['guest']];
            $roomType = $roomTypes[$room->roomType->title] ?? $roomTypes['Standard'];
            $nights = max(1, Carbon::parse($definition['check_in'])->diffInDays(Carbon::parse($definition['check_out'])));
            $totalAmount = $roomType->base_price * $nights;

            $bookingId = DB::table('bookings')->updateOrInsert(
                ['booking_code' => $definition['code']],
                [
                    'property_id' => $propertyId,
                    'room_id' => $room->id,
                    'room_type_id' => $roomType->id,
                    'user_id' => $guest->id,
                    'guest_name' => $guest->name,
                    'guest_email' => $guest->email,
                    'guest_phone' => '+234800100' . str_pad((string) $guest->id, 4, '0', STR_PAD_LEFT),
                    'nightly_rate' => $roomType->base_price,
                    'booking_code' => $definition['code'],
                    'check_in' => $definition['check_in'],
                    'check_out' => $definition['check_out'],
                    'guests' => 2,
                    'adults' => 2,
                    'children' => 0,
                    'quantity' => '1',
                    'total_amount' => $totalAmount,
                    'status' => $definition['status'],
                    'payment_method' => $definition['payment_method'],
                    'payment_status' => $definition['payment_status'],
                    'details' => json_encode(['source' => 'local_demo_seeder']),
                    'special_requests' => 'Late checkout if available',
                    'expires_at' => now()->addDay(),
                    'created_at' => $definition['created_at'],
                    'updated_at' => now(),
                ]
            );

            $booking = DB::table('bookings')->where('booking_code', $definition['code'])->first();

            DB::table('booking_rooms')->updateOrInsert(
                ['booking_id' => $booking->id, 'room_id' => $room->id],
                [
                    'rate_override' => $roomType->base_price,
                    'checked_in_at' => in_array($definition['status'], ['active', 'checked_in'], true) ? now()->subHours(6) : null,
                    'checked_out_at' => null,
                    'status' => in_array($definition['status'], ['active', 'checked_in'], true) ? 'checked_in' : 'reserved',
                    'created_at' => $definition['created_at'],
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function seedGuestRequests(array $rooms): void
    {
        $bookingIds = DB::table('bookings')->pluck('id', 'booking_code');

        $definitions = [
            ['booking' => 'DEMO-1001', 'room' => 'AMB101', 'type' => 'kitchen', 'status' => 'pending', 'notes' => 'Breakfast tray follow-up'],
            ['booking' => 'DEMO-1002', 'room' => 'AMB102', 'type' => 'laundry', 'status' => 'acknowledged', 'notes' => 'Guest requested express laundry'],
            ['booking' => 'DEMO-1004', 'room' => 'CRL204', 'type' => 'cleaning', 'status' => 'pending', 'notes' => 'Extra towels and full refresh'],
            ['booking' => 'DEMO-1005', 'room' => 'RYL301', 'type' => 'bar', 'status' => 'pending', 'notes' => 'Mini bar restock request'],
        ];

        foreach ($definitions as $definition) {
            DB::table('guest_requests')->updateOrInsert(
                [
                    'booking_id' => $bookingIds[$definition['booking']],
                    'room_id' => $rooms[$definition['room']]->id,
                    'type' => $definition['type'],
                ],
                [
                    'status' => $definition['status'],
                    'notes' => $definition['notes'],
                    'updated_at' => now(),
                    'created_at' => now()->subHours(2),
                ]
            );
        }
    }

    protected function seedMaintenanceTickets(array $rooms): void
    {
        $bookingIds = DB::table('bookings')->pluck('id', 'booking_code');

        $definitions = [
            ['booking' => 'DEMO-1001', 'room' => 'AMB101', 'type' => 'air_conditioning', 'status' => 'open', 'description' => 'Cooling is weak in the room'],
            ['booking' => 'DEMO-1004', 'room' => 'CRL204', 'type' => 'plumbing', 'status' => 'open', 'description' => 'Bathroom sink is leaking slowly'],
            ['booking' => 'DEMO-1005', 'room' => 'RYL301', 'type' => 'electrical', 'status' => 'closed', 'description' => 'Lamp fitting already repaired'],
        ];

        foreach ($definitions as $definition) {
            DB::table('maintenance_tickets')->updateOrInsert(
                [
                    'booking_id' => $bookingIds[$definition['booking']],
                    'room_id' => $rooms[$definition['room']]->id,
                    'type' => $definition['type'],
                ],
                [
                    'description' => $definition['description'],
                    'photo_path' => null,
                    'status' => $definition['status'],
                    'updated_at' => now(),
                    'created_at' => now()->subHours(4),
                ]
            );
        }
    }

    protected function seedRoomCleanings(array $rooms, array $users): void
    {
        $definitions = [
            ['room' => 'AMB103', 'status' => 'dirty'],
            ['room' => 'AMB104', 'status' => 'cleaning'],
            ['room' => 'CRL202', 'status' => 'cleaner_requested'],
            ['room' => 'RYL302', 'status' => 'clean'],
        ];

        foreach ($definitions as $definition) {
            DB::table('room_cleanings')->updateOrInsert(
                ['room_id' => $rooms[$definition['room']]->id],
                [
                    'staff_id' => $users['cleaner']->id,
                    'status' => $definition['status'],
                    'cleaned_at' => $definition['status'] === 'clean' ? now()->subHour() : null,
                    'updated_at' => now(),
                    'created_at' => now()->subHours(3),
                ]
            );
        }
    }

    protected function seedOrders(array $rooms, array $users): void
    {
        $bookingIds = DB::table('bookings')->pluck('id', 'booking_code');

        $definitions = [
            ['code' => 'ORD-DEMO-001', 'booking' => 'DEMO-1001', 'room' => 'AMB101', 'guest' => 'guest1', 'service_area' => 'kitchen', 'status' => 'pending', 'total' => 18500],
            ['code' => 'ORD-DEMO-002', 'booking' => 'DEMO-1002', 'room' => 'AMB102', 'guest' => 'guest2', 'service_area' => 'bar', 'status' => 'processing', 'total' => 9500],
            ['code' => 'ORD-DEMO-003', 'booking' => 'DEMO-1004', 'room' => 'CRL204', 'guest' => 'guest4', 'service_area' => 'kitchen', 'status' => 'processing', 'total' => 22000],
            ['code' => 'ORD-DEMO-004', 'booking' => 'DEMO-1005', 'room' => 'RYL301', 'guest' => 'guest5', 'service_area' => 'bar', 'status' => 'completed', 'total' => 12000],
        ];

        foreach ($definitions as $definition) {
            DB::table('orders')->updateOrInsert(
                ['order_code' => $definition['code']],
                [
                    'booking_id' => $bookingIds[$definition['booking']],
                    'room_id' => $rooms[$definition['room']]->id,
                    'user_id' => $users[$definition['guest']]->id,
                    'total' => $definition['total'],
                    'payment_method' => 'room_charge',
                    'payment_status' => $definition['status'] === 'completed' ? 'paid' : 'pending',
                    'payment_reference' => null,
                    'status' => $definition['status'],
                    'service_area' => $definition['service_area'],
                    'notes' => 'Local demo order',
                    'cancelable_until' => now()->addMinutes(15),
                    'completed_at' => $definition['status'] === 'completed' ? now()->subMinutes(20) : null,
                    'created_at' => now()->subHours(2),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
