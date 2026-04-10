<?php
// ========================================================
// Admin\RoomTypeController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use App\Models\Property;
use App\Services\AuditLoggerService as AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RoomTypeController extends Controller
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->auditLogger = $auditLogger;
    }

    public function index()
    {
        $types = RoomType::with('property')
            ->with('images:id,imageable_id,imageable_type,path,caption,is_primary,created_at')
            ->withCount('rooms')
            ->latest()
            ->paginate(20)
            ->through(function (RoomType $type) {
                return [
                    'id' => $type->id,
                    'property_id' => $type->property_id,
                    'title' => $type->title,
                    'max_occupancy' => $type->max_occupancy,
                    'base_price' => $type->base_price,
                    'features' => $type->features ?? [],
                    'rooms_count' => $type->rooms_count,
                    'primary_image_url' => $type->primary_image_url,
                    'property' => $type->property ? [
                        'id' => $type->property->id,
                        'name' => $type->property->name,
                    ] : null,
                    'images' => $type->images->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => $image->url,
                        'caption' => $image->caption,
                        'is_primary' => (bool) $image->is_primary,
                    ])->values(),
                ];
            });

        return Inertia::render('Admin/RoomTypes/Index', compact('types'));
    }

    public function create()
    {
        $properties = Property::all();
        return Inertia::render('Admin/RoomTypes/Create', compact('properties'));
    }

    public function store(Request $request)
    {
        $this->preparePayload($request);

        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'title' => 'required|string|max:191',
            'max_occupancy' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'features' => 'nullable|array',
            'images.*' => 'nullable|image|max:8192',
            'primary_upload_index' => 'nullable|integer|min:0',
        ]);

        $type = RoomType::create($data);

        $uploadedIds = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $image = $type->images()->create([
                    'path' => $file->store('room-types', 'public'),
                    'is_primary' => false,
                ]);

                $uploadedIds[] = $image->id;
            }
        }

        $this->applyPrimaryImageSelection($type, null, $data['primary_upload_index'] ?? null, $uploadedIds);

        $this->auditLogger->log('roomtype_created', 'RoomType', $type->id, ['data' => $data]);

        return redirect()->route('admin.room-types.index')->with('success','Room type created.');
    }

    public function edit(RoomType $room_type)
    {
        $room_type->load('property', 'images');
        $properties = Property::all();
        return Inertia::render('Admin/RoomTypes/Edit', [
            'type' => [
                'id' => $room_type->id,
                'property_id' => $room_type->property_id,
                'title' => $room_type->title,
                'max_occupancy' => $room_type->max_occupancy,
                'base_price' => $room_type->base_price,
                'features' => $room_type->features ?? [],
                'images' => $room_type->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'caption' => $image->caption,
                    'is_primary' => (bool) $image->is_primary,
                ])->values(),
            ],
            'properties' => $properties,
        ]);
    }

    public function update(Request $request, RoomType $room_type)
    {
        $this->preparePayload($request);

        $data = $request->validate([
            'title' => 'required|string|max:191',
            'max_occupancy' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'features' => 'nullable|array',
            'images.*' => 'nullable|image|max:8192',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'integer',
            'primary_image_id' => 'nullable|integer',
            'primary_upload_index' => 'nullable|integer|min:0',
        ]);

        if (!empty($data['remove_images'])) {
            foreach ($data['remove_images'] as $imgId) {
                $image = $room_type->images()->find($imgId);
                if ($image) {
                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                }
            }
        }

        $uploadedIds = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $image = $room_type->images()->create([
                    'path' => $file->store('room-types', 'public'),
                    'is_primary' => false,
                ]);

                $uploadedIds[] = $image->id;
            }
        }

        $this->applyPrimaryImageSelection(
            $room_type,
            $data['primary_image_id'] ?? null,
            $data['primary_upload_index'] ?? null,
            $uploadedIds
        );

        $room_type->update($data);

        $this->auditLogger->log('roomtype_updated', 'RoomType', $room_type->id, ['data' => $data]);

        return redirect()->route('admin.room-types.index')->with('success','Room type updated.');
    }

    public function destroy(RoomType $room_type)
    {
        $room_type->delete();

        $this->auditLogger->log('roomtype_deleted', 'RoomType', $room_type->id);

        return back()->with('success','Room type removed.');
    }

    protected function preparePayload(Request $request): array
    {
        $payload = $request->all();
        $features = $payload['features'] ?? null;

        if (is_string($features)) {
            $features = trim($features);

            if ($features === '') {
                $payload['features'] = [];
            } else {
                $decoded = json_decode($features, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload['features'] = $decoded;
                } else {
                    throw ValidationException::withMessages([
                        'features' => 'Features must be provided as a list.',
                    ]);
                }
            }
        }

        $request->merge($payload);

        return $payload;
    }

    protected function applyPrimaryImageSelection(RoomType $roomType, ?int $primaryImageId, ?int $primaryUploadIndex, array $uploadedIds = []): void
    {
        $targetImageId = $primaryImageId;

        if ($targetImageId === null && $primaryUploadIndex !== null && array_key_exists($primaryUploadIndex, $uploadedIds)) {
            $targetImageId = $uploadedIds[$primaryUploadIndex];
        }

        if ($targetImageId !== null) {
            $roomType->images()->update(['is_primary' => false]);

            $primary = $roomType->images()->find($targetImageId);

            if ($primary) {
                $primary->update(['is_primary' => true]);
                return;
            }
        }

        if (! $roomType->images()->where('is_primary', true)->exists()) {
            $fallback = $roomType->images()->oldest('id')->first();

            if ($fallback) {
                $fallback->update(['is_primary' => true]);
            }
        }
    }
}
