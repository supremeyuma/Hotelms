<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function createPublic(Request $request): Response
    {
        return Inertia::render('Feedback/Create', $this->buildCreateProps(
            source: Feedback::SOURCE_PUBLIC,
            action: route('feedback.store'),
            audience: 'public',
            context: null,
            request: $request,
        ));
    }

    public function createStaff(Request $request): Response
    {
        return Inertia::render('Staff/Feedback/Create', $this->buildCreateProps(
            source: Feedback::SOURCE_STAFF,
            action: route('staff.feedback.store'),
            audience: 'staff',
            context: null,
            request: $request,
        ));
    }

    public function createGuest(Request $request): Response
    {
        $room = $request->attributes->get('room');
        $booking = $request->attributes->get('booking');
        $token = $request->attributes->get('roomAccessToken');

        return Inertia::render('Guest/Feedback/Create', $this->buildCreateProps(
            source: Feedback::SOURCE_GUEST,
            action: route('guest.feedback.store', ['token' => $token->token]),
            audience: 'guest',
            context: [
                'room_name' => $room?->display_name ?: $room?->name ?: $room?->room_number,
                'guest_name' => $booking?->guest_name,
            ],
            request: $request,
        ));
    }

    public function storePublic(Request $request): RedirectResponse
    {
        $this->storeFeedback($request, Feedback::SOURCE_PUBLIC);

        return back()->with('success', 'Your feedback has been submitted. Thank you for taking the time to share it.');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        $this->storeFeedback($request, Feedback::SOURCE_STAFF);

        return back()->with('success', 'Your feedback has been submitted. Management can review it without revealing your identity when anonymous is selected.');
    }

    public function storeGuest(Request $request): RedirectResponse
    {
        $this->storeFeedback(
            $request,
            Feedback::SOURCE_GUEST,
            $request->attributes->get('booking'),
            $request->attributes->get('room'),
        );

        return back()->with('success', 'Your feedback has been submitted. Thank you for helping us improve your stay.');
    }

    private function buildCreateProps(string $source, string $action, string $audience, ?array $context, Request $request): array
    {
        $user = $request->user();

        return [
            'formConfig' => [
                'source' => $source,
                'action' => $action,
                'audience' => $audience,
                'prefill' => [
                    'contact_name' => $source === Feedback::SOURCE_STAFF && $user ? $user->name : '',
                    'contact_email' => $source === Feedback::SOURCE_STAFF && $user ? $user->email : '',
                    'contact_phone' => '',
                    'is_anonymous' => true,
                    'allow_follow_up' => false,
                ],
                'context' => $context,
                'category_options' => [
                    ['value' => 'service', 'label' => 'Service quality'],
                    ['value' => 'staff_conduct', 'label' => 'Staff conduct'],
                    ['value' => 'cleanliness', 'label' => 'Cleanliness'],
                    ['value' => 'food_and_beverage', 'label' => 'Food and beverage'],
                    ['value' => 'maintenance', 'label' => 'Maintenance'],
                    ['value' => 'safety', 'label' => 'Safety'],
                    ['value' => 'billing', 'label' => 'Billing or payment'],
                    ['value' => 'suggestion', 'label' => 'Suggestion'],
                    ['value' => 'compliment', 'label' => 'Compliment'],
                    ['value' => 'complaint', 'label' => 'Complaint'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
        ];
    }

    private function storeFeedback(Request $request, string $source, $booking = null, $room = null): Feedback
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'is_anonymous' => ['required', 'boolean'],
            'allow_follow_up' => ['nullable', 'boolean'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $isAnonymous = (bool) $validated['is_anonymous'];
        $allowFollowUp = $isAnonymous ? false : (bool) ($validated['allow_follow_up'] ?? false);
        $user = $request->user();

        if (!$isAnonymous && !$user && blank($validated['contact_name']) && blank($validated['contact_email']) && blank($validated['contact_phone'])) {
            throw ValidationException::withMessages([
                'contact_name' => 'Provide a contact name, email, or phone number when submitting non-anonymous feedback.',
            ]);
        }

        return Feedback::create([
            'source' => $source,
            'category' => $validated['category'],
            'status' => Feedback::STATUS_NEW,
            'subject' => $validated['subject'] ?: null,
            'message' => $validated['message'],
            'rating' => $validated['rating'] ?: null,
            'is_anonymous' => $isAnonymous,
            'allow_follow_up' => $allowFollowUp,
            'contact_name' => $isAnonymous ? null : ($validated['contact_name'] ?: $user?->name),
            'contact_email' => $isAnonymous ? null : ($validated['contact_email'] ?: $user?->email),
            'contact_phone' => $isAnonymous ? null : ($validated['contact_phone'] ?: null),
            'submitted_by_user_id' => $isAnonymous ? null : $user?->id,
            'booking_id' => $isAnonymous ? null : $booking?->id,
            'room_id' => $isAnonymous ? null : $room?->id,
            'metadata' => [
                'submitted_from' => $source,
                'submitted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
