<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomTypePriceSchedule;
use App\Services\AuditLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomTypePriceScheduleController extends Controller
{
    protected AuditLoggerService $auditLogger;

    public function __construct(AuditLoggerService $auditLogger)
    {
        $this->middleware(['auth', 'role:manager|md|superuser']);
        $this->auditLogger = $auditLogger;
    }

    /**
     * Display a listing of price schedules
     */
    public function index(Request $request): JsonResponse
    {
        $query = RoomTypePriceSchedule::with(['roomType', 'property'])
            ->latest();

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->input('room_type_id'));
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->input('property_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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
     * Store a newly created price schedule
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'custom_price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $schedule = RoomTypePriceSchedule::create($data);

        $this->auditLogger->log('room_type_price_schedule_created', $schedule, $schedule->id, $data);

        return back()->with('success', 'Price schedule created successfully.');
    }

    /**
     * Update the specified price schedule
     */
    public function update(Request $request, RoomTypePriceSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'custom_price' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $this->auditLogger->logChange('room_type_price_schedule_updated', $schedule,
            ['before' => $schedule->toArray(), 'after' => $data]);

        $schedule->update($data);

        return back()->with('success', 'Price schedule updated successfully.');
    }

    /**
     * Remove the specified price schedule
     */
    public function destroy(RoomTypePriceSchedule $schedule): RedirectResponse
    {
        $this->auditLogger->log('room_type_price_schedule_deleted', $schedule, $schedule->id);

        $schedule->delete();

        return back()->with('success', 'Price schedule deleted successfully.');
    }
}