<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Services\MenuPrepTimeService;
use App\Models\MenuItemImage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuItemController extends Controller
{
    public function index(Request $request, string $area)
    {
        //$area = $request->get('area'); // kitchen | bar
        //dd($token);

        

        if (auth()->user()->hasRole('kitchen') && $area !== 'kitchen') {
            abort(403);
        }

        if (auth()->user()->hasRole('bar') && $area !== 'bar') {
            abort(403);
        }

        return Inertia::render('Staff/Menu/Index', [
            'area' => $area,
            'categories' => MenuCategory::with([
                'subcategories.items',
                'items' => fn ($q) => $q->whereNull('menu_subcategory_id')
            ])
            ->where(function ($q) use ($area) {
                $q->where('type', $area)->orWhere('type', 'both');
            })
            ->orderBy('sort_order')
            ->get(),
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_category_id' => 'required|exists:menu_categories,id',
            'menu_subcategory_id' => [
                'nullable',
                Rule::exists('menu_subcategories', 'id')
                    ->where('menu_category_id', $request->menu_category_id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'service_area' => 'required|in:kitchen,bar',
            'is_available' => 'required|boolean',
            'images.*' => 'image|max:8192',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'nullable|url',
        ]);

        $item = MenuItem::create($validated);
        $this->storeUploadedImages($request, $item);
        $this->storeRemoteImages($request->input('image_urls', []), $item);

        return back();
    }


    public function update(Request $request, MenuItem $item)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'menu_category_id' => 'required|exists:menu_categories,id',
            'menu_subcategory_id' => 'nullable|exists:menu_subcategories,id',
            'prep_time_adjustment' => 'nullable|integer',
            'is_available' => 'required|boolean',
            'images.*' => 'image|max:8192',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'nullable|url',
        ]);

        $item->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'menu_category_id' => $data['menu_category_id'],
            'menu_subcategory_id' => $data['menu_subcategory_id'] ?? null,
            'is_available' => $data['is_available'],
        ]);

        if (array_key_exists('prep_time_adjustment', $data)) {
            MenuPrepTimeService::adjustForItem($item, (int) $data['prep_time_adjustment']);
        }

        $this->storeUploadedImages($request, $item);
        $this->storeRemoteImages($request->input('image_urls', []), $item);

        return back();
    }





    public function destroy(MenuItem $item)
    {
        $item->delete();
        return back();
    }

    public function edit(MenuItem $item)
    {
        return Inertia::render('Staff/Menu/EditItem', [
            'item' => $item->load('images'),
            'categories' => MenuCategory::with('subcategories')->get()
        ]);
    }

    public function toggle(MenuItem $item)
    {
        $item->update(['is_available' => ! $item->is_available]);
        return back();
    }

    public function reorder(Request $request)
    {
        foreach ($request->items as $row) {
            MenuItem::where('id', $row['id'])
                ->update(['sort_order' => $row['sort_order']]);
        }

        return back();
    }

    public function deleteImage(MenuItemImage $image)
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back();
    }

    private function storeUploadedImages(Request $request, MenuItem $item): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $image) {
            $path = $image->store('menu-items', 'public');

            $item->images()->create([
                'path' => $path,
            ]);
        }
    }

    private function storeRemoteImages(array $imageUrls, MenuItem $item): void
    {
        foreach (array_filter($imageUrls) as $imageUrl) {
            try {
                $response = Http::timeout(15)->get($imageUrl)->throw();
            } catch (RequestException $exception) {
                throw ValidationException::withMessages([
                    'image_urls' => 'One of the image URLs could not be downloaded.',
                ]);
            }

            $contentType = Str::before((string) $response->header('Content-Type'), ';');

            if (! Str::startsWith($contentType, 'image/')) {
                throw ValidationException::withMessages([
                    'image_urls' => 'One of the provided URLs did not return an image.',
                ]);
            }

            $contents = $response->body();

            if (strlen($contents) > 8 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'image_urls' => 'One of the provided image URLs exceeds the 8MB limit.',
                ]);
            }

            $extension = $this->extensionFromContentType($contentType);
            $filename = 'menu-items/' . Str::uuid() . '.' . $extension;

            Storage::disk('public')->put($filename, $contents);

            $item->images()->create([
                'path' => $filename,
            ]);
        }
    }

    private function extensionFromContentType(string $contentType): string
    {
        return match ($contentType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };
    }


}
