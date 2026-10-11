<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingSearchController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('q'));

        $query = Booking::query()
            ->with(['rooms', 'room'])
            ->whereIn('status', ['active', 'checked_in']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhereHas('rooms', function ($rooms) use ($search) {
                        $rooms->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('display_name', 'like', "%{$search}%");
                    });
            });
        }

        $results = $query->latest()->limit(20)->get()->map(function (Booking $booking) {
            $room = $booking->rooms->first() ?? $booking->room;
            $label = $room
                ? ($room->display_name ?: $room->name ?: $room->code)
                : null;

            return [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'guest_name' => $booking->guest_name,
                'status' => $booking->status,
                'room_id' => $room?->id ?? $booking->room_id,
                'room_label' => $label,
            ];
        });

        return response()->json(['results' => $results]);
    }
}
