<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\{Booking,InventoryLog,User,Room};
use Carbon\Carbon;
use Cache;
use DB;
use Inertia\Inertia;
use Illuminate\Http\Request;


class ReportController extends Controller
{
public function index(Request $request)
{
[$from,$to] = $this->range($request);


return Inertia::render('Admin/Reports/Index', [
'summary' => Cache::remember("report:summary:$from:$to", 3600, fn() => [
'occupancy' => $this->occupancyRate($from,$to),
'revenue' => Booking::whereBetween('created_at', [$from,$to])->sum('total_amount'),
'adr' => Booking::whereBetween('check_in', [$from,$to])->avg('nightly_rate'),
'revpar' => $this->revpar($from,$to),
'orders' => DB::table('orders')->whereBetween('created_at',[$from,$to])->count()
]),
'range' => compact('from','to')
]);
}


public function occupancy(Request $request)
{
[$from,$to] = $this->range($request);


$data = Booking::selectRaw('DATE(check_in) as date, COUNT(*) as bookings')
->whereBetween('check_in',[$from,$to])
->groupBy('date')->orderBy('date')->get();


return response()->json($data);
}


public function revenue(Request $request)
{
[$from,$to] = $this->range($request);


return Booking::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
->whereBetween('created_at',[$from,$to])
->groupBy('date')->orderBy('date')->get();
}


public function staff(Request $request)
{
return User::query()->role('staff')->withCount([
'orders as completed_orders' => fn($q)=>$q->where('status','completed')
])->get();
}


public function inventory(Request $request)
{
[$from,$to] = $this->range($request);


return InventoryLog::selectRaw('inventory_item_id, SUM(ABS(change)) as used')
->whereBetween('created_at',[$from,$to])
->groupBy('inventory_item_id')->get();
}


/* ---------- Helpers ---------- */


protected function range(Request $request)
{
return [
$request->input('from', now()->subMonth()->toDateString()),
$request->input('to', now()->toDateString())
];
}


protected function occupancyRate($from,$to)
{
$rooms = Room::count();
$days = Carbon::parse($from)->diffInDays($to);
if (!$rooms || !$days) return 0;


$occupied = Booking::where(function($q) use ($from,$to){
$q->whereBetween('check_in',[$from,$to])
->orWhereBetween('check_out',[$from,$to]);
})->selectRaw('SUM(DATEDIFF(LEAST(check_out, ?), GREATEST(check_in, ?))) as nights',[$to,$from])->value('nights');


return round(($occupied / ($rooms * $days)) * 100,2);
}


protected function revpar($from,$to)
{
$revenue = Booking::whereBetween('created_at',[$from,$to])->sum('total_amount');
$rooms = Room::count();
$days = Carbon::parse($from)->diffInDays($to);
return $rooms && $days ? round($revenue / ($rooms * $days),2) : 0;
}
}