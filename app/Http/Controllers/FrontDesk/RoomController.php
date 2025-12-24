<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Room;

class RoomController extends Controller
{
    public function index()
    {
        return Inertia::render('FrontDesk/Rooms/Index', [
            'rooms' => Room::with('bookings')->where('status', 'occupied')->get()
        ]);
    }
}
