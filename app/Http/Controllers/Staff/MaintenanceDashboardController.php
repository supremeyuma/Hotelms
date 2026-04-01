<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceDashboardController extends Controller
{
    public function __construct(protected AuditLoggerService $audit)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user()->loadMissing('roles', 'department');

        abort_unless($this->canAccessMaintenance($user), 403);

        $tickets = MaintenanceTicket::with('room', 'staff')
            ->when(!$user->hasAnyRole(['manager', 'md']), function ($query) use ($user) {
                $query->where(function ($ticketQuery) use ($user) {
                    $ticketQuery
                        ->whereNull('staff_id')
                        ->orWhere('staff_id', $user->id);
                });
            })
            ->whereIn('status', ['open', 'in_progress', 'resolved'])
            ->latest()
            ->paginate(20)
            ->through(fn (MaintenanceTicket $ticket) => $this->transformTicket($ticket));

        return Inertia::render('Staff/Maintenance/Index', [
            'tickets' => $tickets,
            'stats' => [
                'open' => MaintenanceTicket::where('status', 'open')->count(),
                'in_progress' => MaintenanceTicket::where('status', 'in_progress')->count(),
                'resolved' => MaintenanceTicket::where('status', 'resolved')->count(),
                'mine' => MaintenanceTicket::where('staff_id', $user->id)
                    ->whereIn('status', ['open', 'in_progress', 'resolved'])
                    ->count(),
            ],
            'canManageAll' => $user->hasAnyRole(['manager', 'md']),
        ]);
    }

    public function updateStatus(Request $request, MaintenanceTicket $ticket)
    {
        $user = $request->user()->loadMissing('roles', 'department');

        abort_unless($this->canAccessMaintenance($user), 403);

        if (!$user->hasAnyRole(['manager', 'md']) && $ticket->staff_id && $ticket->staff_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
        ]);

        if (!$ticket->staff_id && !$user->hasAnyRole(['manager', 'md'])) {
            $ticket->staff_id = $user->id;
        }

        $oldStatus = $ticket->status;
        $ticket->status = $data['status'];
        $ticket->save();

        if ($oldStatus !== $ticket->status) {
            $this->audit->log('maintenance_status_updated', 'MaintenanceTicket', $ticket->id, [
                'from' => $oldStatus,
                'to' => $ticket->status,
            ]);
        }

        return back()->with('success', 'Maintenance status updated.');
    }

    protected function canAccessMaintenance($user): bool
    {
        if ($user->hasAnyRole(['manager', 'md'])) {
            return true;
        }

        $roleNames = collect($user->roles ?? [])->pluck('name')->map(fn ($role) => str($role)->lower()->toString());
        $department = str($user->department?->name ?? '')->lower()->toString();

        return $roleNames->contains(fn ($role) => str_contains($role, 'maintenance'))
            || str_contains($department, 'maintenance');
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
            'issue_type' => $meta['issue_type'] ?? null,
            'guest_name' => $meta['guest_name'] ?? null,
            'photo_path' => $meta['photo_path'] ?? null,
            'room' => $ticket->room ? [
                'id' => $ticket->room->id,
                'name' => $ticket->room->name,
                'room_number' => $ticket->room->room_number,
            ] : null,
            'staff' => $ticket->staff ? [
                'id' => $ticket->staff->id,
                'name' => $ticket->staff->name,
            ] : null,
        ];
    }
}
