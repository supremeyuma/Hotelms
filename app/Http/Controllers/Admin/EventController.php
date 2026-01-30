<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\EventPromotionalMedia;
use App\Services\EventService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    public function __construct(
        protected EventService $eventService
    ) {}

    public function index()
    {
        $events = Event::with(['ticketTypes', 'tableReservations'])
            ->withCount(['tickets', 'tableReservations'])
            ->orderBy('event_date', 'desc')
            ->get();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'event_date' => $event->formatted_date,
                    'start_time' => $event->start_time?->format('g:i A'),
                    'end_time' => $event->end_time?->format('g:i A'),
                    'venue' => $event->venue,
                    'capacity' => $event->capacity,
                    'is_active' => $event->is_active,
                    'is_featured' => $event->is_featured,
                    'status' => $event->status,
                    'ticket_types_count' => $event->ticket_types_count,
                    'tables_reserved' => $event->table_reservations_count,
                    'tickets_sold' => $event->tickets_count,
                    'total_revenue' => $event->total_tickets_revenue,
                ];
            }),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Events/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'ticket_sales_start' => 'nullable|date',
            'ticket_sales_end' => 'nullable|date|after:ticket_sales_start',
            'max_tickets_per_person' => 'nullable|integer|min:1|max:20',
            'has_table_reservations' => 'boolean',
            'table_capacity' => 'nullable|integer|min:1',
            'table_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('events', 'public');
            $data['image'] = $imagePath;
        }

        $event = Event::create($data);

        return redirect()->route('admin.events.index')
            ->with('success', 'Event created successfully!');
    }

    public function show(Event $event)
    {
        $event->load(['ticketTypes', 'promotionalMedia', 'tickets', 'tableReservations']);
        $statistics = $this->eventService->getEventStatistics($event);

        return Inertia::render('Admin/Events/Show', [
            'event' => $event,
            'statistics' => $statistics,
            'qr_code' => $this->eventService->generateEventQRCode($event),
        ]);
    }

    public function edit(Event $event)
    {
        $event->load(['ticketTypes', 'promotionalMedia']);

        return Inertia::render('Admin/Events/Edit', [
            'event' => $event,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'ticket_sales_start' => 'nullable|date',
            'ticket_sales_end' => 'nullable|date|after:ticket_sales_start',
            'max_tickets_per_person' => 'nullable|integer|min:1|max:20',
            'has_table_reservations' => 'boolean',
            'table_capacity' => 'nullable|integer|min:1',
            'table_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }
            
            $imagePath = $request->file('image')->store('events', 'public');
            $data['image'] = $imagePath;
        }

        $event->update($data);

        return redirect()->route('admin.events.index')
            ->with('success', 'Event updated successfully!');
    }

    public function destroy(Event $event)
    {
        // Delete image if exists
        if ($event->image) {
            Storage::disk('public')->delete($event->image);
        }

        $event->delete();

        return redirect()->route('admin.events.index')
            ->with('success', 'Event deleted successfully!');
    }

    public function storeTicketTypes(Request $request, Event $event)
    {
        $data = $request->validate([
            'ticket_types' => 'required|array|min:1',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.description' => 'nullable|string',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity_available' => 'required|integer|min:1',
            'ticket_types.*.max_per_person' => 'nullable|integer|min:1|max:20',
            'ticket_types.*.sales_start' => 'nullable|date',
            'ticket_types.*.sales_end' => 'nullable|date|after:ticket_types.*.sales_start',
            'ticket_types.*.color_code' => 'nullable|string|max:7',
        ]);

        $this->eventService->createTicketTypes($event, $data['ticket_types']);

        return redirect()->route('admin.events.show', $event)
            ->with('success', 'Ticket types created successfully!');
    }

    public function featureEvent(Event $event)
    {
        $this->eventService->featureEvent($event->id);

        return redirect()->route('admin.events.index')
            ->with('success', 'Event featured successfully!');
    }

    public function unfeatureEvent(Event $event)
    {
        $this->eventService->unfeatureEvent($event->id);

        return redirect()->route('admin.events.index')
            ->with('success', 'Event unfeatured successfully!');
    }

    public function uploadPromotionalMedia(Request $request, Event $event)
    {
        $data = $request->validate([
            'media' => 'required|array|min:1',
            'media.*.media_type' => 'required|in:image,video',
            'media.*.title' => 'nullable|string|max:255',
            'media.*.description' => 'nullable|string',
            'media.*.file' => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:10240', // 10MB max
        ]);

        foreach ($data['media'] as $mediaData) {
            $filePath = $mediaData['file']->store('events/promotional', 'public');
            
            EventPromotionalMedia::create([
                'event_id' => $event->id,
                'media_type' => $mediaData['media_type'],
                'media_url' => $filePath,
                'title' => $mediaData['title'],
                'description' => $mediaData['description'],
                'sort_order' => EventPromotionalMedia::where('event_id', $event->id)->max('sort_order') + 1,
            ]);
        }

        return redirect()->route('admin.events.show', $event)
            ->with('success', 'Promotional media uploaded successfully!');
    }

    public function deletePromotionalMedia(EventPromotionalMedia $media)
    {
        Storage::disk('public')->delete($media->media_url);
        $media->delete();

        return back()->with('success', 'Media deleted successfully!');
    }

    public function reorderPromotionalMedia(Request $request, Event $event)
    {
        $data = $request->validate([
            'media_order' => 'required|array',
            'media_order.*' => 'required|integer|exists:event_promotional_media,id',
        ]);

        foreach ($data['media_order'] as $index => $mediaId) {
            EventPromotionalMedia::where('event_id', $event->id)
                ->where('id', $mediaId)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }
}