<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\DrinkStock;
use App\Models\MenuItem;
use App\Models\PosDocket;
use App\Models\PosSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        return Inertia::render('ClubPos/reports/Sales', array_merge(
            $this->salesPayload($request),
            [
                'staff' => $request->user(),
                'currentSession' => $this->currentSession($request),
            ]
        ));
    }

    public function salesData(Request $request)
    {
        return response()->json($this->salesPayload($request));
    }

    public function trends(Request $request)
    {
        return Inertia::render('ClubPos/reports/Trends', array_merge(
            $this->trendsPayload($request),
            [
                'staff' => $request->user(),
                'currentSession' => $this->currentSession($request),
            ]
        ));
    }

    public function trendsData(Request $request)
    {
        return response()->json($this->trendsPayload($request));
    }

    public function stock()
    {
        $items = MenuItem::where('service_area', 'bar')
            ->where('is_available', true)
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $stock = DrinkStock::where('menu_item_id', $item->id)->first();
                $movements = collect();
                if ($stock) {
                    $movements = $stock->movements()
                        ->latest()
                        ->take(20)
                        ->get();
                }

                return [
                    'menu_item' => $item,
                    'stock' => $stock,
                    'recent_movements' => $movements,
                    'is_low_stock' => $stock ? $stock->full_bottles <= $stock->low_stock_threshold : true,
                    'estimated_pours' => $stock ? $stock->estimated_pours_remaining : 0,
                ];
            });

        $summary = [
            'total_items' => $items->count(),
            'low_stock_items' => $items->where('is_low_stock', true)->count(),
            'total_bottles' => $items->sum(fn ($i) => $i['stock']?->full_bottles ?? 0),
            'estimated_total_pours' => $items->sum(fn ($i) => $i['estimated_pours'] ?? 0),
        ];

        return response()->json([
            'summary' => $summary,
            'items' => $items,
        ]);
    }

    private function salesPayload(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $validated['date_from'] ?? now()->startOfDay()->toDateString();
        $dateTo = $validated['date_to'] ?? now()->endOfDay()->toDateString();

        $dockets = PosDocket::where('status', 'closed')
            ->whereBetween('closed_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->with(['items', 'payments', 'staff', 'session'])
            ->orderBy('closed_at', 'desc')
            ->get();

        $totalSales = $dockets->sum('total');
        $totalDockets = $dockets->count();
        $averagePerDocket = $totalDockets > 0 ? $totalSales / $totalDockets : 0;

        $categoryBreakdown = DB::table('pos_docket_items')
            ->join('menu_items', 'pos_docket_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->whereIn('pos_docket_items.docket_id', $dockets->pluck('id'))
            ->select('menu_categories.name as category', DB::raw('SUM(pos_docket_items.subtotal) as total'))
            ->groupBy('menu_categories.name')
            ->get();

        $paymentMethodBreakdown = DB::table('pos_docket_payments')
            ->whereIn('docket_id', $dockets->pluck('id'))
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        $topItems = DB::table('pos_docket_items')
            ->whereIn('docket_id', $dockets->pluck('id'))
            ->select('item_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total'))
            ->groupBy('item_name')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $hourlyBreakdown = DB::table('pos_dockets')
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->select(DB::raw($this->hourExpression('closed_at').' as hour'), DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw($this->hourExpression('closed_at')))
            ->orderBy('hour')
            ->get();

        return [
            'summary' => [
                'total_sales' => $totalSales,
                'total_dockets' => $totalDockets,
                'average_per_docket' => round($averagePerDocket, 2),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'category_breakdown' => $categoryBreakdown,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'top_items' => $topItems,
            'hourly_breakdown' => $hourlyBreakdown,
            'dockets' => $dockets,
        ];
    }

    private function trendsPayload(Request $request): array
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:7|max:365',
        ]);

        $days = $validated['days'] ?? 30;
        $since = now()->subDays($days)->startOfDay();

        $dailyTrend = DB::table('pos_dockets')
            ->where('status', 'closed')
            ->where('closed_at', '>=', $since)
            ->select(
                DB::raw('DATE(closed_at) as date'),
                DB::raw('COUNT(*) as docket_count'),
                DB::raw('SUM(total) as total_sales')
            )
            ->groupBy(DB::raw('DATE(closed_at)'))
            ->orderBy('date')
            ->get();

        $topItems = DB::table('pos_docket_items')
            ->join('pos_dockets', 'pos_docket_items.docket_id', '=', 'pos_dockets.id')
            ->where('pos_dockets.status', 'closed')
            ->where('pos_dockets.closed_at', '>=', $since)
            ->select(
                'pos_docket_items.item_name',
                DB::raw('SUM(pos_docket_items.quantity) as total_qty'),
                DB::raw('SUM(pos_docket_items.subtotal) as total')
            )
            ->groupBy('pos_docket_items.item_name')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $staffPerformance = DB::table('pos_dockets')
            ->where('status', 'closed')
            ->where('closed_at', '>=', $since)
            ->join('users', 'pos_dockets.staff_id', '=', 'users.id')
            ->select(
                'users.name as staff_name',
                DB::raw('COUNT(*) as docket_count'),
                DB::raw('SUM(total) as total_sales')
            )
            ->groupBy('users.name')
            ->orderBy('total_sales', 'desc')
            ->get();

        $paymentMethodTrend = DB::table('pos_docket_payments')
            ->join('pos_dockets', 'pos_docket_payments.docket_id', '=', 'pos_dockets.id')
            ->where('pos_dockets.status', 'closed')
            ->where('pos_dockets.closed_at', '>=', $since)
            ->select('pos_docket_payments.payment_method', DB::raw('SUM(pos_docket_payments.amount) as total'))
            ->groupBy('pos_docket_payments.payment_method')
            ->get();

        return [
            'daily_trend' => $dailyTrend,
            'top_items' => $topItems,
            'staff_performance' => $staffPerformance,
            'payment_method_trend' => $paymentMethodTrend,
            'period_days' => $days,
        ];
    }

    private function hourExpression(string $column): string
    {
        // The hour-of-day of a timestamp is spelled differently on every engine
        // the app can run on: SQLite has strftime, Postgres has EXTRACT, and
        // MySQL has HOUR. MySQL is the deployment default, so it stays the
        // fallback; Postgres is the cloud target and must not be left to HOUR.
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            'pgsql' => "CAST(EXTRACT(HOUR FROM {$column}) AS INTEGER)",
            default => "HOUR({$column})",
        };
    }

    private function currentSession(Request $request): ?PosSession
    {
        return PosSession::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }
}
