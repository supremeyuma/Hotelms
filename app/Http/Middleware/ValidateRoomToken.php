<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\RoomAccessToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRoomToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        $access = RoomAccessToken::where('token', $token)->first();

        if (!$access) {
            abort(403, 'Invalid room access token.');
        }

        if (!$access->isValid()) {
            abort(403, 'Token expired.');
        }

        if ($access->booking->status !== 'active') {
            abort(403, 'Booking is not active.');
        }

        // Attach room & booking to request
        $request->merge([
            'room' => $access->room,
            'booking' => $access->booking,
            'room_access_token' => $access,
        ]);

        return $next($request);
    }
}
