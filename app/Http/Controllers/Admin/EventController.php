<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPromotionalMedia;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EventController extends Controller
{
    public function __construct(
        protected EventService $eventService
    ) {}

    public function index()
    {
        // 1. Updated order by start_datetime
        $events = Event::with(['promotionalMedia'])
            ->withCount(['tickets', 'tableReservations'])
            ->orderBy('start_datetime', 'desc')
            ->get();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    // 2. Formatting using the new Carbon objects
                    'start_datetime' => $event->start_datetime?->format('Y-m-d H:i'),
                    'end_datetime' => $event->end_datetime?->format('Y-m-d H:i'),
                    'venue' => $event->venue,
                    'capacity' => $event->capacity,
                    'is_active' => $event->is_active,
                    'is_featured' => $event->is_featured,
                    'promotional_media' => $event->promotionalMedia,
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
        // 3. Updated validation for start/end datetime
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_datetime' => 'required|date|after_or_equal:today',
            'end_datetime' => 'required|date|after:start_datetime',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'ticket_sales_start' => 'nullable|date',
            'ticket_sales_end' => 'nullable|date|after:ticket_sales_start',
            'max_tickets_per_person' => 'nullable|integer|min:1|max:20',
            'has_table_reservations' => 'boolean',
            'image' => 'nullable|image|max:2048',
            'ticket_types' => 'required|array|min:1',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity_available' => 'required|integer|min:1',
            'table_types' => 'nullable|array',
            'table_types.*.name' => 'required_if:has_table_reservations,true|string',
            'table_types.*.price' => 'required_if:has_table_reservations,true|numeric',
            'table_types.*.capacity' => 'required_if:has_table_reservations,true|integer',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('events', 'public');
        }
        
        // 4. Clean T from datetime strings for database
        $data['start_datetime'] = str_replace('T', ' ', $data['start_datetime']);
        $data['end_datetime'] = str_replace('T', ' ', $data['end_datetime']);

        $event = Event::create($data);

        // Handle Promotional Media
        if ($request->has('promotional_media')) {
            foreach ($request->promotional_media as $media) {
                if (isset($media['file'])) {
                    $path = $media['file']->store('events/promotional', 'public');
                    $event->promotionalMedia()->create([
                        'media_url' => $path,
                        'media_type' => $media['media_type'],
                        'title' => $media['title'] ?? null,
                        'is_main_image' => $media['is_main_image'] ?? false,
                    ]);
                }
            }
        }

        // Handle Tickets and Tables
        foreach ($request->ticket_types as $ticket) {
            $event->ticketTypes()->create($ticket);
        }

        if (!empty($data['has_table_reservations']) && !empty($data['table_types'])) {
            foreach ($data['table_types'] as $table) {
                if(!empty($table['name'])) $event->tableTypes()->create($table);
            }
        }

        return redirect()->route('admin.events.index')->with('success', 'Event created!');
    }

    public function edit(Event $event)
    {
        $event->load(['ticketTypes', 'tableTypes', 'promotionalMedia']);

        return Inertia::render('Admin/Events/Edit', [
            'event' => $event,
        ]);
    }

    public function show(Event $event)
    {
        $event->load(['ticketTypes', 'promotionalMedia', 'tableReservations', 'tableTypes']);

        $statistics = $this->eventService->getEventStatistics($event);
        $qrCode = $this->eventService->generateEventQRCode($event);

        return Inertia::render('Admin/Events/Show', [
            'event' => $event,
            'statistics' => $statistics,
            'qr_code' => $qrCode,
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date', 
            'end_datetime' => 'required|date|after:start_datetime',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'ticket_sales_start' => 'nullable|date',
            'ticket_sales_end' => 'nullable|date|after:ticket_sales_start',
            'max_tickets_per_person' => 'nullable|integer|min:1|max:20',
            'has_table_reservations' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'removed_media' => 'nullable|array',
            'media' => 'nullable|array',
            'existing_media' => 'nullable|array',
            'table_types' => 'nullable|array',
            'ticket_types' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            if ($event->image) Storage::disk('public')->delete($event->image);
            $data['image'] = $request->file('image')->store('events', 'public');
        }

        // 5. Normalizing date formats
        $data['start_datetime'] = str_replace('T', ' ', $request->start_datetime);
        $data['end_datetime'] = str_replace('T', ' ', $request->end_datetime);

        $event->update($data);

        // Handle Media Removal
        if ($request->has('removed_media')) {
            foreach ($request->removed_media as $mediaId) {
                $media = EventPromotionalMedia::find($mediaId);
                if ($media && $media->event_id === $event->id) {
                    Storage::disk('public')->delete($media->media_url);
                    $media->delete();
                }
            }
        }

        // Handle New Media
        if ($request->has('media')) {
            foreach ($request->media as $mediaData) {
                if (isset($mediaData['file']) && $mediaData['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $filePath = $mediaData['file']->store('events/promotional', 'public');
                    if (!empty($mediaData['is_main_image'])) {
                        EventPromotionalMedia::where('event_id', $event->id)->update(['is_main_image' => false]);
                    }
                    $event->promotionalMedia()->create([
                        'media_type' => $mediaData['media_type'] ?? 'image',
                        'media_url' => $filePath,
                        'title' => $mediaData['title'] ?? null,
                        'description' => $mediaData['description'] ?? null,
                        'is_main_image' => !empty($mediaData['is_main_image']),
                    ]);
                }
            }
        }

        // Sync Ticket Types
        if ($request->has('ticket_types')) {
            $incomingTicketIds = collect($request->ticket_types)->pluck('id')->filter()->toArray();
            $event->ticketTypes()->whereNotIn('id', $incomingTicketIds)->delete();

            foreach ($request->ticket_types as $ticketTypeData) {
                if (!empty($ticketTypeData['id'])) {
                    $event->ticketTypes()->find($ticketTypeData['id'])?->update($ticketTypeData);
                } else {
                    $event->ticketTypes()->create($ticketTypeData);
                }
            }
        }

        // Sync Table Types
        if ($request->has('table_types')) {
            $incomingTableIds = collect($request->table_types)->pluck('id')->filter()->toArray();
            $event->tableTypes()->whereNotIn('id', $incomingTableIds)->delete();

            foreach ($request->table_types as $tableData) {
                if (empty($tableData['name'])) continue;
                
                if (!empty($tableData['id'])) {
                    $event->tableTypes()->find($tableData['id'])?->update($tableData);
                } else {
                    $event->tableTypes()->create($tableData);
                }
            }
        }

        return redirect()->route('admin.events.show', $event)->with('success', 'Event updated successfully!');
    }

    public function destroy(Event $event)
    {
        if ($event->image) Storage::disk('public')->delete($event->image);
        $event->delete();
        return redirect()->route('admin.events.index')->with('success', 'Event deleted successfully!');
    }
}