<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomAvailabilitySchedule;
use App\Models\RoomType;
use App\Models\RoomTypePriceSchedule;
use App\Models\Property;
use App\Services\AuditLoggerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoomSchedulingController extends Controller
{
    protected AuditLoggerService $auditLogger;

    public function __construct(AuditLoggerService $auditLogger)
    {
        $this->middleware(['auth', 'role:manager|md|superuser']);
        $this->auditLogger = $auditLogger;
    }

    /**
     * Scheduling dashboard for price and availability plans.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today()->toDateString();

        $priceSchedules = RoomTypePriceSchedule::with(['roomType', 'property'])
            ->latest()
            ->get()
            ->map(fn (RoomTypePriceSchedule $schedule) => [
                'id' => $schedule->id,
                'room_type_id' => $schedule->room_type_id,
                'property_id' => $schedule->property_id,
                'start_date' => optional($schedule->start_date)->format('Y-m-d'),
                'end_date' => optional($schedule->end_date)->format('Y-m-d'),
                'custom_price' => (float) $schedule->custom_price,
                'description' => $schedule->description,
                'is_active' => (bool) $schedule->is_active,
                'room_type_title' => $schedule->roomType?->title,
                'base_price' => (float) ($schedule->roomType?->base_price ?? 0),
                'is_past' => optional($schedule->end_date)?->lt($today) ?? false,
            ])
            ->values();

        $availabilitySchedules = RoomAvailabilitySchedule::with(['room', 'roomType', 'property'])
            ->latest()
            ->get()
            ->map(fn (RoomAvailabilitySchedule $schedule) => [
                'id' => $schedule->id,
                'room_id' => $schedule->room_id,
                'room_type_id' => $schedule->room_type_id,
                'property_id' => $schedule->property_id,
                'start_date' => optional($schedule->start_date)->format('Y-m-d'),
                'end_date' => optional($schedule->end_date)->format('Y-m-d'),
                'reason' => $schedule->reason,
                'is_unavailable' => (bool) $schedule->is_unavailable,
                'notes' => $schedule->notes,
                'label' => $schedule->room
                    ? ($schedule->room->display_name ?? $schedule->room->name ?? $schedule->room->room_number)
                    : ($schedule->roomType?->title ? "All {$schedule->roomType->title} rooms" : 'All rooms'),
                'property_name' => $schedule->property?->name,
                'is_past' => optional($schedule->end_date)?->lt($today) ?? false,
            ])
            ->values();

        $roomTypes = RoomType::query()
            ->orderBy('title')
            ->get()
            ->map(fn (RoomType $type) => [
                'id' => $type->id,
                'title' => $type->title,
                'base_price' => (float) $type->base_price,
                'property_id' => $type->property_id,
            ]);

        $rooms = Room::with('roomType')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => $room->display_name ?? $room->name ?? $room->room_number,
                'room_type_id' => $room->room_type_id,
                'property_id' => $room->property_id,
                'room_type_title' => $room->roomType?->title,
            ]);

        $properties = Property::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $defaultPropertyId = $properties->first()->id ?? null;

        return Inertia::render('Admin/RoomScheduling/Index', [
            'priceSchedules' => $priceSchedules,
            'availabilitySchedules' => $availabilitySchedules,
            'roomTypes' => $roomTypes,
            'rooms' => $rooms,
            'properties' => $properties,
            'defaultPropertyId' => $defaultPropertyId,
            'today' => $today,
            'summary' => [
                'active_price_schedules' => $priceSchedules->where('is_active', true)->count(),
                'active_unavailability' => $availabilitySchedules
                    ->where('is_unavailable', true)
                    ->where('is_past', false)
                    ->count(),
                'rooms_blocked' => (function () use ($availabilitySchedules, $rooms) {
                    $active = $availabilitySchedules
                        ->where('is_unavailable', true)
                        ->where('is_past', false);

                    $blocked = $active->pluck('room_id')->filter()->unique()->count();

                    foreach ($active as $schedule) {
                        if ($schedule['room_id']) {
                            continue;
                        }
                        if ($schedule['room_type_id']) {
                            $blocked += $rooms
                                ->where('room_type_id', $schedule['room_type_id'])
                                ->where('property_id', $schedule['property_id'])
                                ->count();
                        } elseif ($schedule['property_id']) {
                            $blocked += $rooms
                                ->where('property_id', $schedule['property_id'])
                                ->count();
                        }
                    }

                    return $blocked;
                })(),
            ],
        ]);
    }

    /**
     * Bulk-create price and availability schedules in one transaction.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_schedules' => 'nullable|array',
            'price_schedules.*.room_type_id' => 'required|exists:room_types,id',
            'price_schedules.*.property_id' => 'required|exists:properties,id',
            'price_schedules.*.start_date' => 'required|date',
            'price_schedules.*.end_date' => 'required|date|after_or_equal:price_schedules.*.start_date',
            'price_schedules.*.custom_price' => 'required|numeric|min:0',
            'price_schedules.*.description' => 'nullable|string|max:255',
            'price_schedules.*.is_active' => 'nullable|boolean',
            'availability_schedules' => 'nullable|array',
            'availability_schedules.*.room_id' => 'nullable|exists:rooms,id',
            'availability_schedules.*.room_type_id' => 'nullable|exists:room_types,id',
            'availability_schedules.*.property_id' => 'required|exists:properties,id',
            'availability_schedules.*.start_date' => 'required|date',
            'availability_schedules.*.end_date' => 'required|date|after_or_equal:availability_schedules.*.start_date',
            'availability_schedules.*.reason' => 'required|string|max:255',
            'availability_schedules.*.is_unavailable' => 'nullable|boolean',
            'availability_schedules.*.notes' => 'nullable|string',
        ]);

        $created = DB::transaction(function () use ($data) {
            $priceCount = 0;
            $availabilityCount = 0;

            foreach ($data['price_schedules'] ?? [] as $item) {
                $schedule = RoomTypePriceSchedule::create($item);
                $this->auditLogger->log('room_type_price_schedule_created', $schedule, $schedule->id, $item);
                $priceCount++;
            }

            foreach ($data['availability_schedules'] ?? [] as $item) {
                if (empty($item['room_id']) && empty($item['room_type_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'availability_schedules' => 'Each availability schedule requires room_id or room_type_id.',
                    ]);
                }
                $schedule = RoomAvailabilitySchedule::create($item);
                $this->auditLogger->log('room_availability_schedule_created', $schedule, $schedule->id, $item);
                $availabilityCount++;
            }

            return ['price_schedules' => $priceCount, 'availability_schedules' => $availabilityCount];
        });

        return response()->json([
            'message' => 'Bulk update completed',
            'created' => $created,
        ], 201);
    }
}