<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\ImageService;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;

class ImageController extends Controller
{
    protected ImageService $service;

    public function __construct(ImageService $service)
    {
        $this->middleware(['auth', 'role:manager|md|staff'])->only(['store','destroy','setPrimary']);
        $this->service = $service;
    }

    /**
     * Store image for a given type and id.
     *
     * Route expected: POST /images/{type}/{id}
     */
    public function store(Request $request, string $type, $id)
    {
        // Acceptable types mapping
        $map = [
            'rooms' => \App\Models\Room::class,
            'room-types' => \App\Models\RoomType::class,
            'facilities' => \App\Models\Facility::class,
            'areas' => \App\Models\HotelArea::class,
            'properties' => \App\Models\Property::class,
        ];

        if (! isset($map[$type])) {
            return back()->withErrors(['type' => 'Invalid image target type.']);
        }

        $modelClass = $map[$type];

        $model = $modelClass::findOrFail($id);

        // Validate file
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:5120', // max 5MB
            'caption' => 'nullable|string|max:255',
            'is_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        /** @var UploadedFile $file */
        $file = $request->file('image');

        $image = $this->service->upload($model, $file, [
            'caption' => $request->input('caption'),
            'is_primary' => boolval($request->input('is_primary', false)),
            'meta' => $request->input('meta', null),
        ]);

        return back()->with('success', 'Image uploaded.')->with('image', $image);
    }

    /**
     * Delete an image.
     *
     * Route: DELETE /images/{image}
     */
    public function destroy(Image $image)
    {
        $this->authorize('delete', $image);

        $this->service->delete($image);

        return back()->with('success', 'Image deleted.');
    }

    /**
     * Set image primary
     *
     * Route: PATCH /images/{image}/primary
     */
    public function setPrimary(Image $image)
    {
        $this->authorize('update', $image);

        $this->service->setPrimary($image);

        return back()->with('success', 'Primary image set.');
    }
}
