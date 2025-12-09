<?php
// ========================================================
// Staff\StaffDashboardController.php
// Namespace: App\Http\Controllers\Staff
// ========================================================
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\MaintenanceTicket;
use App\Models\Booking;
use Inertia\Inertia;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:staff|manager|md']);
    }

    /**
     * Dashboard showing tasks assigned to staff
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $pendingOrders = Order::where('status','pending')->with('booking','items')->paginate(10);
        $myMaintenance = MaintenanceTicket::where('staff_id', $user->id)->whereIn('status', ['open','in_progress'])->get();
        $pendingBookings = Booking::where('status','pending')->count();

        return Inertia::render('Staff/Dashboard', [
            'pendingOrders' => $pendingOrders,
            'myMaintenance' => $myMaintenance,
            'pendingBookingsCount' => $pendingBookings,
            'user' => $user,
        ]);
    }
}
