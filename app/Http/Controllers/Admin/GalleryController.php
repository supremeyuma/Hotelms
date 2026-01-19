<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/WebsiteContent/Gallery', [
            'items' => Gallery::orderBy('order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:100',
            'image'    => 'required|image|max:5120',
            'caption'  => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('gallery', 'public');

        Gallery::create([
            'category'   => $data['category'],
            'image_path' => $path,
            'caption'    => $data['caption'],
        ]);

        return back()->with('success', 'Image uploaded');
    }

    public function update(Request $request, Gallery $gallery)
    {
        $data = $request->validate([
            'category' => 'required|string|max:100',
            'caption'  => 'nullable|string|max:255',
            'image'    => 'nullable|image|max:10240',
        ]);

        if ($request->hasFile('image')) {
            Storage::delete($gallery->image_path);
            $data['image_path'] = $request->file('image')->store('gallery','public');
        }

        $gallery->update($data);

        return back()->with('success', 'Gallery item updated');
    }

    public function destroy(Gallery $gallery)
    {
        Storage::delete($gallery->image_path);
        $gallery->delete();

        return back()->with('success', 'Gallery item deleted');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
        ]);

        foreach ($request->order as $index => $id) {
            Gallery::where('id', $id)->update(['order' => $index]);
        }

        return back()->with('success', 'Gallery reordered');
    }

    public function toggle(Gallery $gallery)
    {
        $gallery->update([
            'is_active' => ! $gallery->is_active,
        ]);

        return back();
}

}
