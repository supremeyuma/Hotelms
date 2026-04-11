<?php
// ========================================================
// BookingController.php
// Namespace: App\Http\Controllers
// ========================================================
namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Setting;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\AuditLogger;
use App\Services\PaymentAccountingService;
use App\Services\PaymentProviderManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RoomAvailabilityService;
use App\Models\RoomType;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\DiscountCodeService;

class BookingController extends Controller
{
    protected BookingService $service;
    protected RoomAvailabilityService $availability;
    protected PricingService $pricingService;
    protected PaymentProviderManager $paymentManager;
    protected DiscountCodeService $discountCodeService;
    

    /*public function __construct(BookingService $service)
    {
        $this->service = $service;
        $this->middleware('auth')->only(['createBooking','confirmBooking','viewBooking','cancelBooking']);
    }*/

     public function __construct(
        RoomAvailabilityService $availability,
        BookingService $bookingService,
        PricingService $pricingService,
        PaymentProviderManager $paymentManager,
        DiscountCodeService $discountCodeService
    )
    {
        $this->availability = $availability;
        $this->bookingService = $bookingService;
        $this->pricingService = $pricingService;
        $this->paymentManager = $paymentManager;
        $this->discountCodeService = $discountCodeService;
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

        $roomTypes = RoomType::with('images')->get()->map(function ($roomType) use ($checkIn, $checkOut, $adults, $children) {
            // Skip room types that cannot accommodate requested guests
            if ($adults + $children > $roomType->max_occupancy) {
            // || $children > $roomType->max_children) {
                return null;
            }

            $availableRooms = $this->availability
                ->getAvailableRoomsForType($roomType->id, $checkIn, $checkOut)
                ->loadMissing('images')
                ->map(fn ($room) => $this->transformRoomForSelection($room, $roomType));

            return [
                'id' => $roomType->id,
                'name' => $roomType->title,
                'description' => $roomType->description,
                'price_per_night' => $roomType->base_price,
                'available_quantity' => $availableRooms->count(),
                'max_adults' => $roomType->max_occupancy,
                //'max_children' => $roomType->max_children,
                'amenities' => $roomType->features ?? [],
                'images' => $roomType->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'thumb_url' => $image->thumb_url,
                    'caption' => $image->caption,
                ])->values(),
                'available_rooms' => $availableRooms->values(),
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
            'image_settings' => $this->bookingImageSettings(),
        ]);
    }

    // Step 2b: select room
    public function selectRoom(Request $request)
    {
        $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'selected_room_ids' => 'required|array|min:1',
            'selected_room_ids.*' => 'integer|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
        ]);

        $selectedRoomIds = array_values(array_unique(array_map('intval', $request->input('selected_room_ids', []))));
        $quantity = count($selectedRoomIds);

        $available = $this->availability->areRoomsAvailable(
            $selectedRoomIds,
            $request->check_in,
            $request->check_out
        );

        if (!$available) {
            return back()->withErrors(['availability' => 'One or more selected rooms are no longer available.']);
        }

        $rooms = Room::with('roomType', 'images')
            ->whereIn('id', $selectedRoomIds)
            ->get();

        if ($rooms->count() !== $quantity || $rooms->pluck('room_type_id')->unique()->count() !== 1 || (int) $rooms->first()->room_type_id !== (int) $request->room_type_id) {
            return back()->withErrors(['availability' => 'Selected rooms do not match the room type you chose.']);
        }

        // Store selection in session
        Session::put('booking', [
            'room_type_id' => $request->room_type_id,
            'quantity' => $quantity,
            'selected_room_ids' => $selectedRoomIds,
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

        $paymentBookingId = null;

        if (! empty($booking['pending_booking_id'])) {
            $pendingBooking = Booking::with(['roomType.images', 'rooms.images'])
                ->find($booking['pending_booking_id']);

            if ($pendingBooking && $pendingBooking->status === 'pending_payment') {
                $booking = $this->buildBookingSessionPayload($pendingBooking);
                Session::put('booking', $booking);
                $paymentBookingId = $pendingBooking->id;
            } else {
                unset($booking['pending_booking_id']);
                Session::put('booking', $booking);
            }
        }

        $roomType = RoomType::with('images')->findOrFail($booking['room_type_id']);
        $selectedRooms = Room::with('roomType', 'images')
            ->whereIn('id', $booking['selected_room_ids'] ?? [])
            ->get();

        $checkIn = Carbon::parse($booking['check_in'])->startOfDay();
        $checkOut = Carbon::parse($booking['check_out'])->startOfDay();
        $nights = $checkIn->diffInDays($checkOut);
        $nights = $nights > 0 ? (int)$nights : 1;

        $totalPrice = $booking['total_amount'] ?? ($roomType->base_price * $nights * $booking['quantity']);

        return Inertia::render('Booking/Review', [
            'booking' => $booking,
            'room_type' => $roomType,
            'selected_rooms' => $selectedRooms->map(fn ($room) => $this->transformRoomForSelection($room, $roomType))->values(),
            'nights' => $nights,
            'total_price' => $totalPrice,
            'payment_booking_id' => $paymentBookingId,
            'discount_preview' => $booking['discount_preview'] ?? null,
            'image_settings' => $this->bookingImageSettings(),
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

        $basePrice = $roomType->base_price * $nights * $bookingData['quantity'];
        $discountPreview = null;

        if (! empty($bookingData['discount_code'])) {
            $discountPreview = $this->discountCodeService->previewForBookingSelection($bookingData, $bookingData['discount_code']);
        }

        $pricing = $discountPreview['pricing'] ?? $this->discountCodeService->buildPricing($basePrice);
        $totalPrice = $pricing['total'];
        
        //dd($bookingData, $totalPrice, $roomType, $nights);
        try {
            $booking = DB::transaction(function () use ($bookingData, $roomType, $pricing, $totalPrice, $discountPreview) {
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
                    'selected_room_ids' => $bookingData['selected_room_ids'] ?? [],
                    'total_amount' => $totalPrice,
                    'guest_name' => $bookingData['guest_name'],
                    'nightly_rate' => $roomType->base_price,
                    'status' => 'pending_payment',
                    'details' => $discountPreview ? [
                        'discount' => [
                            'discount_code_id' => $discountPreview['discount_code_id'],
                            'code' => $discountPreview['code'],
                            'name' => $discountPreview['name'],
                            'scope' => $discountPreview['scope'],
                            'discount_type' => $discountPreview['discount_type'],
                            'discount_value' => $discountPreview['discount_value'],
                            'discount_amount' => $discountPreview['discount_amount'],
                            'pricing' => $pricing,
                        ],
                    ] : null,
                ]);

                if ($discountPreview) {
                    $this->discountCodeService->reserveForBooking($booking, $discountPreview);
                }

                return $booking;
            });
        } catch (\Exception $e) {
            return redirect()->route('booking.search')->withErrors(['availability' => $e->getMessage()]);
        }

        // Redirect to payment page or mock payment
        // For now, simulate successful payment
        //$this->bookingService->confirmBooking($booking);

        Session::put('booking', array_merge(
            $this->buildBookingSessionPayload($booking),
            ['pending_booking_id' => $booking->id]
        ));

        //return redirect()->route('booking.confirmation', $booking->id);
        return redirect()->route('booking.payment', $booking);
    }

public function payment(Booking $booking)
{
    if ($booking->payment_status === 'paid' || $booking->status === 'confirmed') {
        Session::forget('booking');
        return redirect()->route('booking.confirmation', $booking);
    }

    abort_if($booking->status !== 'pending_payment', 404);

    Session::put('booking', array_merge(
        $this->buildBookingSessionPayload($booking),
        ['pending_booking_id' => $booking->id]
    ));

    return Inertia::render('Booking/Payment', [
        'booking' => $booking,
        'expires_at' => $booking->expires_at,
        'discount' => $this->discountCodeService->bookingDiscountSummary($booking),
    ]);
}

    public function confirmPayment(Booking $booking)
    {
        abort_if($booking->status !== 'pending_payment', 404);

        $booking->update([
            'payment_status' => 'pending',
            'payment_method' => 'offline',
        ]);

        $this->bookingService->confirmBooking($booking);
        Session::forget('booking');

        return redirect()->route('booking.confirmation', $booking);
    }

    public function paymentCallback(Request $request, Booking $booking)
    {
        if ($booking->payment_status === 'paid') {
            return redirect()->route('booking.confirmation', $booking)
                ->with('success', 'Payment already confirmed.');
        }

        abort_if($booking->status !== 'pending_payment', 404);

        $reference = $request->input('tx_ref')
            ?? $request->input('reference')
            ?? $booking->booking_code;
        $provider = strtolower((string) (
            $request->input('provider')
            ?? $booking->payments()->latest('id')->value('provider')
            ?? config('payment.default', 'flutterwave')
        ));

        try {
            $verification = $this->paymentManager->verifyPayment($reference, $provider);

            if (!($verification['success'] ?? false) || !($verification['verified'] ?? false)) {
                \Log::warning('Booking payment callback verification failed', [
                    'booking_id' => $booking->id,
                    'reference' => $reference,
                    'provider' => $provider,
                    'gateway_status' => $verification['status'] ?? null,
                ]);

                return redirect()->route('booking.payment', $booking)
                    ->with('error', 'We could not confirm your payment yet. If you were charged, please wait a moment and try again.');
            }

            $verifiedProvider = $verification['provider'] ?? $provider;
            $payment = $booking->payments()->updateOrCreate(
                ['reference' => $booking->booking_code],
                [
                    'provider' => $verifiedProvider,
                    'amount' => $booking->total_amount,
                    'amount_paid' => $this->normalizeVerifiedAmount($verification['amount'] ?? $booking->total_amount, $verifiedProvider),
                    'currency' => $verification['currency'] ?? 'NGN',
                    'status' => 'completed',
                    'payment_type' => 'booking',
                    'verified_at' => now(),
                    'external_reference' => $verification['data']['id'] ?? null,
                    'flutterwave_tx_ref' => $verifiedProvider === 'flutterwave' ? ($verification['reference'] ?? $reference) : null,
                    'flutterwave_tx_id' => $verifiedProvider === 'flutterwave' ? ($verification['data']['id'] ?? null) : null,
                    'flutterwave_tx_status' => $verifiedProvider === 'flutterwave' ? ($verification['status'] ?? ($verification['data']['status'] ?? null)) : null,
                    'raw_response' => $verification['data'] ?? $verification,
                    'paid_at' => now(),
                ]
            );

            resolve(PaymentAccountingService::class)->handleSuccessful($payment);

            $this->bookingService->markPaidAndConfirm($booking, $verifiedProvider);
            Session::forget('booking');

            return redirect()->route('booking.confirmation', $booking)
                ->with('success', 'Payment confirmed');
        } catch (\Exception $e) {
            \Log::error('Booking payment callback failed', [
                'booking_id' => $booking->id,
                'reference' => $reference,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('booking.payment', $booking)
                ->with('error', 'We could not confirm your payment right now. Please contact support if the issue continues.');
        }
    }

    protected function normalizeVerifiedAmount(mixed $amount, string $provider): float
    {
        $numericAmount = (float) $amount;

        if ($provider === 'paystack') {
            return $numericAmount / 100;
        }

        return $numericAmount;
    }


    // Step 6: Confirmation page
    public function confirmation(Booking $booking)
    {
         $booking->load(['roomType.images', 'rooms.images', 'payments']);
        return Inertia::render('Booking/Confirmation', [
            'booking' => $booking,
            'pre_check_in_url' => $booking->payment_status === 'paid'
                ? URL::temporarySignedRoute(
                    'booking.precheck.show',
                    Carbon::parse($booking->check_out)->endOfDay(),
                    ['booking' => $booking->id]
                )
                : null,
            'image_settings' => $this->bookingImageSettings(),
        ]);
    }

    public function preCheckIn(Request $request, Booking $booking)
    {
        abort_unless($booking->payment_status === 'paid', 403, 'Pre-check-in is only available for online paid bookings.');
        abort_if(in_array($booking->status, ['checked_out', 'cancelled'], true), 404);

        $details = $booking->details ?? [];
        $preCheckIn = $details['pre_check_in'] ?? [];

        return Inertia::render('Booking/PreCheckIn', [
            'booking' => $booking->load(['roomType', 'rooms.images']),
            'pre_check_in' => $preCheckIn,
            'can_submit' => ! in_array($booking->status, ['active', 'checked_in'], true),
            'signed_action' => URL::temporarySignedRoute(
                'booking.precheck.submit',
                Carbon::parse($booking->check_out)->endOfDay(),
                ['booking' => $booking->id]
            ),
        ]);
    }

    public function submitPreCheckIn(Request $request, Booking $booking)
    {
        abort_unless($booking->payment_status === 'paid', 403, 'Pre-check-in is only available for online paid bookings.');
        abort_if(in_array($booking->status, ['active', 'checked_in', 'checked_out', 'cancelled'], true), 403, 'This booking is no longer eligible for pre-check-in.');

        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'required|string|max:20',
            'estimated_arrival_time' => 'required|date_format:H:i',
            'arrival_notes' => 'nullable|string|max:500',
        ]);

        if (strcasecmp($booking->guest_email, $data['guest_email']) !== 0) {
            throw ValidationException::withMessages([
                'guest_email' => 'Use the same email address that was used for this booking.',
            ]);
        }

        $details = $booking->details ?? [];
        $details['pre_check_in'] = [
            'completed_at' => now()->toIso8601String(),
            'estimated_arrival_time' => $data['estimated_arrival_time'],
            'arrival_notes' => $data['arrival_notes'] ?? null,
            'source' => 'online_paid_booking',
        ];

        $booking->update([
            'guest_name' => $data['guest_name'],
            'guest_phone' => $data['guest_phone'],
            'details' => $details,
        ]);

        return redirect()->route('booking.precheck.show', ['booking' => $booking->id] + $request->query())
            ->with('success', 'Pre-check-in completed. Room access will still be issued by the front desk on arrival.');
    }

    protected function transformRoomForSelection(Room $room, ?RoomType $roomType = null): array
    {
        $roomType ??= $room->roomType;
        $roomImages = $room->images->map(fn ($image) => [
            'id' => $image->id,
            'url' => $image->url,
            'thumb_url' => $image->thumb_url,
            'caption' => $image->caption,
        ])->values();

        $fallbackImages = $roomType?->images?->map(fn ($image) => [
            'id' => $image->id,
            'url' => $image->url,
            'thumb_url' => $image->thumb_url,
            'caption' => $image->caption,
        ])->values() ?? collect();

        return [
            'id' => $room->id,
            'name' => $room->display_name ?? $room->name ?? ('Room ' . $room->id),
            'code' => $room->code,
            'floor' => $room->floor,
            'status' => $room->status,
            'meta' => $room->meta ?? [],
            'room_type_id' => $room->room_type_id,
            'images' => ($roomImages->isNotEmpty() ? $roomImages : $fallbackImages)->values(),
            'primary_image_url' => $roomImages->first()['url'] ?? $fallbackImages->first()['url'] ?? null,
        ];
    }

    protected function bookingImageSettings(): array
    {
        $settings = Setting::query()
            ->whereIn('key', ['booking_show_room_images', 'booking_show_room_type_images'])
            ->pluck('value', 'key');

        return [
            'show_room_images' => $settings->has('booking_show_room_images')
                ? filter_var($settings['booking_show_room_images'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'show_room_type_images' => $settings->has('booking_show_room_type_images')
                ? filter_var($settings['booking_show_room_type_images'], FILTER_VALIDATE_BOOLEAN)
                : true,
        ];
    }

    protected function buildBookingSessionPayload(Booking $booking): array
    {
        $discountSummary = $this->discountCodeService->bookingDiscountSummary($booking);

        return [
            'room_type_id' => $booking->room_type_id,
            'quantity' => (int) $booking->quantity,
            'selected_room_ids' => $booking->rooms()->pluck('rooms.id')->all(),
            'check_in' => optional($booking->check_in)->toDateString() ?? Carbon::parse($booking->check_in)->toDateString(),
            'check_out' => optional($booking->check_out)->toDateString() ?? Carbon::parse($booking->check_out)->toDateString(),
            'adults' => (int) $booking->adults,
            'children' => (int) ($booking->children ?? 0),
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'special_requests' => $booking->special_requests,
            'total_amount' => (float) $booking->total_amount,
            'discount_code' => $discountSummary['code'] ?? null,
            'discount_preview' => $discountSummary,
        ];
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
