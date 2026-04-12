<?php
// ========================================================
// Admin\BookingAdminController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Services\BillingService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class BookingAdminController extends Controller
{
    protected BookingService $service;
    protected BillingService $billingService;

    public function __construct(BookingService $service, BillingService $billingService)
    {
        $this->middleware(['auth', 'role:manager|md|superuser']);
        $this->service = $service;
        $this->billingService = $billingService;
    }

    public function index(Request $request)
    {
        $this->service->reconcilePaidBookingStates();

        $today = Carbon::today();
        $search = trim($request->string('search')->toString());
        $filter = $request->string('filter')->toString();
        $dateType = $request->string('dateType')->toString() === 'check_out' ? 'check_out' : 'check_in';
        $date = $dateType === 'check_out'
            ? $request->string('check_out_date')->toString()
            : $request->string('check_in_date')->toString();
        $allowedFilters = ['arrivals_today', 'departures_today', 'in_house', 'unsettled', 'pending_override'];

        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $bookings = Booking::with(['room.roomType', 'roomType', 'rooms.roomType', 'user'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($bookingQuery) use ($search) {
                    $bookingQuery->where('booking_code', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhere('guest_phone', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('rooms', function ($roomQuery) use ($search) {
                            $roomQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('room_number', 'like', "%{$search}%")
                                ->orWhere('display_name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('room', function ($roomQuery) use ($search) {
                            $roomQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('room_number', 'like', "%{$search}%")
                                ->orWhere('display_name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filter === 'arrivals_today', function ($query) use ($today) {
                $query->whereDate('check_in', $today)
                    ->whereNotIn('status', ['cancelled']);
            })
            ->when($filter === 'in_house', function ($query) use ($today) {
                $query->whereDate('check_in', '<=', $today)
                    ->whereDate('check_out', '>=', $today)
                    ->whereIn('status', ['confirmed', 'active', 'checked_in']);
            })
            ->when($filter === 'departures_today', function ($query) use ($today) {
                $query->whereDate('check_out', $today)
                    ->whereNotIn('status', ['cancelled']);
            })
            ->when($filter === 'unsettled', function ($query) {
                $query->whereIn('status', ['confirmed', 'active', 'checked_in'])
                    ->where('payment_status', '!=', 'paid');
            })
            ->when($filter === 'pending_override', function ($query) {
                $query->where('details->price_override->approval_status', 'pending');
            })
            ->when($date !== '', function ($query) use ($date, $dateType) {
                $query->whereDate($dateType, $date);
            })
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(function (Booking $booking) use ($today) {
                $assignedRooms = $booking->rooms
                    ->map(function ($room) {
                        $roomTypeTitle = $room->roomType?->title;

                        return trim(collect([
                            $roomTypeTitle,
                            $room->name ?: $room->room_number,
                        ])->filter()->implode(' - '));
                    })
                    ->filter()
                    ->values();

                $primaryRoomLabel = $assignedRooms->first()
                    ?: trim(collect([
                        $booking->roomType?->title ?: $booking->room?->roomType?->title,
                        $booking->room?->name ?: $booking->room?->room_number,
                    ])->filter()->implode(' - '))
                    ?: 'Unassigned';

                $guestCount = (int) ($booking->guests ?: (($booking->adults ?? 0) + ($booking->children ?? 0)));
                $nights = ($booking->check_in && $booking->check_out)
                    ? max($booking->check_in->diffInDays($booking->check_out), 1)
                    : 0;

                return [
                    'id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'guest_name' => $booking->guest_name ?: optional($booking->user)->name ?: 'Walk-in guest',
                    'guest_email' => $booking->guest_email,
                    'guest_phone' => $booking->guest_phone,
                    'room_label' => $primaryRoomLabel,
                    'room_labels' => $assignedRooms->isNotEmpty() ? $assignedRooms->all() : [$primaryRoomLabel],
                    'room_count' => max((int) ($booking->quantity ?: $assignedRooms->count() ?: 1), 1),
                    'check_in' => optional($booking->check_in)->format('d M Y'),
                    'check_out' => optional($booking->check_out)->format('d M Y'),
                    'check_in_raw' => optional($booking->check_in)->toDateString(),
                    'check_out_raw' => optional($booking->check_out)->toDateString(),
                    'nights' => $nights,
                    'guests' => $guestCount,
                    'adults' => (int) ($booking->adults ?? 0),
                    'children' => (int) ($booking->children ?? 0),
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status ?: 'unpaid',
                    'payment_method' => $booking->payment_method ?: 'Not recorded',
                    'total_amount' => (float) $booking->total_amount,
                    'price_override' => $booking->price_override,
                    'has_price_override' => $booking->has_price_override,
                    'has_pending_price_override_approval' => $booking->has_pending_price_override_approval,
                    'special_requests' => $booking->special_requests,
                    'created_at' => optional($booking->created_at)->format('d M Y, g:i A'),
                    'checked_in_rooms' => $booking->checked_in_rooms_count,
                    'stay_phase' => match (true) {
                        $booking->status === 'cancelled' => 'Cancelled',
                        $booking->check_in && $booking->check_out && $today->lt($booking->check_in) => 'Upcoming',
                        $booking->check_in && $booking->check_out && $today->betweenIncluded($booking->check_in, $booking->check_out) => 'In house',
                        $booking->check_out && $today->gt($booking->check_out) => 'Completed stay',
                        default => 'Pending schedule',
                    },
                ];
            });

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => $bookings,
            'summary' => [
                'arrivals_today' => Booking::whereDate('check_in', $today)
                    ->whereNotIn('status', ['cancelled'])
                    ->count(),
                'in_house' => Booking::whereDate('check_in', '<=', $today)
                    ->whereDate('check_out', '>=', $today)
                    ->whereIn('status', ['confirmed', 'active', 'checked_in'])
                    ->count(),
                'unsettled' => Booking::whereIn('status', ['confirmed', 'active', 'checked_in'])
                    ->where('payment_status', '!=', 'paid')
                    ->count(),
                'pending_override' => Booking::where('details->price_override->approval_status', 'pending')->count(),
            ],
            'filters' => [
                'active' => $filter,
                'search' => $search,
                'date' => $date,
                'dateType' => $dateType,
            ],
            'todayLabel' => $today->format('l, d M Y'),
        ]);
    }

    public function edit(Booking $booking)
    {
        $this->service->normalizeLegacyCheckedInStatuses();
        $booking->load(['room.roomType', 'rooms.roomType', 'roomType', 'user', 'charges', 'payments']);
        $rooms = Room::with('roomType')->get();
        $details = $booking->details ?? [];
        $preCheckIn = $details['pre_check_in'] ?? null;

        return Inertia::render('Admin/Bookings/Edit', [
            'booking' => $this->editPayload($booking),
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
            'preCheckIn' => $preCheckIn ? [
                'completed_at' => $preCheckIn['completed_at'] ?? null,
                'estimated_arrival_time' => $preCheckIn['estimated_arrival_time'] ?? null,
                'arrival_notes' => $preCheckIn['arrival_notes'] ?? null,
                'source' => $preCheckIn['source'] ?? null,
            ] : null,
            'statusOptions' => [
                ['label' => 'Pending payment', 'value' => 'pending_payment'],
                ['label' => 'Confirmed', 'value' => 'confirmed'],
                ['label' => 'Checked in', 'value' => 'checked_in'],
                ['label' => 'Checked out', 'value' => 'checked_out'],
                ['label' => 'Cancelled', 'value' => 'cancelled'],
            ],
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'status' => 'required|in:pending_payment,confirmed,checked_in,checked_out,cancelled',
            'guests' => 'nullable|integer|min:1',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'purpose_of_stay' => 'nullable|string|max:255',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        // Use service to safely reassign/modify booking
        $this->service->updateBooking($booking, $data);

        AuditLogger::log('booking_admin_updated', 'Booking', $booking->id, ['data' => $data]);

        return redirect()->route('admin.bookings.index')->with('success', 'Booking updated.');
    }

    public function approvePriceOverride(Booking $booking)
    {
        $priceOverride = $booking->price_override;

        abort_unless($booking->has_pending_price_override_approval && is_array($priceOverride), 404);

        $details = $booking->details ?? [];
        $details['price_override'] = array_merge($priceOverride, [
            'approval_status' => 'approved',
            'approved_at' => now()->toIso8601String(),
            'approved_by_user_id' => auth()->id(),
            'approved_by_name' => auth()->user()?->name,
        ]);

        $booking->update([
            'total_amount' => (float) ($priceOverride['override_amount'] ?? $booking->total_amount),
            'details' => $details,
        ]);

        AuditLogger::log('booking_price_override_approved', 'Booking', $booking->id, [
            'override_amount' => $priceOverride['override_amount'] ?? null,
        ]);

        return back()->with('success', 'Price override approved.');
    }

    public function rejectPriceOverride(Booking $booking)
    {
        $priceOverride = $booking->price_override;

        abort_unless($booking->has_pending_price_override_approval && is_array($priceOverride), 404);

        $details = $booking->details ?? [];
        $details['price_override'] = array_merge($priceOverride, [
            'approval_status' => 'rejected',
            'rejected_at' => now()->toIso8601String(),
            'rejected_by_user_id' => auth()->id(),
            'rejected_by_name' => auth()->user()?->name,
        ]);

        $booking->update([
            'total_amount' => (float) ($priceOverride['original_amount'] ?? $booking->total_amount),
            'details' => $details,
        ]);

        AuditLogger::log('booking_price_override_rejected', 'Booking', $booking->id, [
            'override_amount' => $priceOverride['override_amount'] ?? null,
            'original_amount' => $priceOverride['original_amount'] ?? null,
        ]);

        return back()->with('success', 'Price override rejected and booking amount reset.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        AuditLogger::log('booking_admin_deleted', 'Booking', $booking->id);

        return back()->with('success', 'Booking removed.');
    }

    /**
     * reassign rooms
     */
    public function reassignRoom(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
        ]);

        $booking->update(['room_id' => $data['room_id']]);

        AuditLogger::log('booking_reassigned', 'Booking', $booking->id, ['to_room' => $data['room_id']]);

        return back()->with('success', 'Room reassigned.');
    }

    public function addCharge(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'nullable|integer',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $roomId = $this->resolveRoomId($booking, $data['room_id'] ?? null);

        if (! $roomId) {
            return back()->withErrors([
                'room_id' => $booking->rooms()->count() > 1
                    ? 'Select the specific room for this charge.'
                    : 'Assign a room to this booking before posting a charge.',
            ]);
        }

        $this->billingService->addCharge($booking, $roomId, $data['description'], (float) $data['amount']);

        return back()->with('success', 'Charge added successfully.');
    }

    public function addPayment(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $roomId = $this->resolveRoomId($booking, $data['room_id'] ?? null);

        if (! $roomId) {
            return back()->withErrors([
                'room_id' => $booking->rooms()->count() > 1
                    ? 'Select the specific room for this payment.'
                    : 'Assign a room to this booking before recording payment.',
            ]);
        }

        $this->billingService->addPayment(
            booking: $booking,
            roomId: $roomId,
            amount: (float) $data['amount'],
            method: $data['method'],
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Check in a booking - marks rooms as occupied and records check-in timestamps
     */
    public function checkIn(Booking $booking)
    {
        if (! in_array($booking->status, ['confirmed', 'checked_in'], true)) {
            return back()->withErrors(['status' => 'Booking must be confirmed before check-in.']);
        }

        try {
            $this->service->checkIn($booking, null, auth()->user());
            AuditLogger::log('booking_checked_in', $booking, $booking->id, [
                'by_admin' => auth()->user()?->id,
            ]);
            return back()->with('success', 'Guest checked in successfully. Rooms marked as occupied.');
        } catch (\Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Check out a booking - marks rooms as dirty and records check-out timestamps
     */
    public function checkOut(Booking $booking)
    {
        if (! in_array($booking->status, ['checked_in', 'checked_out'], true)) {
            return back()->withErrors(['status' => 'Booking must be checked in before check-out.']);
        }

        try {
            $this->service->checkOut($booking, null);
            AuditLogger::log('booking_checked_out', $booking, $booking->id, [
                'by_admin' => auth()->user()?->id,
            ]);
            return back()->with('success', 'Guest checked out successfully. Rooms marked for cleaning.');
        } catch (\Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    protected function editPayload(Booking $booking): array
    {
        $paymentColumns = ['id', 'room_id', 'amount', 'amount_paid', 'method', 'created_at'];

        if (Schema::hasColumn('payments', 'notes')) {
            $paymentColumns[] = 'notes';
        }

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'room_id' => $booking->room_id,
            'check_in' => optional($booking->check_in)->toDateString(),
            'check_out' => optional($booking->check_out)->toDateString(),
            'status' => $booking->status === 'active' ? 'checked_in' : $booking->status,
            'guests' => (int) ($booking->guests ?: (($booking->adults ?? 0) + ($booking->children ?? 0)) ?: 1),
            'adults' => (int) ($booking->adults ?? 1),
            'children' => (int) ($booking->children ?? 0),
            'guest_name' => $booking->guest_name ?: optional($booking->user)->name ?: '',
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'emergency_contact_name' => $booking->emergency_contact_name,
            'emergency_contact_phone' => $booking->emergency_contact_phone,
            'purpose_of_stay' => $booking->purpose_of_stay,
            'payment_status' => $booking->payment_status ?: 'unpaid',
            'payment_method' => $booking->payment_method ?: 'Not recorded',
            'total_amount' => (float) ($booking->total_amount ?? 0),
            'price_override' => $booking->price_override,
            'has_price_override' => $booking->has_price_override,
            'has_pending_price_override_approval' => $booking->has_pending_price_override_approval,
            'special_requests' => $booking->special_requests,
            'room_label' => trim(collect([
                $booking->roomType?->title ?: $booking->room?->roomType?->title,
                $booking->room?->name ?: $booking->room?->room_number,
            ])->filter()->implode(' - ')) ?: 'Unassigned',
            'assigned_rooms' => $booking->rooms
                ->map(function (Room $room) {
                    return trim(collect([
                        $room->roomType?->title,
                        $room->name ?: $room->room_number,
                    ])->filter()->implode(' - '));
                })
                ->filter()
                ->values()
                ->all(),
            'assigned_room_options' => $booking->rooms
                ->map(function (Room $room) {
                    return [
                        'id' => $room->id,
                        'label' => trim(collect([
                            $room->roomType?->title,
                            $room->name ?: $room->room_number,
                        ])->filter()->implode(' - ')),
                    ];
                })
                ->values()
                ->all(),
            'has_multiple_rooms' => $booking->rooms->count() > 1,
            'charges' => $booking->charges()
                ->latest('id')
                ->get(['id', 'room_id', 'description', 'amount', 'created_at'])
                ->map(fn ($charge) => [
                    'id' => $charge->id,
                    'room_id' => $charge->room_id,
                    'description' => $charge->description,
                    'amount' => (float) $charge->amount,
                    'created_at' => optional($charge->created_at)?->toIso8601String(),
                ])
                ->all(),
            'payments' => $booking->payments()
                ->latest('id')
                ->get($paymentColumns)
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'room_id' => $payment->room_id,
                    'amount' => (float) ($payment->amount_paid ?? $payment->amount),
                    'method' => $payment->method,
                    'notes' => $payment->notes ?? null,
                    'created_at' => optional($payment->created_at)?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    protected function resolveRoomId(Booking $booking, ?int $roomId): ?int
    {
        if ($roomId) {
            return $roomId;
        }

        $assignedRoomIds = $booking->rooms()->pluck('rooms.id');

        if ($assignedRoomIds->count() === 1) {
            return (int) $assignedRoomIds->first();
        }

        if ($assignedRoomIds->count() > 1) {
            return null;
        }

        return $booking->room_id ? (int) $booking->room_id : null;
    }
}
