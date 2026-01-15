<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ContentController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/WebsiteContent/Index', [
            'contents' => Content::all()->mapWithKeys(fn ($c) => [
                $c->key => [
                    'id' => $c->id,
                    'value' => $c->value,
                    'type' => $c->type,
                    'updated_at' => $c->updated_at,
                ],
            ]),
        ]);

    }

    /**
     * Create new content (used rarely once seeded)
     */
    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        Content::updateOrCreate(
            ['key' => $data['key']],
            [
                'value' => $data['value'],
                'type' => $data['type'],
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Content saved');
    }

    /**
     * Update existing content by key
     */
    public function update(Request $request, string $key)
    {
        $content = Content::where('key', $key)->firstOrFail();

        $data = $request->validate([
            'value' => 'nullable|string',
            'type'  => 'required|in:text,html,image',
        ]);

        // Clean up unwanted whitespace from the edges
        $cleanValue = trim($data['value']);

        $content->update([
            'value' => $cleanValue,
            'type' => $data['type'],
            'updated_by' => $request->user()->id,
        ]);

        return back();
    }

    /**
     * Upload & update image content
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'image' => 'required|image|max:5120',
        ]);

        $path = $request->file('image')->store('content', 'public');
        $url = Storage::url($path);

        Content::updateOrCreate(
            ['key' => $request->key],
            [
                'value' => $url,
                'type' => 'image',
                'updated_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Image updated');
    }

    /**
     * Shared validation
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'key'   => 'required|string',
            'value' => 'nullable',
            'type'  => 'required|in:text,html,image',
        ]);
    }
}
