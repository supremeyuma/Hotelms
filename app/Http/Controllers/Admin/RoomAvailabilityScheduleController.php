<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomAvailabilitySchedule;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomAvailabilityScheduleController extends Controller
{
    protected AuditLoggerService $auditLogger;

    public function __construct(AuditLoggerService $auditLogger)
    {
        $this->middleware(['auth', 'role:manager|md|superuser']);
        $this->auditLogger = $auditLogger;
    }

    /**
     * Display a listing of availability schedules
     */
    public function index(Request $request): JsonResponse
    {
        $query = RoomAvailabilitySchedule::with(['room', 'roomType', 'property'])
            ->latest();

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->input('room_type_id'));
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->input('property_id'));
        }

        if ($request->filled('is_unavailable')) {
            $query->where('is_unavailable', $request->boolean('is_unavailable'));
        }

        $schedules = $query->paginate(10)->withQueryString();

        return response()->json([
            'schedules' => $schedules->items(),
            'pagination' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
        ]);
    }

    /**
     * Store a newly created availability schedule
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => 'nullable|exists:rooms,id',
            'room_type_id' => 'nullable|exists:room_types,id',
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:255',
            'is_unavailable' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if (empty($data['room_id']) && empty($data['room_type_id'])) {
            throw ValidationException::withMessages([
                'room_id' => 'Select a specific room or a room type to block.',
                'room_type_id' => 'Select a specific room or a room type to block.',
            ]);
        }

        $schedule = RoomAvailabilitySchedule::create($data);

        $this->auditLogger->log('room_availability_schedule_created', $schedule, $schedule->id, $data);

        return back()->with('success', 'Availability schedule created successfully.');
    }

    /**
     * Update the specified availability schedule
     */
    public function update(Request $request, RoomAvailabilitySchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'reason' => 'sometimes|required|string|max:255',
            'is_unavailable' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $this->auditLogger->logChange('room_availability_schedule_updated', $schedule,
            ['before' => $schedule->toArray(), 'after' => $data]);

        $schedule->update($data);

        return back()->with('success', 'Availability schedule updated successfully.');
    }

    /**
     * Remove the specified availability schedule
     */
    public function destroy(RoomAvailabilitySchedule $schedule): RedirectResponse
    {
        $this->auditLogger->log('room_availability_schedule_deleted', $schedule, $schedule->id);

        $schedule->delete();

        return back()->with('success', 'Availability schedule deleted successfully.');
    }
}