<?php
// ========================================================
// Admin\RoomTypeController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Models\Property;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:manager|md']);
    }

    public function index()
    {
        $types = RoomType::with('property')
            ->withCount('rooms')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/RoomTypes/Index', compact('types'));
    }

    public function create()
    {
        $properties = Property::all();
        return Inertia::render('Admin/RoomTypes/Create', compact('properties'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|string|max:191',
            'max_occupancy' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'features' => 'nullable|array',
        ]);

        $type = RoomType::create($data);

        AuditLogger::log('roomtype_created', 'RoomType', $type->id, ['data' => $data]);

        return redirect()->route('admin.room-types.index')->with('success','Room type created.');
    }

    public function edit(RoomType $room_type)
    {
        $room_type->load('property');
        $properties = Property::all();
        return Inertia::render('Admin/RoomTypes/Edit', ['type'=>$room_type,'properties'=>$properties]);
    }

    public function update(Request $request, RoomType $room_type)
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'max_occupancy' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'features' => 'nullable|array',
        ]);

        $room_type->update($data);

        AuditLogger::log('roomtype_updated', 'RoomType', $room_type->id, ['data' => $data]);

        return redirect()->route('admin.room-types.index')->with('success','Room type updated.');
    }

    public function destroy(RoomType $room_type)
    {
        $room_type->delete();

        AuditLogger::log('roomtype_deleted', 'RoomType', $room_type->id);

        return back()->with('success','Room type removed.');
    }
}
