<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LaundryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaundryItemController extends Controller
{
    public function index()
    {
        return Inertia::render('Staff/LaundryItems/Index', [
            'items' => LaundryItem::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        LaundryItem::create($data);

        return back()->with('success', 'Laundry item created.');
    }

    public function update(Request $request, LaundryItem $laundryItem)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $laundryItem->update($data);

        return back()->with('success', 'Laundry item updated.');
    }

    public function destroy(LaundryItem $laundryItem)
    {
        $laundryItem->delete();

        return back()->with('success', 'Laundry item deleted.');
    }
}
