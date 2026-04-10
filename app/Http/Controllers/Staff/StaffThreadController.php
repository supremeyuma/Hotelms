<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffThread;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffThreadController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:staff|manager|md|frontdesk|laundry|hr|clean|kitchen|bar|inventory|accountant|Accountant|maintenance']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $threadQuery = StaffThread::query()
            ->where('staff_id', $user->id);

        $threads = (clone $threadQuery)
            ->with(['latestMessage.sender:id,name'])
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Staff/Threads/Index', [
            'threads' => $threads,
            'summary' => [
                [
                    'label' => 'Total conversations',
                    'value' => (clone $threadQuery)->count(),
                    'helper' => 'All your recorded conversations with leadership',
                ],
                [
                    'label' => 'Queries',
                    'value' => (clone $threadQuery)->where('type', 'query')->count(),
                    'helper' => 'Questions, requests, and issues raised',
                ],
                [
                    'label' => 'Commendations',
                    'value' => (clone $threadQuery)->where('type', 'commendation')->count(),
                    'helper' => 'Recognition and positive follow-ups',
                ],
            ],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Staff/Threads/Create', [
            'staff' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:query,commendation',
            'title' => 'nullable|string|max:191',
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:8192',
        ]);

        if (! $this->hasMessageBody($data['message'] ?? null) && ! $request->hasFile('attachments')) {
            return back()->withErrors([
                'message' => 'Add a message or at least one attachment before sending.',
            ]);
        }

        $thread = StaffThread::create([
            'staff_id' => $request->user()->id,
            'admin_id' => $request->user()->hasAnyRole(['manager', 'md', 'hr']) ? $request->user()->id : null,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
        ]);

        $thread->messages()->create([
            'sender_id' => $request->user()->id,
            'message' => $this->normalizeMessage($data['message'] ?? null),
            'attachments' => $this->storeAttachments($request),
        ]);

        return redirect()
            ->route('staff.threads.show', $thread)
            ->with('success', 'Conversation started.');
    }

    public function show(Request $request, StaffThread $thread)
    {
        $this->ensureOwnThread($request, $thread);

        $thread->load(['staff:id,name', 'messages.sender:id,name']);
        $thread->loadCount('messages');

        return Inertia::render('Staff/Threads/Show', [
            'thread' => $thread,
        ]);
    }

    public function storeMessage(Request $request, StaffThread $thread)
    {
        $this->ensureOwnThread($request, $thread);

        $data = $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:5120',
        ]);

        if (! $this->hasMessageBody($data['message'] ?? null) && ! $request->hasFile('attachments')) {
            return back()->withErrors([
                'message' => 'Add a reply or attachment before sending.',
            ]);
        }

        $thread->messages()->create([
            'sender_id' => $request->user()->id,
            'message' => $this->normalizeMessage($data['message'] ?? null),
            'attachments' => $this->storeAttachments($request),
        ]);

        return back()->with('success', 'Reply sent.');
    }

    protected function ensureOwnThread(Request $request, StaffThread $thread): void
    {
        abort_unless($thread->staff_id === $request->user()->id, 403);
    }

    protected function storeAttachments(Request $request): array
    {
        $paths = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->store('staff_threads', 'public');
            }
        }

        return $paths;
    }

    protected function hasMessageBody(?string $message): bool
    {
        return trim((string) $message) !== '';
    }

    protected function normalizeMessage(?string $message): ?string
    {
        $message = trim((string) $message);

        return $message === '' ? null : $message;
    }
}
