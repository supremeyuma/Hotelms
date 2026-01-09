<?php
// app/Http/Controllers/Admin/ContentController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContentController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/WebsiteContent/Index', [
            'contents' => Content::all()->keyBy('key')
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key' => 'required|string',
            'value' => 'nullable',
            'type' => 'required|in:text,html,image'
        ]);

        Content::updateOrCreate(
            ['key' => $data['key']],
            ['value' => $data['value'], 'type' => $data['type'], 'updated_by' => $request->user()->id]
        );

        return back();
    }

    // app/Http/Controllers/Admin/ContentController.php
    public function uploadImage(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'image' => 'required|image'
        ]);

        $path = $request->file('image')->store('public/content');

        \App\Models\Content::updateOrCreate(
            ['key' => $request->key],
            [
                'value' => '/storage/' . str_replace('public/', '', $path),
                'type' => 'image',
                'updated_by' => $request->user()->id
            ]
        );

        return back();
    }

}
