<?php
// ========================================================
// Admin\RoomController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\AuditLoggerService as AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->middleware(['auth','role:Manager|MD']);
        $this->auditLogger = $auditLogger;
    }

    /**
     * Display a listing of rooms
     */
    public function index(Request $request)
    {
        $rooms = Room::with('roomType','property')->paginate(20);
        return Inertia::render('Admin/Rooms/Index', compact('rooms'));
    }

    /**
     * Show the form for creating a new room
     */
    public function create()
    {
        $types = RoomType::all();
        $properties = Property::all();
        return Inertia::render('Admin/Rooms/Create', compact('types', 'properties'));
    }

    /**
     * Store a newly created room
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50|unique:rooms,room_number',
            'status' => 'nullable|string|in:available,occupied,maintenance',
            'meta' => 'nullable|array',
            'images.*' => 'nullable|image|max:8192'
        ]);

        $room = Room::create($data);

        if ($request->hasFile('images')) {
        foreach ($request->file('images') as $file) {
            $room->images()->create([
                'path' => $file->store('rooms', 'public')
            ]);
        }
    }

        $this->auditLogger->log('room_created', 'Room', $room->id, ['data' => $data]);

        return redirect()->route('admin.rooms.index')->with('success', 'Room created successfully.');
    }

    /**
     * Show the form for editing the specified room
     */
    public function edit(Room $room)
    {
        $room->load('roomType','property', 'images');
        $types = RoomType::all();
        $properties = Property::all();

        return Inertia::render('Admin/Rooms/Edit', compact('room', 'types', 'properties'));
    }

    /**
     * Update the specified room
     */
    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number'  => 'required|string|max:50|unique:rooms,room_number,' . $room->id,
            'status'       => 'nullable|string|in:available,occupied,maintenance',
            'meta'         => 'nullable|array',
            'images.*'     => 'nullable|image|max:8192', // each image max 5MB
            'remove_images'=> 'nullable|array',          // array of image IDs to remove
            'primary_image_id' => 'nullable|integer',
        ]);

        // Remove images marked for deletion
        if (!empty($data['remove_images'])) {
            foreach ($data['remove_images'] as $imgId) {
                $image = $room->images()->find($imgId);
                if ($image) {
                    \Storage::disk('public')->delete($image->path);
                    $image->delete();
                }
            }
        }


        // Upload new images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('rooms', 'public');
                $room->images()->create([
                    'path' => $path,
                ]);
            }
        }

        // Update primary image
        if (!empty($data['primary_image_id'])) {
            // reset all images
            $room->images()->update(['is_primary' => false]);
            // set the selected one as primary
            $primary = $room->images()->find($data['primary_image_id']);
            if ($primary) {
                $primary->update(['is_primary' => true]);
            }
        }

        // Update room data
        $room->update([
            'room_type_id' => $data['room_type_id'],
            'room_number'  => $data['room_number'],
            'status'       => $data['status'] ?? $room->status,
            'meta'         => $data['meta'] ?? $room->meta,
        ]);

        $this->auditLogger->log('room_updated', 'Room', $room->id, ['data' => $data]);

        return redirect()->route('admin.rooms.index')->with('success', 'Room updated successfully.');
    }


    /**
     * Remove the specified room
     */
    public function destroy(Room $room)
    {
        $room->delete();

        $this->auditLogger->log('room_deleted', 'Room', $room->id);

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted successfully.');
    }

    /**
     * Toggle room availability
     */
    public function toggleAvailability(Room $room)
    {
        $room->status = $room->status === 'available' ? 'maintenance' : 'available';
        $room->save();

        $this->auditLogger->log('room_toggled', 'Room', $room->id, ['status' => $room->status]);

        return redirect()->route('admin.rooms.index')->with('success', 'Room availability toggled.');
    }
}
