<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DiscountCode;
use App\Models\Room;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\BookingService;
use App\Services\DiscountCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BookingsController extends Controller
{
    protected BookingService $bookingService;
    protected BillingService $billingService;
    protected DiscountCodeService $discountCodeService;

    public function __construct(BookingService $bookingService, BillingService $billingService, DiscountCodeService $discountCodeService)
    {
        $this->bookingService = $bookingService;
        $this->billingService = $billingService;
        $this->discountCodeService = $discountCodeService;
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

        if ($filter === 'pending_override') {
            $query->where('details->price_override->approval_status', 'pending');
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
            'roomDiscountCodeHint' => DiscountCode::scopeOptions()[DiscountCode::APPLIES_TO_ROOM_RATE],
            'priceOverrideSettings' => $this->priceOverrideSettings(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'selected_room_ids' => 'required|array|min:1',
            'selected_room_ids.*' => 'integer|exists:rooms,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'purpose_of_stay' => 'nullable|string|max:255',
            'special_requests' => 'nullable|string|max:1000',
            'discount_code' => 'nullable|string|max:50',
            'override_amount' => 'nullable|numeric|min:0',
            'override_reason' => 'nullable|string|max:500',
        ]);

        $selectedRooms = $this->resolveSelectedRooms($data['selected_room_ids']);
        $roomType = $selectedRooms->first()->roomType;
        $quantity = $selectedRooms->count();
        $discountPreview = null;
        $priceOverrideSettings = $this->priceOverrideSettings();

        if (! empty($data['discount_code'])) {
            $discountPreview = $this->discountCodeService->previewForBookingSelection([
                'room_type_id' => $roomType->id,
                'quantity' => $quantity,
                'selected_room_ids' => $selectedRooms->pluck('id')->all(),
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
            ], $data['discount_code']);
        }

        $booking = DB::transaction(function () use ($data, $roomType, $selectedRooms, $discountPreview, $quantity, $priceOverrideSettings) {
            $nights = max(
                now()->parse($data['check_in'])->startOfDay()->diffInDays(now()->parse($data['check_out'])->startOfDay()),
                1
            );
            $baseAmount = (float) $roomType->base_price * $nights * $quantity;
            $pricing = $discountPreview['pricing'] ?? $this->discountCodeService->buildPricing($baseAmount);
            $priceOverride = $this->buildPriceOverrideData(
                overrideAmount: $data['override_amount'] ?? null,
                originalAmount: (float) $pricing['total'],
                reason: $data['override_reason'] ?? null,
                settings: $priceOverrideSettings,
            );
            $details = $discountPreview ? [
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
            ] : [];

            if ($priceOverride) {
                $details['price_override'] = $priceOverride;
            }

            $booking = $this->bookingService->createBooking([
                ...$data,
                'room_type_id' => $roomType->id,
                'room_id' => $selectedRooms->first()->id,
                'selected_room_ids' => $selectedRooms->pluck('id')->all(),
                'quantity' => $quantity,
                'nightly_rate' => $roomType->base_price,
                'total_amount' => $priceOverride['override_amount'] ?? $pricing['total'],
                'status' => 'confirmed',
                'payment_status' => 'pending',
                'details' => $details !== [] ? $details : null,
            ]);

            if ($discountPreview) {
                $this->discountCodeService->reserveForBooking($booking, $discountPreview);
                $this->discountCodeService->markAppliedForBooking($booking);
            }

            return $booking;
        });

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
                        'id' => $room->roomType?->id,
                        'title' => $room->roomType?->title,
                        'base_price' => (float) ($room->roomType?->base_price ?? 0),
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
            'selected_room_ids' => 'required|array|min:1',
            'selected_room_ids.*' => 'integer|exists:rooms,id',
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

        $selectedRooms = $this->resolveSelectedRooms($data['selected_room_ids']);
        $roomType = $selectedRooms->first()->roomType;
        $quantity = $selectedRooms->count();
        $nights = max(
            now()->parse($data['check_in'])->startOfDay()->diffInDays(now()->parse($data['check_out'])->startOfDay()),
            1
        );
        $baseAmount = (float) $roomType->base_price * $nights * $quantity;
        $discountSummary = $this->discountCodeService->bookingDiscountSummary($booking->loadMissing('discountRedemption.discountCode'));
        $discountPreview = $discountSummary && ! empty($discountSummary['code'])
            ? $this->discountCodeService->previewForBookingSelection([
                'room_type_id' => $roomType->id,
                'quantity' => $quantity,
                'selected_room_ids' => $selectedRooms->pluck('id')->all(),
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
            ], $discountSummary['code'])
            : null;
        $pricing = $discountPreview['pricing'] ?? $this->discountCodeService->buildPricing($baseAmount);

        $booking = DB::transaction(function () use ($booking, $data, $selectedRooms, $roomType, $quantity, $pricing, $discountPreview, $discountSummary) {
            $booking = $this->bookingService->updateBooking($booking, [
                ...$data,
                'room_type_id' => $roomType->id,
                'room_id' => $selectedRooms->first()->id,
                'selected_room_ids' => $selectedRooms->pluck('id')->all(),
                'quantity' => $quantity,
                'nightly_rate' => $roomType->base_price,
                'total_amount' => $pricing['total'],
            ]);

            if ($discountPreview) {
                $this->discountCodeService->reserveForBooking($booking, $discountPreview);

                if (($booking->status === 'confirmed' || $booking->status === 'checked_in') && $discountSummary) {
                    $this->discountCodeService->markAppliedForBooking($booking);
                }
            }

            return $booking;
        });

        return redirect()->route('frontdesk.bookings.index')
            ->with('success', "Booking {$booking->booking_code} updated successfully.");
    }

    public function show(Booking $booking)
    {
        $this->bookingService->reconcilePaidBookingStates();
        $booking->refresh()->load(['room.roomType', 'rooms.roomType', 'charges', 'payments', 'orders', 'user']);
        $billing = $this->billingService->getBillingHistory($booking);
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
                'total_amount' => (float) $this->bookingService->effectiveBookingAmount($booking),
                'created_at' => optional($booking->created_at)?->toIso8601String(),
                'checked_in_rooms_count' => $booking->checked_in_rooms_count,
                'price_override' => $booking->price_override,
                'has_price_override' => $booking->has_price_override,
                'has_pending_price_override_approval' => $booking->has_pending_price_override_approval,
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
                'charges' => collect($billing['charges'])->map(fn ($charge) => [
                    'id' => $charge['id'],
                    'room_id' => $charge['room_id'],
                    'description' => $charge['description'],
                    'amount' => (float) $charge['amount'],
                ])->values(),
                'payments' => collect($billing['payments'])->map(fn ($payment) => [
                    'id' => $payment['id'],
                    'room_id' => $payment['room_id'],
                    'method' => $payment['method'],
                    'amount' => (float) $payment['amount'],
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

        if ($booking->has_pending_price_override_approval) {
            return back()->withErrors([
                'booking' => 'This booking has a pending price override and must be approved by a manager before check-in.',
            ]);
        }

        $this->bookingService->checkIn(
            $booking,
            $request->rooms,
            auth()->user()
        );

        return back()->with('success', 'Guest checked in successfully.');
    }

    public function checkOut(Booking $booking)
    {
        $checkoutDay = $booking->check_out?->startOfDay();
        $isOnOrPastCheckoutDay = $checkoutDay && $checkoutDay->lte(now()->startOfDay());

        $this->bookingService->checkOut($booking, null, $isOnOrPastCheckoutDay);

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
            'room_type_id' => $booking->room_type_id,
            'nightly_rate' => (float) ($booking->nightly_rate ?? 0),
            'selected_room_ids' => $booking->rooms->pluck('id')->values()->all(),
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
            'price_override' => $booking->price_override,
            'has_price_override' => $booking->has_price_override,
            'has_pending_price_override_approval' => $booking->has_pending_price_override_approval,
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

    protected function resolveSelectedRooms(array $selectedRoomIds)
    {
        $roomIds = array_values(array_unique(array_map('intval', $selectedRoomIds)));

        if ($roomIds === []) {
            throw ValidationException::withMessages([
                'selected_room_ids' => 'Select at least one room for this booking.',
            ]);
        }

        $rooms = Room::with('roomType')
            ->whereIn('id', $roomIds)
            ->get()
            ->sortBy(fn (Room $room) => array_search($room->id, $roomIds, true))
            ->values();

        if ($rooms->count() !== count($roomIds)) {
            throw ValidationException::withMessages([
                'selected_room_ids' => 'One or more selected rooms could not be found.',
            ]);
        }

        if ($rooms->pluck('room_type_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'selected_room_ids' => 'All selected rooms must belong to the same room type.',
            ]);
        }

        return $rooms;
    }

    protected function priceOverrideSettings(): array
    {
        return [
            'enabled' => filter_var(Setting::get('frontdesk_price_override_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'requires_approval' => filter_var(Setting::get('frontdesk_price_override_requires_approval', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    protected function buildPriceOverrideData(
        mixed $overrideAmount,
        float $originalAmount,
        ?string $reason,
        array $settings,
    ): ?array {
        if ($overrideAmount === null || $overrideAmount === '') {
            return null;
        }

        if (! ($settings['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'override_amount' => 'Front desk price override is currently disabled.',
            ]);
        }

        $normalizedOverride = round((float) $overrideAmount, 2);
        $normalizedOriginal = round($originalAmount, 2);

        if ($normalizedOverride === $normalizedOriginal) {
            return null;
        }

        $user = auth()->user();
        $requiresApproval = (bool) ($settings['requires_approval'] ?? false);
        $note = filled($reason) ? trim((string) $reason) : null;

        return [
            'original_amount' => $normalizedOriginal,
            'override_amount' => $normalizedOverride,
            'reason' => $note,
            'note' => $note,
            'requested_at' => now()->toIso8601String(),
            'requested_by_user_id' => $user?->id,
            'requested_by_name' => $user?->name,
            'approval_required' => $requiresApproval,
            'approval_status' => $requiresApproval ? 'pending' : 'approved',
            'approved_at' => $requiresApproval ? null : now()->toIso8601String(),
            'approved_by_user_id' => $requiresApproval ? null : $user?->id,
            'approved_by_name' => $requiresApproval ? null : $user?->name,
        ];
    }
}
