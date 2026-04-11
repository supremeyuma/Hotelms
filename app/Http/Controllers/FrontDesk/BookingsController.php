<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BillingService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
        $this->bookingService->reconcilePaidBookingStates();

        $search = $request->string('search')->toString();
        $filter = $request->string('filter')->toString();

        $query = Booking::query()->with('rooms');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%");
            });
        }

        if ($filter === 'confirmed') {
            $query->where('status', 'confirmed');
        }

        if ($filter === 'active') {
            $query->whereIn('status', ['active', 'checked_in']);
        }

        if ($filter === 'past') {
            $query->whereIn('status', ['checked_out', 'cancelled']);
        }

        if ($request->filled('check_in_date')) {
            $query->whereDate('check_in', $request->check_in_date);
        }

        if ($request->filled('check_out_date')) {
            $query->whereDate('check_out', $request->check_out_date);
        }

        return Inertia::render('FrontDesk/Bookings/Index', [
            'bookings' => $query->latest()->paginate(25)->withQueryString(),
            'search' => $search,
            'filter' => $filter,
            'date' => $request->check_in_date ?? $request->check_out_date,
            'dateType' => $request->filled('check_out_date') ? 'check_out' : 'check_in',
        ]);
    }

    public function create()
    {
        $rooms = Room::with('roomType')
            ->where('status', 'available')
            ->get();

        return Inertia::render('FrontDesk/Bookings/Create', [
            'rooms' => $rooms->map(function (Room $room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'room_number' => $room->room_number,
                    'room_type' => [
                        'id' => $room->roomType?->id,
                        'title' => $room->roomType?->title,
                        'base_price' => (float) ($room->roomType?->base_price ?? 0),
                    ],
                ];
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'purpose_of_stay' => 'nullable|string|max:255',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $booking = $this->bookingService->createBooking([
            ...$data,
            'status' => 'confirmed',
            'payment_status' => 'pending',
        ]);

        return redirect()->route('frontdesk.bookings.show', $booking)
            ->with('success', "Booking created successfully: {$booking->booking_code}");
    }

    public function edit(Booking $booking)
    {
        $this->bookingService->normalizeLegacyCheckedInStatuses();
        $booking->load(['room.roomType', 'rooms.roomType', 'user']);
        $rooms = Room::with('roomType')->where('status', 'available')->get();
        $details = $booking->details ?? [];
        $preCheckIn = $details['pre_check_in'] ?? null;

        return Inertia::render('FrontDesk/Bookings/Edit', [
            'booking' => $this->editPayload($booking),
            'preCheckIn' => $preCheckIn ? [
                'completed_at' => $preCheckIn['completed_at'] ?? null,
                'estimated_arrival_time' => $preCheckIn['estimated_arrival_time'] ?? null,
                'arrival_notes' => $preCheckIn['arrival_notes'] ?? null,
                'source' => $preCheckIn['source'] ?? null,
            ] : null,
            'rooms' => $rooms->map(function (Room $room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'room_number' => $room->room_number,
                    'status' => $room->status,
                    'room_type' => [
                        'title' => $room->roomType?->title,
                    ],
                ];
            })->values(),
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
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'purpose_of_stay' => 'nullable|string|max:255',
            'special_requests' => 'nullable|string|max:1000',
            'status' => 'required|in:pending_payment,confirmed,checked_in,checked_out,cancelled',
        ]);

        $this->bookingService->updateBooking($booking, $data);

        return redirect()->route('frontdesk.bookings.index')
            ->with('success', "Booking {$booking->booking_code} updated successfully.");
    }

    public function show(Booking $booking)
    {
        $this->bookingService->reconcilePaidBookingStates();
        $booking->refresh()->load(['room.roomType', 'rooms.roomType', 'charges', 'payments', 'orders', 'user']);
        $details = $booking->details ?? [];
        $preCheckIn = $details['pre_check_in'] ?? null;

        return Inertia::render('FrontDesk/Bookings/Show', [
            'booking' => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'guest_phone' => $booking->guest_phone,
                'emergency_contact_name' => $booking->emergency_contact_name,
                'emergency_contact_phone' => $booking->emergency_contact_phone,
                'purpose_of_stay' => $booking->purpose_of_stay,
                'special_requests' => $booking->special_requests,
                'check_in' => optional($booking->check_in)?->toDateString(),
                'check_out' => optional($booking->check_out)?->toDateString(),
                'status' => $booking->status === 'active' ? 'checked_in' : $booking->status,
                'total_amount' => (float) ($booking->total_amount ?? 0),
                'created_at' => optional($booking->created_at)?->toIso8601String(),
                'checked_in_rooms_count' => $booking->checked_in_rooms_count,
                'rooms' => $booking->rooms->map(function (Room $room) {
                    return [
                        'id' => $room->id,
                        'name' => $room->name,
                        'room_number' => $room->room_number,
                        'room_label' => trim(collect([
                            $room->roomType?->title,
                            $room->name ?: $room->room_number,
                        ])->filter()->implode(' - ')),
                        'pivot' => [
                            'checked_in_at' => optional($room->pivot->checked_in_at)?->toIso8601String(),
                            'checked_out_at' => optional($room->pivot->checked_out_at)?->toIso8601String(),
                        ],
                    ];
                })->values(),
                'charges' => $booking->charges->map(fn ($charge) => [
                    'id' => $charge->id,
                    'room_id' => $charge->room_id,
                    'description' => $charge->description,
                    'amount' => (float) $charge->amount,
                ])->values(),
                'payments' => $booking->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'room_id' => $payment->room_id,
                    'method' => $payment->method,
                    'amount' => (float) ($payment->amount_paid ?? $payment->amount),
                ])->values(),
                'assigned_room_options' => $booking->rooms->map(function (Room $room) {
                    return [
                        'id' => $room->id,
                        'label' => trim(collect([
                            $room->roomType?->title,
                            $room->name ?: $room->room_number,
                        ])->filter()->implode(' - ')),
                    ];
                })->values(),
                'has_multiple_rooms' => $booking->rooms->count() > 1,
            ],
            'preCheckIn' => $preCheckIn ? [
                'completed_at' => $preCheckIn['completed_at'] ?? null,
                'estimated_arrival_time' => $preCheckIn['estimated_arrival_time'] ?? null,
                'arrival_notes' => $preCheckIn['arrival_notes'] ?? null,
                'source' => $preCheckIn['source'] ?? null,
            ] : null,
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

    protected function editPayload(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'room_id' => $booking->room_id,
            'check_in' => optional($booking->check_in)?->toDateString(),
            'check_out' => optional($booking->check_out)?->toDateString(),
            'status' => $booking->status === 'active' ? 'checked_in' : $booking->status,
            'guest_name' => $booking->guest_name ?: optional($booking->user)->name ?: '',
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'adults' => (int) ($booking->adults ?? 1),
            'children' => (int) ($booking->children ?? 0),
            'emergency_contact_name' => $booking->emergency_contact_name,
            'emergency_contact_phone' => $booking->emergency_contact_phone,
            'purpose_of_stay' => $booking->purpose_of_stay,
            'special_requests' => $booking->special_requests,
            'assigned_room_options' => $booking->rooms->map(function (Room $room) {
                return [
                    'id' => $room->id,
                    'label' => trim(collect([
                        $room->roomType?->title,
                        $room->name ?: $room->room_number,
                    ])->filter()->implode(' - ')),
                ];
            })->values()->all(),
        ];
    }
}
