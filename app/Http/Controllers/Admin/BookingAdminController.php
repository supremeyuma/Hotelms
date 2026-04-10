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
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingAdminController extends Controller
{
    protected BookingService $service;

    public function __construct(BookingService $service)
    {
        $this->middleware(['auth', 'role:manager|md']);
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $today = Carbon::today();
        $filter = $request->string('filter')->toString();
        $allowedFilters = ['arrivals_today', 'departures_today', 'in_house', 'unsettled'];

        if (! in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $bookings = Booking::with(['room.roomType', 'roomType', 'rooms.roomType', 'user'])
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
            ],
            'filters' => [
                'active' => $filter,
            ],
            'todayLabel' => $today->format('l, d M Y'),
        ]);
    }

    public function edit(Booking $booking)
    {
        $booking->load(['room', 'user']);
        $rooms = Room::with('roomType')->get();

        return Inertia::render('Admin/Bookings/Edit', compact('booking', 'rooms'));
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'status' => 'required|string',
            'guests' => 'nullable|integer|min:1',
        ]);

        // Use service to safely reassign/modify booking
        $this->service->updateBooking($booking, $data);

        AuditLogger::log('booking_admin_updated', 'Booking', $booking->id, ['data' => $data]);

        return redirect()->route('admin.bookings.index')->with('success', 'Booking updated.');
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
}
