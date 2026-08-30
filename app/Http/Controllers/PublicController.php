<?php
// app/Http/Controllers/PublicController.php
namespace App\Http\Controllers;

use App\Mail\ContactSubmissionMail;
use App\Models\Content;
use App\Models\Gallery;
use App\Models\Event;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function home()
    {
        // Get featured events for homepage
        $featuredEvents = Event::where('is_featured', true)
            ->where('is_active', true)
            ->where('start_datetime', '>', now())
            ->with(['promotionalMedia', 'ticketTypes'])
            ->orderBy('start_datetime', 'asc')
            ->limit(6)
            ->get();
        
        return Inertia::render('Public/Home', [
            'gallery' => [
                'hero' => Gallery::where('category', 'home.hero')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get(),

                'beach' => Gallery::where('category', 'beach')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get(),

                'club' => Gallery::where('category', 'club')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get(),
            ],
            'featured_events' => $featuredEvents->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_datetime' => $event->start_datetime,
                    'end_datetime' => $event->end_datetime,
                    'venue' => $event->venue,
                    'is_featured' => $event->is_featured,
                    'promotional_media' => $event->promotionalMedia,
                    'ticket_types' => $event->ticketTypes,
                ];
            }),
        ]);
    }

    public function gallery()
    {
        return Inertia::render('Public/Gallery', [
            'items' => Gallery::where('is_active', true)->get()->groupBy('category')
        ]);
    }

    public function amenities()
    {
        return Inertia::render('Public/Amenities');
    }

    public function club()
    {
        $upcomingEvents = Event::where('is_active', true)
            ->where('start_datetime', '>', now())
            ->with(['promotionalMedia', 'ticketTypes'])
            ->orderBy('start_datetime', 'asc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'event_date' => $event->start_datetime->format('M d, Y'),
                    'start_time' => $event->start_datetime->format('g:i A'),
                    'end_time' => $event->end_datetime->format('g:i A'),
                    'venue' => $event->venue,
                    'promotional_media' => $event->promotionalMedia,
                ];
            });

        return Inertia::render('Public/ClubLounge', [
            'events' => $upcomingEvents,
        ]);
    }

    public function policies()
    {
        return Inertia::render('Public/Policies');
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact');
    }

    public function submitContactForm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = Setting::where('key', 'contact_email')->value('value')
            ?: config('mail.from.address');

        Mail::to($recipient)->send(new ContactSubmissionMail(
            hotelName: Content::where('key', 'site.name')->value('value') ?: config('app.name', 'Hotel'),
            name: $validated['name'],
            email: $validated['email'],
            subject: $validated['subject'],
            message: $validated['message'],
        ));

        return back()->with('success', 'Your message has been sent. Our team will be in touch shortly.');
    }
}
