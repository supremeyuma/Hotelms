<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomTypePriceSchedule;
use App\Models\RoomAvailabilitySchedule;
use App\Services\AuditLoggerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RoomSchedulingController extends Controller
{
    protected AuditLoggerService $auditLogger;

    public function __construct(AuditLoggerService $auditLogger)
    {
        $this->middleware(['auth', 'role:manager|md|superuser']);
        $this->auditLogger = $auditLogger;
    }

    /**
     * Dashboard overview of active and upcoming schedules.
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $priceSchedules = RoomTypePriceSchedule::with(['roomType', 'property'])
            ->where('end_date', '>=', $today)
            ->latest()
            ->get();

        $availabilitySchedules = RoomAvailabilitySchedule::with(['room', 'roomType', 'property'])
            ->where('end_date', '>=', $today)
            ->latest()
            ->get();

        return response()->json([
            'price_schedules' => $priceSchedules,
            'availability_schedules' => $availabilitySchedules,
            'summary' => [
                'active_price_schedules' => $priceSchedules->where('is_active', true)->count(),
                'active_unavailability' => $availabilitySchedules->where('is_unavailable', true)->count(),
                'total_rooms_blocked' => $availabilitySchedules
                    ->where('is_unavailable', true)
                    ->sum(fn ($schedule) => $schedule->room_id ? 1 : 0),
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