<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomGuestAccessService;
use Illuminate\Http\RedirectResponse;

class RoomEntryController extends Controller
{
    public function __construct(
        protected RoomGuestAccessService $roomGuestAccessService
    ) {
    }

    public function show(Room $room): RedirectResponse
    {
        $accessToken = $this->roomGuestAccessService->resolveCurrentAccessTokenForRoom($room);

        abort_unless($accessToken, 403, 'Guest access is not available for this room right now.');

        return redirect()->route('guest.room.dashboard', [
            'token' => $accessToken->token,
        ]);
    }
}
