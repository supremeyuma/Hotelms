<?php

namespace App\Http\Controllers\ClubPos;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\PosDocket;
use App\Models\MenuItem;
use App\Models\PosSession;
use App\Models\MenuCategory;
use App\Models\DrinkStock;
use App\Services\ClubPosService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ClubPosService $clubPos,
    ) {}

    public function index()
    {
        $user = request()->user();
        $device = null;

        return Inertia::render('ClubPos/Dashboard', [
            'device' => $device,
            'openDockets' => [],
            'currentSession' => null,
            'categories' => MenuCategory::where('type', 'bar')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with(['subcategories' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order');
                }])
                ->get(),
            'menuItems' => MenuItem::where('service_area', 'bar')
                ->where('is_available', true)
                ->with('category')
                ->orderBy('name')
                ->get(),
            'lowStockCount' => DrinkStock::where('is_active', true)
                ->whereColumn('full_bottles', '<=', 'low_stock_threshold')
                ->count(),
        ]);
    }

    public function posData(Request $request)
    {
        $user = $request->user();
        $session = PosSession::where('user_id', $user->id)
            ->where('status', 'open')
            ->with('dockets')
            ->latest()
            ->first();

        $openDockets = collect();
        if ($session) {
            $openDockets = $session->dockets()
                ->where('status', 'open')
                ->with(['items', 'items.menuItem', 'staff'])
                ->orderBy('opened_at')
                ->get();
        }

        return response()->json([
            'session' => $session,
            'openDockets' => $openDockets,
        ]);
    }
}
