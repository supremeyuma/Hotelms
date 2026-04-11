<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Room;
use App\Models\GuestRequest;
use App\Services\BookingService;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function index(Request $request)
    {
        $this->bookingService->reconcilePaidBookingStates();

        $today = Carbon::today();

        // Rooms KPIs
        $roomsOccupied = Room::where('status', 'occupied')->count();
        $roomsAvailable = Room::where('status', 'available')->count();
        
        // Guests arriving / departing today
        $guestsArriving = Booking::where('check_in', $today)
            ->where('status', 'confirmed')
            ->count();
        $guestsDeparting = Booking::where('check_out', $today)
            ->whereIn('status', ['active', 'checked_in'])
            ->count();

        // Outstanding bookings
        $allBookings = Booking::with('charges', 'payments')->get();
        $outstandingBookingList = $allBookings->filter(function ($booking) {
            $charges = (float) $booking->charges->sum('amount');
            $payments = (float) $booking->payments->sum(function ($payment) {
                return $payment->amount_paid ?? $payment->amount;
            });
            $effectiveCharges = max($charges, (float) $booking->total_amount);

            return ($effectiveCharges - $payments) > 0;
        })->values();

        $outstandingBookings = $outstandingBookingList->count();

        // Recent guest requests
        $recentRequests = GuestRequest::latest()
            ->with('booking', 'room')
            ->take(20)
            ->get()
            ->filter(fn ($r) => $r->isFrontDeskVisible())
            ->values();

        return Inertia::render('FrontDesk/Dashboard', [
            'roomsOccupied' => $roomsOccupied,
            'roomsAvailable' => $roomsAvailable,
            'guestsArriving' => $guestsArriving,
            'guestsDeparting' => $guestsDeparting,
            'outstandingBookings' => $outstandingBookings,
            'outstandingBookingList' => $outstandingBookingList,
            'recentRequests' => $recentRequests,
        ]);
    }

}
