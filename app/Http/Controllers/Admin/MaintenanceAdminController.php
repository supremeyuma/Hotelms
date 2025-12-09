<?php
// ========================================================
// Admin\MaintenanceAdminController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:manager|md']);
    }

    public function index()
    {
        $tickets = MaintenanceTicket::with('room','staff')->paginate(25);
        return Inertia::render('Admin/Maintenance/Index', compact('tickets'));
    }

    public function assign(Request $request, MaintenanceTicket $ticket)
    {
        $this->validate($request, ['staff_id' => 'required|exists:users,id']);
        $ticket->update(['staff_id' => $request->staff_id, 'status' => 'in_progress']);

        AuditLogger::log('maintenance_assigned', 'MaintenanceTicket', $ticket->id, ['staff'=> $request->staff_id]);

        return back()->with('success','Ticket assigned.');
    }

    public function updateStatus(Request $request, MaintenanceTicket $ticket)
    {
        $this->validate($request, ['status' => 'required|string|in:open,in_progress,resolved,closed,completed']);
        $old = $ticket->status;
        $ticket->update(['status' => $request->status]);

        AuditLogger::log('maintenance_status_updated', 'MaintenanceTicket', $ticket->id, ['from'=>$old,'to'=>$ticket->status]);

        return back()->with('success','Status updated.');
    }

    public function close(MaintenanceTicket $ticket)
    {
        $ticket->update(['status' => 'closed']);
        AuditLogger::log('maintenance_closed', 'MaintenanceTicket', $ticket->id);

        return back()->with('success','Ticket closed.');
    }
}
