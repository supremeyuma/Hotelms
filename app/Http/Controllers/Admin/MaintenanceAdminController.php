<?php
// ========================================================
// Admin\MaintenanceAdminController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceAdminController extends Controller
{
    public function __construct(protected AuditLoggerService $audit)
    {
        $this->middleware(['auth','role:manager|md']);
    }

    public function index()
    {
        $tickets = MaintenanceTicket::with('room', 'staff')
            ->latest()
            ->paginate(25)
            ->through(fn (MaintenanceTicket $ticket) => $this->transformTicket($ticket));

        return Inertia::render('Admin/Maintenance/Index', [
            'tickets' => $tickets,
            'staffOptions' => $this->maintenanceStaffOptions(),
            'stats' => [
                'open' => MaintenanceTicket::where('status', 'open')->count(),
                'in_progress' => MaintenanceTicket::where('status', 'in_progress')->count(),
                'resolved' => MaintenanceTicket::where('status', 'resolved')->count(),
                'unassigned' => MaintenanceTicket::whereNull('staff_id')->count(),
            ],
        ]);
    }

    public function show(MaintenanceTicket $ticket)
    {
        $ticket->load('room', 'staff');

        return Inertia::render('Admin/Maintenance/Show', [
            'ticket' => $this->transformTicket($ticket),
            'staffOptions' => $this->maintenanceStaffOptions(),
        ]);
    }

    public function update(Request $request, MaintenanceTicket $ticket)
    {
        $data = $request->validate([
            'staff_id' => 'nullable|exists:users,id',
            'status' => 'required|string|in:open,in_progress,resolved,closed',
            'manager_note' => 'nullable|string|max:1000',
        ]);

        $meta = $ticket->meta ?? [];
        $oldStatus = $ticket->status;
        $previousAssignee = $ticket->staff_id;

        if (!empty($data['manager_note'])) {
            $meta['manager_note'] = $data['manager_note'];
            $meta['last_reviewed_at'] = now()->toIso8601String();
        }

        $ticket->update([
            'staff_id' => $data['staff_id'] ?: null,
            'status' => $data['status'],
            'meta' => $meta,
        ]);

        if ($previousAssignee !== $ticket->staff_id) {
            $this->audit->log('maintenance_assigned', 'MaintenanceTicket', $ticket->id, [
                'from' => $previousAssignee,
                'to' => $ticket->staff_id,
            ]);
        }

        if ($oldStatus !== $ticket->status) {
            $this->audit->log('maintenance_status_updated', 'MaintenanceTicket', $ticket->id, [
                'from' => $oldStatus,
                'to' => $ticket->status,
            ]);
        }

        return back()->with('success', 'Maintenance ticket updated.');
    }

    protected function maintenanceStaffOptions()
    {
        $users = User::query()
            ->with('department')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['staff', 'Maintenance Staff', 'maintenance']))
            ->orderBy('name')
            ->get();

        $filtered = $users->filter(function (User $user) {
            $roleNames = $user->roles->pluck('name')->map(fn ($role) => str($role)->lower()->toString());
            $department = str($user->department?->name ?? '')->lower()->toString();

            return $roleNames->contains(fn ($role) => str_contains($role, 'maintenance'))
                || str_contains($department, 'maintenance');
        })->values();

        $staff = $filtered->isNotEmpty() ? $filtered : $users;

        return $staff->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'department' => $user->department?->name,
        ])->values();
    }

    protected function transformTicket(MaintenanceTicket $ticket): array
    {
        $meta = $ticket->meta ?? [];

        return [
            'id' => $ticket->id,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
            'room' => $ticket->room ? [
                'id' => $ticket->room->id,
                'name' => $ticket->room->name,
                'room_number' => $ticket->room->room_number,
            ] : null,
            'staff' => $ticket->staff ? [
                'id' => $ticket->staff->id,
                'name' => $ticket->staff->name,
            ] : null,
            'guest_name' => $meta['guest_name'] ?? null,
            'guest_email' => $meta['guest_email'] ?? null,
            'issue_type' => $meta['issue_type'] ?? null,
            'photo_path' => $meta['photo_path'] ?? null,
            'booking_id' => $meta['booking_id'] ?? null,
            'manager_note' => $meta['manager_note'] ?? null,
        ];
    }
}
