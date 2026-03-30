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
                    'metric' => User::whereHas('roles', fn ($query) => $query->whereIn('name', ['hr', 'manager', 'staff', 'frontdesk', 'laundry', 'clean', 'kitchen', 'bar', 'inventory', 'accountant', 'Accountant']))->whereNull('suspended_at')->count(),
                    'secondary' => User::whereNotNull('suspended_at')->count() . ' suspended staff',
                    'route' => route('hr.staff.index'),
                ],
                [
                    'name' => 'Finance',
                    'metric' => Booking::whereIn('status', ['confirmed', 'active', 'checked_in'])->where('payment_status', '!=', 'paid')->count(),
                    'secondary' => 'NGN ' . number_format((float) Booking::whereDate('created_at', $today)->sum('total_amount'), 2),
                    'route' => route('finance.dashboard'),
                ],
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'rooms' => Room::count(),
                'occupied_rooms' => Room::where('status', 'occupied')->count(),
                'active_bookings' => Booking::whereDate('check_out', '>=', $today)->count(),
                'open_guest_requests' => GuestRequest::whereIn('status', ['pending', 'requested', 'acknowledged'])->count(),
                'open_maintenance' => MaintenanceTicket::whereNotIn('status', ['resolved', 'closed', 'completed'])->count(),
            ],
            'recentBookings' => Booking::latest()->limit(5)->get(),
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
