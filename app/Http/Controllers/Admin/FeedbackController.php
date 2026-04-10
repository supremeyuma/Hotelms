<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();
        $search = trim($request->string('search')->toString());
        $anonymousOnly = $request->boolean('anonymous');

        $query = Feedback::query()
            ->with(['submitter:id,name,email', 'reviewer:id,name', 'booking:id,booking_code,guest_name', 'room:id,name,display_name,room_number'])
            ->latest();

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($source !== '') {
            $query->where('source', $source);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        if ($anonymousOnly) {
            $query->where('is_anonymous', true);
        }

        return Inertia::render('Admin/Feedback/Index', [
            'feedback' => $query->paginate(15)->withQueryString()->through(function (Feedback $feedback) {
                return [
                    'id' => $feedback->id,
                    'source' => $feedback->source,
                    'category' => $feedback->category,
                    'status' => $feedback->status,
                    'subject' => $feedback->subject,
                    'message' => $feedback->message,
                    'rating' => $feedback->rating,
                    'is_anonymous' => $feedback->is_anonymous,
                    'allow_follow_up' => $feedback->allow_follow_up,
                    'contact_name' => $feedback->contact_name,
                    'contact_email' => $feedback->contact_email,
                    'contact_phone' => $feedback->contact_phone,
                    'submitter' => $feedback->is_anonymous ? null : $feedback->submitter?->only(['id', 'name', 'email']),
                    'reviewer' => $feedback->reviewer?->only(['id', 'name']),
                    'booking' => $feedback->booking ? [
                        'id' => $feedback->booking->id,
                        'booking_code' => $feedback->booking->booking_code,
                        'guest_name' => $feedback->booking->guest_name,
                    ] : null,
                    'room' => $feedback->room ? [
                        'id' => $feedback->room->id,
                        'name' => $feedback->room->display_name ?: $feedback->room->name ?: $feedback->room->room_number,
                    ] : null,
                    'internal_notes' => $feedback->internal_notes,
                    'reviewed_at' => $feedback->reviewed_at?->toDateTimeString(),
                    'created_at' => $feedback->created_at->toDateTimeString(),
                ];
            }),
            'filters' => [
                'status' => $status,
                'source' => $source,
                'search' => $search,
                'anonymous' => $anonymousOnly,
            ],
            'stats' => [
                'new' => Feedback::where('status', Feedback::STATUS_NEW)->count(),
                'in_review' => Feedback::where('status', Feedback::STATUS_IN_REVIEW)->count(),
                'resolved' => Feedback::where('status', Feedback::STATUS_RESOLVED)->count(),
                'anonymous' => Feedback::where('is_anonymous', true)->count(),
            ],
            'statusOptions' => [
                ['value' => Feedback::STATUS_NEW, 'label' => 'New'],
                ['value' => Feedback::STATUS_IN_REVIEW, 'label' => 'In review'],
                ['value' => Feedback::STATUS_RESOLVED, 'label' => 'Resolved'],
                ['value' => Feedback::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'sourceOptions' => [
                ['value' => '', 'label' => 'All sources'],
                ['value' => Feedback::SOURCE_PUBLIC, 'label' => 'Public'],
                ['value' => Feedback::SOURCE_GUEST, 'label' => 'Guest'],
                ['value' => Feedback::SOURCE_STAFF, 'label' => 'Staff'],
            ],
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:new,in_review,resolved,archived'],
            'internal_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $feedback->update([
            'status' => $validated['status'],
            'internal_notes' => $validated['internal_notes'] ?: null,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => $validated['status'] === Feedback::STATUS_NEW ? null : now(),
        ]);

        return back()->with('success', 'Feedback review details updated.');
    }
}
