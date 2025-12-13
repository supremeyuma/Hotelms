<?php
// ========================================================
// Admin\RoomController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:Manager|MD']);
    }

    /**
     * index - list rooms
     */
    public function index(Request $request)
    {
        $rooms = Room::with('roomType','property')->paginate(20);
        return Inertia::render('Admin/Rooms/Index', compact('rooms'));
    }

    /**
     * create - show form (Inertia)
     */
    public function create()
    {
        $types = RoomType::all();
        return Inertia::render('Admin/Rooms/Create', ['types' => $types]);
    }

    /**
     * store - validate and persist room
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50|unique:rooms,room_number',
            'status' => 'nullable|string|in:available,occupied,maintenance',
            'meta' => 'nullable|array',
        ]);

        $room = Room::create($data);

        AuditLogger::log('room_created', 'Room', $room->id, ['data' => $data]);

        return redirect()->route('rooms.index')->with('success','Room created.');
    }

    /**
     * edit - show edit form
     */
    public function edit(Room $room)
    {
        $room->load('roomType','property');
        $types = RoomType::all();
        return Inertia::render('Admin/Rooms/Edit', ['room'=>$room,'types'=>$types]);
    }

    /**
     * update
     */
    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50|unique:rooms,room_number,'.$room->id,
            'status' => 'nullable|string|in:available,occupied,maintenance',
            'meta' => 'nullable|array',
        ]);

        $room->update($data);

        AuditLogger::log('room_updated', 'Room', $room->id, ['data' => $data]);

        return redirect()->route('rooms.index')->with('success','Room updated.');
    }

    /**
     * destroy
     */
    public function destroy(Room $room)
    {
        $room->delete();

        AuditLogger::log('room_deleted', 'Room', $room->id);

        return back()->with('success','Room deleted.');
    }

    /**
     * toggle availability
     */
    public function toggleAvailability(Room $room)
    {
        $room->status = $room->status === 'available' ? 'maintenance' : 'available';
        $room->save();

        AuditLogger::log('room_toggled', 'Room', $room->id, ['status' => $room->status]);

        return back()->with('success','Room availability toggled.');
    }
}
