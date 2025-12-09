<?php
// ========================================================
// BookingController.php
// Namespace: App\Http\Controllers
// ========================================================
namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    protected BookingService $service;

    public function __construct(BookingService $service)
    {
        $this->service = $service;
        $this->middleware('auth')->only(['createBooking','confirmBooking','viewBooking','cancelBooking']);
    }

    /**
     * Check availability for room(s) between dates.
     */
    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        $room = Room::findOrFail($data['room_id']);

        // naive availability check: bookings overlapping
        $overlap = Booking::where('room_id', $room->id)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q->whereBetween('check_in', [$data['check_in'],$data['check_out']])
                                ->orWhereBetween('check_out', [$data['check_in'],$data['check_out']])
                                ->orWhere(fn($q2) => $q2->where('check_in', '<=', $data['check_in'])
                                                         ->where('check_out', '>=', $data['check_out'])))
            ->exists();

        return response()->json(['available' => !$overlap]);
    }

    /**
     * Create booking (store)
     */
    public function createBooking(BookingRequest $request)
    {
        $payload = $request->validated();

        $booking = $this->service->createBooking($payload);

        AuditLogger::log('booking_created', 'Booking', $booking->id, [
            'payload' => $payload
        ]);

        return redirect()->route('booking.confirm', ['booking' => $booking->id]);
    }

    /**
     * Confirm booking (view confirmation)
     */
    public function confirmBooking(Booking $booking)
    {
        $booking->load(['room.roomType','user']);
        return Inertia::render('Public/BookingConfirmation', [
            'booking' => $booking
        ]);
    }

    /**
     * View booking (for guest or staff)
     */
    public function viewBooking(Booking $booking)
    {
        $this->authorize('view', $booking);
        $booking->load(['room','payments','orders','user']);
        return Inertia::render('Bookings/View', compact('booking'));
    }

    /**
     * Cancel booking (soft delete or status)
     */
    public function cancelBooking(Booking $booking)
    {
        $this->authorize('delete', $booking);

        $this->service->cancelBooking($booking);

        AuditLogger::log('booking_cancelled', 'Booking', $booking->id);

        return back()->with('success', 'Booking cancelled.');
    }
}
