<?php

namespace App\Http\Controllers;

use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Requests\Booking\UpdateBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\AuditLogger;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Booking::class, 'booking');
    }

    public function store(StoreBookingRequest $request, BookingService $service)
    {
        $booking = $service->createBooking($request->validated());

        AuditLogger::log('booking_created', 'Booking', $booking->id);

        return redirect()->back()->with('success', 'Booking created successfully.');
    }

    public function update(UpdateBookingRequest $request, Booking $booking, BookingService $service)
    {
        $service->updateBooking($booking, $request->validated());

        AuditLogger::log('booking_updated', 'Booking', $booking->id);

        return back()->with('success', 'Booking updated.');
    }

    public function destroy(Booking $booking, BookingService $service)
    {
        $service->cancelBooking($booking);

        AuditLogger::log('booking_cancelled', 'Booking', $booking->id);

        return back()->with('success', 'Booking cancelled.');
    }
}
