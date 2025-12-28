<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomAccessToken;
use Closure;
use Illuminate\Http\Request;

class ResolveGuestRoom
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->route('token');

        $access = RoomAccessToken::where('token', $token)->first();

        abort_if(!$access, 403, 'Invalid room access token.');

        $room = Room::find($access->room_id);

        $booking = Booking::find($access->booking_id);

        abort_if(!$room, 403, 'Room not found.');

        // Attach resolved context
        $request->attributes->set('guestRoom', $room);
        $request->attributes->set('guestBooking', $booking);
        $request->attributes->set('roomAccessToken', $token);

        return $next($request);
    }
}
