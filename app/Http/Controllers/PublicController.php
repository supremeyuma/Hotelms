<?php
// app/Http/Controllers/PublicController.php
namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Event;
use Inertia\Inertia;

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
        return Inertia::render('Public/ClubLounge');
    }

    public function policies()
    {
        return Inertia::render('Public/Policies');
    }
}
