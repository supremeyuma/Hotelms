<?php
// ========================================================
// Admin\ReportController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use App\Models\InventoryLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use DB;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:manager|md']);
    }

    /**
     * Dashboard summary and report endpoints
     */
    public function index(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->toDateString());

        // Occupancy: percent of occupied rooms vs total in range
        $totalRooms = DB::table('rooms')->count();
        $occupiedDays = Booking::whereBetween('check_in', [$from, $to])->count();
        $occupancy = $totalRooms ? round(($occupiedDays / $totalRooms) * 100, 2) : 0;

        // Revenue: sum of bookings total_amount
        $revenue = Booking::whereBetween('created_at', [$from, $to])->sum('total_amount');

        // Staff performance: orders completed per staff
        $staffPerformance = User::role('staff')->withCount(['orders as completed_orders_count' => function($q){
            $q->where('status','completed');
        }])->get(['id','name']);

        // Inventory usage
        $inventoryUsage = InventoryLog::select(DB::raw('inventory_item_id, SUM(ABS(change)) as used'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('inventory_item_id')->get();

        // Orders counts
        $ordersCount = Order::whereBetween('created_at', [$from,$to])->count();

        return Inertia::render('Admin/Reports/Index', [
            'occupancy' => $occupancy,
            'revenue' => $revenue,
            'staffPerformance' => $staffPerformance,
            'inventoryUsage' => $inventoryUsage,
            'ordersCount' => $ordersCount,
            'range' => ['from'=>$from,'to'=>$to],
        ]);
    }

    /**
     * Occupancy detail per day (example)
     */
    public function occupancyDetail(Request $request)
    {
        $from = $request->input('from', Carbon::now()->subWeek()->toDateString());
        $to = $request->input('to', Carbon::now()->toDateString());

        $rows = Booking::selectRaw('DATE(check_in) as date, COUNT(*) as bookings')
            ->whereBetween('check_in', [$from, $to])
            ->groupBy('date')
            ->orderBy('date','asc')
            ->get();

        return response()->json($rows);
    }
}
