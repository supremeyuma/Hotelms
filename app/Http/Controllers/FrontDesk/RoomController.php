<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Room;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'occupied');

        return Inertia::render('FrontDesk/Rooms/Index', [
            'status' => $status,
            'rooms' => Room::with([
                    'roomType',
                    'bookings' => function ($q) {
                        $q->wherePivot('status', 'active')
                          ->orderBy('booking_rooms.checked_in_at');
                    }
                ])
                ->where('rooms.status', $status)
                ->get()
        ]);
    }
}


