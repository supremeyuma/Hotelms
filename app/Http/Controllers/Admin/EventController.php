<?php
// app/Http/Controllers/Admin/EventController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Events/Index', [
            'events' => Event::orderBy('event_date')->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required',
            'description' => 'nullable',
            'event_date' => 'required|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
        ]);

        Event::create($data);
        return back();
    }
}
