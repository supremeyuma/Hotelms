<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\BillingService;

class BookingsController extends Controller
{
    protected BookingService $bookingService;
    protected BillingService $billingService;

    public function __construct(BookingService $bookingService, BillingService $billingService)
    {
        $this->bookingService = $bookingService;
        $this->billingService = $billingService;
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $filter = $request->string('filter')->toString();

        $query = Booking::query()->with('rooms');

        // SEARCH
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                ->orWhere('guest_name', 'like', "%{$search}%");
            });
        }

        // STATUS FILTER
        if ($filter === 'confirmed') {
            $query->where('status', 'confirmed');
        }

        if ($filter === 'active') {
            $query->whereIn('status', ['active', 'checked_in']);
        }

        if ($filter === 'past') {
            $query->whereIn('status', ['checked_out', 'cancelled']);
        }

        // DATE FILTERS
        if ($request->filled('check_in_date')) {
            $query->whereDate('check_in', $request->check_in_date);
        }

        if ($request->filled('check_out_date')) {
            $query->whereDate('check_out', $request->check_out_date);
        }

        return Inertia::render('FrontDesk/Bookings/Index', [
            'bookings' => $query->latest()->paginate(25)->withQueryString(),
            'search'   => $search,
            'filter'   => $filter,
            'date'     => $request->check_in_date ?? $request->check_out_date,
            'dateType' => $request->filled('check_out_date') ? 'check_out' : 'check_in',
        ]);
    }



    public function create()
    {
        $rooms = Room::with('roomType')
            ->where('status', 'available')
            ->get();
        //dd($rooms);
        return Inertia::render('FrontDesk/Bookings/Create', ['rooms' => $rooms]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'room_id' => 'nullable|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'notes' => 'nullable|string|max:1000',
        ]);

        $booking = $this->bookingService->createBooking($data, auth()->user());

        return redirect()->route('frontdesk.bookings.index')
            ->with('success', "Booking created successfully: {$booking->booking_code}");
    }

    public function edit(Booking $booking)
    {
        $rooms = Room::with('roomType')
            ->where('status', 'available')
            ->get();
                return Inertia::render('FrontDesk/Bookings/Edit', [
            'booking' => $booking->load('room'),
            'rooms' => $rooms
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'room_id' => 'nullable|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:pending_payment,confirmed,active,checked_out,cancelled',
        ]);

        $this->bookingService->updateBooking($booking, $data);

        return redirect()->route('frontdesk.bookings.index')
            ->with('success', "Booking {$booking->booking_code} updated successfully.");
    }

    public function show(Booking $booking)
    {
        return Inertia::render('FrontDesk/Bookings/Show', [
            'booking' => $booking->load([
                'room',
                'rooms',
                'charges',   // ✅ correct relationship
                'payments',
                'orders',
                'user',
            ]),
        ]);
    }


    public function checkIn(Request $request, Booking $booking)
    {
        $request->validate([
            'rooms' => 'nullable|integer|min:1',
        ]);

        $this->bookingService->checkIn(
            $booking,
            $request->rooms,
            auth()->user()
        );

        return back()->with('success', 'Guest checked in successfully.');
    }

    public function checkOut(Booking $booking)
    {
        $this->bookingService->checkOut($booking);

        return back()->with('success', "Guest {$booking->guest_name} checked out successfully.");
    }

    public function extendStay(Request $request, Booking $booking)
    {
        $request->validate([
            'new_checkout' => 'required|date|after:' . $booking->check_out,
        ]);

        $this->bookingService->extendStay($booking, $request->new_checkout);

        return back()->with('success', "Booking {$booking->booking_code} extended successfully.");
    }
}
