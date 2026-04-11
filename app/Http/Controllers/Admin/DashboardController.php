<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestRequest;
use App\Models\MaintenanceTicket;
use App\Models\Order;
use App\Models\RoomCleaning;
use App\Models\LaundryOrder;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        $roomsCount = Room::count();
        $occupiedRooms = Room::where('status', 'occupied')->count();
        $availableRooms = Room::where('status', 'available')->count();
        $arrivalsToday = Booking::whereDate('check_in', $today)
            ->whereNotIn('status', ['cancelled'])
            ->count();
        $departuresToday = Booking::whereDate('check_out', $today)
            ->whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->count();
        $openGuestRequests = GuestRequest::whereIn('status', ['pending', 'requested', 'acknowledged'])->count();
        $openMaintenance = MaintenanceTicket::whereNotIn('status', ['resolved', 'closed', 'completed'])->count();
        $cleaningBacklog = RoomCleaning::whereIn('status', ['dirty', 'cleaning', 'cleaner_requested'])->count();
        $pendingServiceOrders = Order::whereIn('status', ['pending', 'processing'])->count();
        $unsettledBookings = Booking::whereIn('status', ['confirmed', 'active', 'checked_in'])
            ->where('payment_status', '!=', 'paid')
            ->count();
        $activeStaffCount = User::whereHas('roles', fn ($query) => $query->whereIn('name', ['hr', 'manager', 'staff', 'frontdesk', 'laundry', 'clean', 'kitchen', 'bar', 'inventory', 'accountant', 'Accountant']))
            ->whereNull('suspended_at')
            ->count();
        $suspendedStaffCount = User::whereNotNull('suspended_at')->count();
        $occupancyRate = $roomsCount > 0
            ? round(($occupiedRooms / $roomsCount) * 100)
            : 0;

        $departmentSnapshots = null;

        if ($user && $user->hasRole('md')) {
            $departmentSnapshots = [
                [
                    'name' => 'Front Desk',
                    'metric' => Booking::whereDate('check_in', $today)->where('status', 'confirmed')->count(),
                    'secondary' => Booking::whereDate('check_out', $today)->whereIn('status', ['active', 'checked_in'])->count() . ' departures today',
                    'route' => route('frontdesk.dashboard'),
                ],
                [
                    'name' => 'Housekeeping',
                    'metric' => RoomCleaning::whereIn('status', ['dirty', 'cleaning', 'cleaner_requested'])->count(),
                    'secondary' => RoomCleaning::where('status', 'cleaning')->count() . ' rooms in progress',
                    'route' => route('clean.dashboard'),
                ],
                [
                    'name' => 'Laundry',
                    'metric' => LaundryOrder::whereNotIn('status', ['delivered', 'cancelled'])->count(),
                    'secondary' => LaundryOrder::where('status', 'requested')->count() . ' newly requested',
                    'route' => route('staff.laundry.dashboard'),
                ],
                [
                    'name' => 'Kitchen',
                    'metric' => Order::where('service_area', 'kitchen')->whereIn('status', ['pending', 'processing'])->count(),
                    'secondary' => Order::where('service_area', 'kitchen')->whereDate('created_at', $today)->count() . ' orders today',
                    'route' => route('staff.kitchen.dashboard'),
                ],
                [
                    'name' => 'Bar',
                    'metric' => Order::where('service_area', 'bar')->whereIn('status', ['pending', 'processing'])->count(),
                    'secondary' => Order::where('service_area', 'bar')->whereDate('created_at', $today)->count() . ' orders today',
                    'route' => route('staff.bar.dashboard'),
                ],
                [
                    'name' => 'HR',
                    'metric' => $activeStaffCount,
                    'secondary' => $suspendedStaffCount . ' suspended staff',
                    'route' => route('admin.staff.index'),
                ],
                [
                    'name' => 'Finance',
                    'metric' => Booking::whereIn('status', ['confirmed', 'active', 'checked_in'])->where('payment_status', '!=', 'paid')->count(),
                    'secondary' => '₦' . number_format((float) Booking::whereDate('created_at', $today)->sum('total_amount'), 2),
                    'route' => route('finance.dashboard'),
                ],
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'rooms' => $roomsCount,
                'occupied_rooms' => $occupiedRooms,
                'available_rooms' => $availableRooms,
                'occupancy_rate' => $occupancyRate,
                'active_bookings' => Booking::whereDate('check_out', '>=', $today)->count(),
                'arrivals_today' => $arrivalsToday,
                'departures_today' => $departuresToday,
                'open_guest_requests' => $openGuestRequests,
                'open_maintenance' => $openMaintenance,
                'cleaning_backlog' => $cleaningBacklog,
                'pending_service_orders' => $pendingServiceOrders,
                'unsettled_bookings' => $unsettledBookings,
            ],
            'todayLabel' => $today->format('l, d M Y'),
            'focusItems' => [
                [
                    'label' => 'Guest requests',
                    'value' => $openGuestRequests,
                    'helper' => 'Requests still waiting for action',
                    'route' => route('frontdesk.dashboard'),
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Maintenance tickets',
                    'value' => $openMaintenance,
                    'helper' => 'Issues not yet resolved',
                    'route' => route('admin.maintenance.index'),
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Cleaning backlog',
                    'value' => $cleaningBacklog,
                    'helper' => 'Dirty or in-progress rooms',
                    'route' => route('clean.dashboard'),
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Unsettled stays',
                    'value' => $unsettledBookings,
                    'helper' => 'Confirmed stays with payment still open',
                    'route' => route('admin.bookings.index'),
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Suspended staff',
                    'value' => $suspendedStaffCount,
                    'helper' => 'Team members who currently need review',
                    'route' => route('admin.staff.index', ['status' => 'suspended']),
                    'tone' => 'indigo',
                ],
            ],
            'quickLinks' => [
                [
                    'label' => 'Staff',
                    'description' => 'Open the staff directory, role assignments, and notes.',
                    'route' => route('admin.staff.index'),
                ],
                [
                    'label' => 'Bookings',
                    'description' => 'Review arrivals, departures, and stay status.',
                    'route' => route('admin.bookings.index'),
                ],
                [
                    'label' => 'Rooms',
                    'description' => 'Check room availability and inventory of spaces.',
                    'route' => route('admin.rooms.index'),
                ],
                [
                    'label' => 'Maintenance',
                    'description' => 'Follow unresolved room or facility issues.',
                    'route' => route('admin.maintenance.index'),
                ],
                [
                    'label' => 'Reports',
                    'description' => 'Open operational and performance reporting.',
                    'route' => route('admin.reports.dashboard'),
                ],
            ],
            'recentBookings' => Booking::with(['room:id,name', 'user:id,name'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'guest_name' => $booking->guest_name ?: optional($booking->user)->name ?: 'Walk-in guest',
                    'room_name' => $booking->room?->name ?: 'Unassigned',
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status ?: 'unpaid',
                    'total_amount' => (float) $booking->total_amount,
                    'check_in' => optional($booking->check_in)->format('d M'),
                    'check_out' => optional($booking->check_out)->format('d M'),
                ]),
            'isExecutive' => (bool) ($user && $user->hasRole('md')),
            'departmentSnapshots' => $departmentSnapshots,
            'reportLinks' => [
                [
                    'label' => 'Operations Reports',
                    'route' => route('admin.reports.dashboard'),
                ],
                [
                    'label' => 'Staff Reports',
                    'route' => route('admin.reports.staff'),
                ],
                [
                    'label' => 'Occupancy Reports',
                    'route' => route('admin.reports.occupancy'),
                ],
                [
                    'label' => 'Inventory Reports',
                    'route' => route('admin.reports.inventory'),
                ],
                [
                    'label' => 'Revenue Reports',
                    'route' => route('finance.reports.revenue'),
                ],
                [
                    'label' => 'Profit & Loss',
                    'route' => route('finance.reports.profit-loss'),
                ],
            ],
        ]);
    }
}
