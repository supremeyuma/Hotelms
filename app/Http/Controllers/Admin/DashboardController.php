<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Room;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'rooms' => Room::count(),
                'occupied_rooms' => Room::where('status', 'occupied')->count(),
                'active_bookings' => Booking::whereDate('check_out', '>=', Carbon::today())->count(),
                'revenue_today' => Order::whereDate('created_at', Carbon::today())->sum('total'),
            ],
            'recentBookings' => Booking::latest()->limit(5)->get(),
        ]);
    }
}
