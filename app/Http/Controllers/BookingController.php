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
use App\Services\RoomAvailabilityService;
use App\Models\RoomType;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class BookingController extends Controller
{
    protected BookingService $service;
    protected RoomAvailabilityService $availability;
    

    /*public function __construct(BookingService $service)
    {
        $this->service = $service;
        $this->middleware('auth')->only(['createBooking','confirmBooking','viewBooking','cancelBooking']);
    }*/

     public function __construct(RoomAvailabilityService $availability, BookingService $bookingService)
    {
        $this->availability = $availability;
        $this->bookingService = $bookingService;
    }


    // Step 1: Show search form
    public function searchForm()
    {
        return Inertia::render('Booking/SearchForm');
    }

    // Step 2: Display available rooms
    public function availableRooms(Request $request)
    {
        $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
        ]);

        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $adults = $request->input('adults');
        $children = $request->input('children', 0);

        $roomTypes = RoomType::all()->map(function ($roomType) use ($checkIn, $checkOut, $adults, $children) {
            // Skip room types that cannot accommodate requested guests
            if ($adults + $children > $roomType->max_occupancy) {
            // || $children > $roomType->max_children) {
                return null;
            }

            return [
                'id' => $roomType->id,
                'name' => $roomType->title,
                'description' => $roomType->description,
                'price_per_night' => $roomType->base_price,
                'available_quantity' => $this->availability->availableRooms($roomType->id, $checkIn, $checkOut),
                'max_adults' => $roomType->max_occupancy,
                //'max_children' => $roomType->max_children,
                'amenities' => $roomType->features ?? [],
            ];
        })->filter()->values(); // remove nulls

        //dd($roomTypes);

        if ($roomTypes->isEmpty()) {
            throw ValidationException::withMessages([
                'availability' => 'No rooms available for the selected dates and occupancy.'
            ]);
        }

        return Inertia::render('Booking/RoomList', [
            'roomTypes' => $roomTypes,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => (int)$adults,
            'children' => (int)$children,
        ]);
    }

    // Step 2b: select room
    public function selectRoom(Request $request)
    {
        $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'quantity' => 'required|integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
        ]);

        $available = $this->availability->checkAvailability(
            $request->room_type_id,
            $request->check_in,
            $request->check_out,
            $request->quantity
        );

        if (!$available) {
            return back()->withErrors(['availability' => 'Selected room is no longer available']);
        }

        // Store selection in session
        Session::put('booking', [
            'room_type_id' => $request->room_type_id,
            'quantity' => $request->quantity,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
            'adults' => $request->adults,
            'children' => $request->children,
        ]);

        return redirect()->route('booking.guest');
    }

    // Step 3: Guest details form
    public function guestDetails()
    {
        $booking = Session::get('booking');
        if (!$booking) return redirect()->route('booking.search');

        return Inertia::render('Booking/GuestDetails', ['booking' => $booking]);
    }

    // Submit guest details
    public function submitGuestDetails(Request $request)
    {
        $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email',
            'guest_phone' => 'required|string|max:20',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $booking = Session::get('booking');
        if (!$booking) return redirect()->route('booking.search');

        $booking = array_merge($booking, $request->only(['guest_name','guest_email','guest_phone','special_requests']));

        // Store updated booking in session
        Session::put('booking', $booking);

        return redirect()->route('booking.review');
    }

    // Step 4: Review page
    public function reviewBooking()
    {
        $booking = Session::get('booking');
        if (!$booking) return redirect()->route('booking.search');

        $roomType = RoomType::findOrFail($booking['room_type_id']);
        
        $checkIn = Carbon::parse($booking['check_in'])->startOfDay();
        $checkOut = Carbon::parse($booking['check_out'])->startOfDay();

        // Calculate nights between check-in and check-out
        $nights = $checkIn->diffInDays($checkOut);

        // Fallback to 1 night if for some reason the dates are the same
        $nights = $nights > 0 ? (int)$nights : 1;

        $totalPrice = $roomType->base_price * $nights * $booking['quantity'];
        //dd($roomType);
        return Inertia::render('Booking/Review', [
            'booking' => $booking,
            'room_type' => $roomType,
            'nights' => $nights,
            'total_price' => $totalPrice,
            
        ]);
    }

    // Step 5: Create pending booking
    public function createBooking()
    {
        $bookingData = Session::get('booking');
        if (!$bookingData) {
            return redirect()->route('booking.search');
        }
        

        $roomType = RoomType::findOrFail($bookingData['room_type_id']);

        $nights = \Carbon\Carbon::parse($bookingData['check_in'])
            ->diffInDays(\Carbon\Carbon::parse($bookingData['check_out']));

        $totalPrice = $roomType->base_price * $nights * $bookingData['quantity'];
        //dd($bookingData, $totalPrice, $roomType, $nights);
        try {
            $booking = $this->bookingService->createBooking([
                'room_type_id' => $bookingData['room_type_id'],
                'check_in' => $bookingData['check_in'],
                'check_out' => $bookingData['check_out'],
                'adults' => $bookingData['adults'],
                'children' => $bookingData['children'] ?? 0,
                'guest_email' => $bookingData['guest_email'],
                'guest_phone' => $bookingData['guest_phone'],
                'special_requests' => $bookingData['special_requests'] ?? null,
                'quantity' => $bookingData['quantity'],
                'total_amount' => $totalPrice,
                'guest_name' => $bookingData['guest_name'],
                'nightly_rate' => $roomType->base_price,
                'status' => 'pending_payment',
                
            ]);
        } catch (\Exception $e) {
            return redirect()->route('booking.search')->withErrors(['availability' => $e->getMessage()]);
        }

        // Redirect to payment page or mock payment
        // For now, simulate successful payment
        //$this->bookingService->confirmBooking($booking);

        // Clear session
        Session::forget('booking');

        //return redirect()->route('booking.confirmation', $booking->id);
        return redirect()->route('booking.payment', $booking);
    }

    public function payment(Booking $booking)
{
    abort_if($booking->status !== 'pending_payment', 404);

    return Inertia::render('Booking/Payment', [
        'booking' => $booking,
        'expires_at' => $booking->expires_at,
    ]);
}

    public function confirmPayment(Booking $booking)
    {
        abort_if($booking->status !== 'pending_payment', 404);

        $this->bookingService->confirmBooking($booking);

        return redirect()->route('booking.confirmation', $booking);
    }


    // Step 6: Confirmation page
    public function confirmation(Booking $booking)
    {
         $booking->load('roomType', 'rooms');
        return Inertia::render('Booking/Confirmation', [
            'booking' => $booking,
        ]);
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
    /*public function createBooking(BookingRequest $request)
    {
        $payload = $request->validated();

        $booking = $this->service->createBooking($payload);

        AuditLogger::log('booking_created', 'Booking', $booking->id, [
            'payload' => $payload
        ]);

        return redirect()->route('booking.confirm', ['booking' => $booking->id]);
    }*/

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
