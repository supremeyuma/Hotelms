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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RoomController extends Controller
{
    protected const ROOM_STATUSES = [
        'available',
        'occupied',
        'dirty',
        'reserved',
        'maintenance',
        'unavailable',
    ];

    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->auditLogger = $auditLogger;
    }

    /**
     * Display a listing of rooms
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');

        $roomsQuery = Room::query()
            ->with([
                'roomType:id,title',
                'property:id,name',
                'images:id,imageable_id,imageable_type,path,caption,is_primary,created_at',
                'latestCleaning' => function ($query) {
                    $query->select([
                        'room_cleanings.id',
                        'room_cleanings.room_id',
                        'room_cleanings.status',
                        'room_cleanings.cleaned_at',
                        'room_cleanings.updated_at',
                    ]);
                },
                'bookings' => function ($query) {
                    $query->select('bookings.id', 'bookings.booking_code', 'bookings.guest_name', 'bookings.check_in', 'bookings.check_out', 'bookings.status')
                        ->whereIn('bookings.status', ['pending_payment', 'confirmed', 'active', 'checked_in'])
                        ->latest('bookings.check_in');
                },
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($roomQuery) use ($search) {
                    $roomQuery->where('rooms.name', 'like', "%{$search}%")
                        ->orWhere('rooms.room_number', 'like', "%{$search}%")
                        ->orWhere('rooms.code', 'like', "%{$search}%")
                        ->orWhereHas('roomType', fn ($roomTypeQuery) => $roomTypeQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, self::ROOM_STATUSES, true), fn ($query) => $query->where('rooms.status', $status))
            ->orderByRaw('COALESCE(rooms.floor, 0) asc')
            ->orderBy('rooms.name');

        $rooms = $roomsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(function (Room $room) {
                $housekeepingStatus = $this->resolveHousekeepingStatus($room);
                $currentBooking = $room->bookings->first();

                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'room_number' => $room->room_number,
                    'code' => $room->code,
                    'display_name' => $room->display_name,
                    'floor' => $room->floor,
                    'status' => $room->status,
                    'property_name' => $room->property?->name,
                    'room_type' => [
                        'id' => $room->roomType?->id,
                        'title' => $room->roomType?->title,
                    ],
                    'primary_image_url' => $room->primary_image_url,
                    'images' => $room->images->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => $image->url,
                        'is_primary' => (bool) $image->is_primary,
                    ])->values(),
                    'housekeeping' => [
                        'status' => $housekeepingStatus,
                        'updated_at' => optional($room->latestCleaning?->updated_at)->toIso8601String(),
                        'cleaned_at' => optional($room->latestCleaning?->cleaned_at)->toIso8601String(),
                    ],
                    'current_booking' => $currentBooking ? [
                        'id' => $currentBooking->id,
                        'booking_code' => $currentBooking->booking_code,
                        'guest_name' => $currentBooking->guest_name,
                        'status' => $currentBooking->status,
                        'check_in' => optional($currentBooking->check_in)->format('d M'),
                        'check_out' => optional($currentBooking->check_out)->format('d M'),
                    ] : null,
                ];
            });

        $roomSnapshots = Room::with([
            'latestCleaning' => function ($query) {
                $query->select([
                    'room_cleanings.id',
                    'room_cleanings.room_id',
                    'room_cleanings.status',
                    'room_cleanings.cleaned_at',
                ]);
            },
        ])
            ->get(['id', 'status']);

        $overview = [
            'total' => $roomSnapshots->count(),
            'available' => $roomSnapshots->where('status', 'available')->count(),
            'occupied' => $roomSnapshots->where('status', 'occupied')->count(),
            'dirty' => $roomSnapshots->filter(fn (Room $room) => $this->resolveHousekeepingStatus($room) === 'dirty')->count(),
            'clean' => $roomSnapshots->filter(fn (Room $room) => $this->resolveHousekeepingStatus($room) === 'clean')->count(),
            'reserved' => $roomSnapshots->where('status', 'reserved')->count(),
            'maintenance' => $roomSnapshots->where('status', 'maintenance')->count(),
            'unavailable' => $roomSnapshots->where('status', 'unavailable')->count(),
        ];

        return Inertia::render('Admin/Rooms/Index', [
            'rooms' => $rooms,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'overview' => $overview,
            'statusOptions' => collect(self::ROOM_STATUSES)
                ->map(fn ($statusOption) => [
                    'label' => ucfirst(str_replace('_', ' ', $statusOption)),
                    'value' => $statusOption,
                ])
                ->prepend(['label' => 'All statuses', 'value' => 'all'])
                ->values(),
        ]);
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
        $this->preparePayload($request);

        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'room_number' => 'required|string|max:50|unique:rooms,room_number',
            'code' => 'nullable|string|max:50|unique:rooms,code',
            'floor' => 'nullable|integer|min:0|max:200',
            'status' => ['nullable', 'string', Rule::in(self::ROOM_STATUSES)],
            'meta' => 'nullable|array',
            'images.*' => 'nullable|image|max:8192',
            'primary_upload_index' => 'nullable|integer|min:0',
        ]);

        $room = Room::create([
            'property_id' => $data['property_id'],
            'room_type_id' => $data['room_type_id'],
            'name' => $data['name'],
            'display_name' => $data['name'],
            'room_number' => $data['room_number'],
            'code' => $data['code'] ?? null,
            'floor' => $data['floor'] ?? null,
            'status' => $data['status'] ?? 'available',
            'meta' => $data['meta'] ?? null,
        ]);

        $uploadedIds = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $image = $room->images()->create([
                    'path' => $file->store('rooms', 'public'),
                    'is_primary' => false,
                ]);

                $uploadedIds[] = $image->id;
            }
        }

        $this->applyPrimaryImageSelection($room, null, $data['primary_upload_index'] ?? null, $uploadedIds);

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
        $this->preparePayload($request);

        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'room_type_id' => 'required|exists:room_types,id',
            'name' => 'required|string|max:255',
            'room_number'  => ['required', 'string', 'max:50', Rule::unique('rooms', 'room_number')->ignore($room->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($room->id)],
            'floor' => 'nullable|integer|min:0|max:200',
            'status'       => ['nullable', 'string', Rule::in(self::ROOM_STATUSES)],
            'meta'         => 'nullable|array',
            'images.*'     => 'nullable|image|max:8192',
            'remove_images'=> 'nullable|array',
            'remove_images.*' => 'integer',
            'primary_image_id' => 'nullable|integer',
            'primary_upload_index' => 'nullable|integer|min:0',
        ]);

        if (!empty($data['remove_images'])) {
            foreach ($data['remove_images'] as $imgId) {
                $image = $room->images()->find($imgId);
                if ($image) {
                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                }
            }
        }

        $uploadedIds = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('rooms', 'public');
                $image = $room->images()->create([
                    'path' => $path,
                    'is_primary' => false,
                ]);

                $uploadedIds[] = $image->id;
            }
        }

        $this->applyPrimaryImageSelection(
            $room,
            $data['primary_image_id'] ?? null,
            $data['primary_upload_index'] ?? null,
            $uploadedIds
        );

        $room->update([
            'property_id' => $data['property_id'],
            'room_type_id' => $data['room_type_id'],
            'name' => $data['name'],
            'display_name' => $data['name'],
            'room_number'  => $data['room_number'],
            'code' => $data['code'] ?? null,
            'floor' => $data['floor'] ?? null,
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

    public function updateStatus(Request $request, Room $room)
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(self::ROOM_STATUSES)],
        ]);

        $room->update(['status' => $data['status']]);

        $this->auditLogger->log('room_status_updated', 'Room', $room->id, ['status' => $data['status']]);

        return back()->with('success', "Room {$room->name} is now marked {$data['status']}.");
    }

    protected function preparePayload(Request $request): array
    {
        $payload = $request->all();
        $meta = $payload['meta'] ?? null;

        if (is_string($meta)) {
            $meta = trim($meta);

            if ($meta === '') {
                $payload['meta'] = null;
            } else {
                $decoded = json_decode($meta, true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        'meta' => 'Meta must be valid JSON.',
                    ]);
                }

                $payload['meta'] = $decoded;
            }
        }

        $request->merge($payload);

        return $payload;
    }

    protected function applyPrimaryImageSelection(Room $room, ?int $primaryImageId, ?int $primaryUploadIndex, array $uploadedIds = []): void
    {
        $targetImageId = $primaryImageId;

        if ($targetImageId === null && $primaryUploadIndex !== null && array_key_exists($primaryUploadIndex, $uploadedIds)) {
            $targetImageId = $uploadedIds[$primaryUploadIndex];
        }

        if ($targetImageId !== null) {
            $room->images()->update(['is_primary' => false]);

            $primary = $room->images()->find($targetImageId);

            if ($primary) {
                $primary->update(['is_primary' => true]);
                return;
            }
        }

        if (! $room->images()->where('is_primary', true)->exists()) {
            $fallback = $room->images()->oldest('id')->first();

            if ($fallback) {
                $fallback->update(['is_primary' => true]);
            }
        }
    }

    protected function resolveHousekeepingStatus(Room $room): string
    {
        if ($room->status === 'dirty') {
            return 'dirty';
        }

        $latestStatus = $room->latestCleaning?->status;

        return in_array($latestStatus, ['dirty', 'cleaning', 'cleaner_requested', 'clean'], true)
            ? $latestStatus
            : 'unknown';
    }
}
