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
        // Define which statuses belong to which view
        $view = $request->query('view', 'occupied');
        
        $statusMap = [
            'occupied'   => ['occupied'],
            'unoccupied' => ['available', 'dirty', 'maintenance']
        ];

        // Fallback to occupied if view is invalid
        $targetStatuses = $statusMap[$view] ?? $statusMap['occupied'];

        $rooms = Room::query()
            ->with(['roomType', 'property', 'bookings' => function ($q) {
                // FIX: Added 'bookings.' prefix to avoid ambiguity error
                $q->where('bookings.status', 'confirmed')
                  ->latest('bookings.created_at');
            }])
            // Filter by the selected view's statuses
            ->whereIn('rooms.status', $targetStatuses)
            // Apply search if present
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('rooms.name', 'like', "%{$search}%")
                        ->orWhereHas('roomType', function ($rt) use ($search) {
                            $rt->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('rooms.name')
            ->get();

        return Inertia::render('FrontDesk/Rooms/Index', [
            'rooms'  => $rooms,
            'view'   => $view,
            'search' => $request->search ?? '',
        ]);
    }

    public function updateStatus(Request $request, Room $room)
    {
        $request->validate([
            'status' => 'required|in:available,dirty,maintenance,occupied'
        ]);

        $room->update(['status' => $request->status]);

        return back()->with('success', "Room {$room->name} updated to {$request->status}.");
    }
}