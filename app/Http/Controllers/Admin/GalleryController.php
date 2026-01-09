<?php
// app/Http/Controllers/Admin/GalleryController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Storage;

class GalleryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/WebsiteContent/Gallery', [
            'items' => Gallery::orderBy('order')->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string',
            'image' => 'required|image',
            'caption' => 'nullable|string'
        ]);

        $path = $request->file('image')->store('public/gallery');

        Gallery::create([
            'category' => $data['category'],
            'image_path' => $path,
            'caption' => $data['caption']
        ]);

        return back();
    }

    public function destroy(Gallery $gallery)
    {
        Storage::delete($gallery->image_path);
        $gallery->delete();
        return back();
    }
}
