<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffThread;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class StaffThreadController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr|md']);
    }

    /**
     * List all threads (queries & commendations)
     * Optionally, filter by staff_id if passed
     */
    public function index(User $staff)
    {
        $threads = StaffThread::with('staff', 'admin')
            ->where('staff_id', $staff->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Staff/Threads/Index', [
            'threads' => $threads,
            'staff' => $staff,
            'staffId' => $staff->id,
            'routePrefix' => $this->routePrefix(),
        ]);
    }


    public function create(User $staff)
    {
        return Inertia::render('Admin/Staff/Threads/Create', [
            'staff' => $staff,
            'staffId' => $staff->id,
            'routePrefix' => $this->routePrefix(),
        ]);
    }



    /**
     * Show a single thread with all messages
     */
    public function show(StaffThread $thread)
    {
        $thread->load('messages.sender', 'staff');
        return Inertia::render('Admin/Staff/Threads/Show', [
            'thread' => $thread,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    /**
     * Create a new thread for a staff member
     */
    public function createThread(Request $request, $staffId)
    {
        $data = $request->validate([
            'type' => 'required|in:query,commendation',
            'title' => 'nullable|string|max:191',
            'message' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:8192', // 8MB max
        ]);

        $paths = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('staff_threads', 'public');
            }
        }

        $thread = StaffThread::create([
            'staff_id' => $staffId,
            'admin_id' => auth()->id(),
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
        ]);

        $thread->messages()->create([
            'sender_id' => auth()->id(),
            'message' => $data['message'],
            'attachments' => $paths,
        ]);

        return redirect()->route($this->routePrefix() . '.threads.show', $thread->id);
    }

    /**
     * Store a message reply in a thread
     */
    public function storeMessage(Request $request, StaffThread $thread)
    {
        $data = $request->validate([
            'message' => 'nullable|string',
            'attachments.*' => 'nullable|file|max:5120', // 5MB each
        ]);

        $paths = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('staff_threads', 'public');
            }
        }

        $thread->messages()->create([
            'sender_id' => auth()->id(),
            'message' => $data['message'] ?? null,
            'attachments' => $paths,
        ]);

        return back()->with('success', 'Message sent');
    }

    protected function routePrefix(): string
    {
        return request()->route()?->named('hr.*') ? 'hr.staff' : 'admin.staff';
    }
}
