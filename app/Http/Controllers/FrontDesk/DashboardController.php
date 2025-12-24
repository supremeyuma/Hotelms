<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Room;
use App\Models\GuestRequest;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Rooms KPIs
        $roomsOccupied = Room::where('status', 'occupied')->count();
        $roomsAvailable = Room::where('status', 'available')->count();

        // Today's arrivals and departures
        $today = Carbon::today();
        $guestsArriving = Booking::where('check_in', $today)->where('status', 'confirmed')->count();
        $guestsDeparting = Booking::where('check_out', $today)->where('status', 'active')->count();

        // Outstanding balances
        $outstandingBookings = Booking::with('charges', 'payments')
            ->get()
            ->filter(fn($b) => ($b->charges->sum('amount') - $b->payments->sum('amount')) > 0)
            ->count();

        // Recent guest requests
        $recentRequests = GuestRequest::where('status', 'pending')
            ->with('booking', 'room')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('FrontDesk/Dashboard', [
            'roomsOccupied' => $roomsOccupied,
            'roomsAvailable' => $roomsAvailable,
            'guestsArriving' => $guestsArriving,
            'guestsDeparting' => $guestsDeparting,
            'outstandingBookings' => $outstandingBookings,
            'recentRequests' => $recentRequests,
        ]);
    }
}
