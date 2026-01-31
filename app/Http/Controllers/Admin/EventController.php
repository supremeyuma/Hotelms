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
            'end_time' => 'nullable|date_format:H:i',
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
            'table_types' => 'nullable|array',
            'ticket_types.*.name' => 'nullable|string|max:255',
            'ticket_types.*.description' => 'nullable|string',
            'ticket_types.*.price' => 'nullable|numeric|min:0',
            'ticket_types.*.quantity_available' => 'nullable|integer|min:1',
            'ticket_types.*.max_per_person' => 'nullable|integer|min:1|max:20',
            'ticket_types.*.sales_start' => 'nullable|date',
            'ticket_types.*.sales_end' => 'nullable|date|after:ticket_types.*.sales_start',
            'ticket_types.*.color_code' => 'nullable|string|max:7',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('events', 'public');
            $data['image'] = $imagePath;
        }

        $event = Event::create($data);

        // Handle promotional media
        if ($request->has('media')) {
            foreach ($request->media as $mediaData) {
                if (isset($mediaData['file']) && $mediaData['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $mediaData['file']->store('events/promotional', 'public');
                    
                    // If this is set as main image, unset any existing main image
                    if (!empty($mediaData['is_main_image'])) {
                        EventPromotionalMedia::where('event_id', $event->id)
                            ->update(['is_main_image' => false]);
                    }
                    
                    EventPromotionalMedia::create([
                        'event_id' => $event->id,
                        'media_type' => $mediaData['media_type'] ?? 'image',
                        'media_url' => $filePath,
                        'title' => $mediaData['title'] ?? null,
                        'description' => $mediaData['description'] ?? null,
                        'sort_order' => EventPromotionalMedia::where('event_id', $event->id)->max('sort_order') + 1,
                        'is_main_image' => !empty($mediaData['is_main_image']),
                    ]);
                }
            }
        }

        // Handle ticket types
        if ($request->has('ticket_types')) {
            foreach ($request->ticket_types as $ticketTypeData) {
                if (!empty($ticketTypeData['name'])) {
                    EventTicketType::create(array_merge($ticketTypeData, [
                        'event_id' => $event->id,
                    ]));
                }
            }
        }

        // Handle table types
        if ($request->has('table_types')) {
            foreach ($request->table_types as $tableTypeData) {
                if (!empty($tableTypeData['name'])) {
                    EventTicketType::create(array_merge($tableTypeData, [
                        'event_id' => $event->id,
                    ]));
                }
            }
        }

        return redirect()->route('admin.events.show', $event)
            ->with('success', 'Event updated successfully!');
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
            'end_time' => 'nullable|date_format:H:i',
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
            'removed_media' => 'nullable|array',
            'removed_media.*' => 'integer',
            'media' => 'nullable|array',
            'media.*.file' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:10240',
            'media.*.media_type' => 'nullable|in:image,video',
            'media.*.title' => 'nullable|string|max:255',
            'media.*.description' => 'nullable|string',
            'media.*.is_main_image' => 'nullable|boolean',
            'existing_media' => 'nullable|array',
            'existing_media.*.id' => 'integer',
            'existing_media.*.title' => 'nullable|string|max:255',
            'existing_media.*.description' => 'nullable|string',
            'existing_media.*.is_main_image' => 'nullable|boolean',
            'table_types' => 'nullable|array',
            'table_types.*.name' => 'nullable|string|max:255',
            'table_types.*.description' => 'nullable|string',
            'table_types.*.price' => 'nullable|numeric|min:0',
            'table_types.*.capacity' => 'nullable|integer|min:1',
            'ticket_types' => 'nullable|array',
            'ticket_types.*.id' => 'nullable|integer',
            'ticket_types.*.name' => 'nullable|string|max:255',
            'ticket_types.*.description' => 'nullable|string',
            'ticket_types.*.price' => 'nullable|numeric|min:0',
            'ticket_types.*.quantity_available' => 'nullable|integer|min:1',
            'ticket_types.*.max_per_person' => 'nullable|integer|min:1|max:20',
            'ticket_types.*.sales_start' => 'nullable|date',
            'ticket_types.*.sales_end' => 'nullable|date|after:ticket_types.*.sales_start',
            'ticket_types.*.color_code' => 'nullable|string|max:7',
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

        // Handle removed media
        if ($request->has('removed_media')) {
            foreach ($request->removed_media as $mediaId) {
                $media = EventPromotionalMedia::find($mediaId);
                if ($media && $media->event_id === $event->id) {
                    Storage::disk('public')->delete($media->media_url);
                    $media->delete();
                }
            }
        }

        // Handle new media
        if ($request->has('media')) {
            foreach ($request->media as $mediaData) {
                if (isset($mediaData['file']) && $mediaData['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $mediaData['file']->store('events/promotional', 'public');
                    
                    // If this is set as main image, unset any existing main image
                    if (!empty($mediaData['is_main_image'])) {
                        EventPromotionalMedia::where('event_id', $event->id)
                            ->update(['is_main_image' => false]);
                    }
                    
                    EventPromotionalMedia::create([
                        'event_id' => $event->id,
                        'media_type' => $mediaData['media_type'] ?? 'image',
                        'media_url' => $filePath,
                        'title' => $mediaData['title'] ?? null,
                        'description' => $mediaData['description'] ?? null,
                        'sort_order' => EventPromotionalMedia::where('event_id', $event->id)->max('sort_order') + 1,
                        'is_main_image' => !empty($mediaData['is_main_image']),
                    ]);
                }
            }
        }

        // Handle existing media updates
        if ($request->has('existing_media')) {
            foreach ($request->existing_media as $mediaData) {
                if (!empty($mediaData['id'])) {
                    $media = EventPromotionalMedia::find($mediaData['id']);
                    if ($media && $media->event_id === $event->id) {
                        // If this is set as main image, unset any existing main image
                        if (!empty($mediaData['is_main_image'])) {
                            EventPromotionalMedia::where('event_id', $event->id)
                                ->where('id', '!=', $media->id)
                                ->update(['is_main_image' => false]);
                        }
                        
                        $media->update([
                            'title' => $mediaData['title'] ?? $media->title,
                            'description' => $mediaData['description'] ?? $media->description,
                            'is_main_image' => !empty($mediaData['is_main_image']),
                        ]);
                    }
                }
            }
        }

        // Handle ticket types
        if ($request->has('ticket_types')) {
            foreach ($request->ticket_types as $ticketTypeData) {
                if (!empty($ticketTypeData['name'])) {
                    if (!empty($ticketTypeData['id'])) {
                        // Update existing
                        $ticketType = EventTicketType::find($ticketTypeData['id']);
                        if ($ticketType && $ticketType->event_id === $event->id) {
                            $ticketType->update($ticketTypeData);
                        }
                    } else {
                        // Create new
                        EventTicketType::create(array_merge($ticketTypeData, [
                            'event_id' => $event->id,
                        ]));
                    }
                }
            }
        }

        return redirect()->route('admin.events.show', $event)
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
            'media.*.is_main_image' => 'nullable|boolean',
        ]);

        foreach ($data['media'] as $index => $mediaData) {
            $filePath = $mediaData['file']->store('events/promotional', 'public');
            
            // If this is set as main image, unset any existing main image
            if (!empty($mediaData['is_main_image'])) {
                EventPromotionalMedia::where('event_id', $event->id)
                    ->update(['is_main_image' => false]);
            }
            
            EventPromotionalMedia::create([
                'event_id' => $event->id,
                'media_type' => $mediaData['media_type'],
                'media_url' => $filePath,
                'title' => $mediaData['title'],
                'description' => $mediaData['description'],
                'sort_order' => EventPromotionalMedia::where('event_id', $event->id)->max('sort_order') + 1,
                'is_main_image' => !empty($mediaData['is_main_image']),
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