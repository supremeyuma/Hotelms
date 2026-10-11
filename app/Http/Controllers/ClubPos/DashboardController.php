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
use Illuminate\Support\Facades\Cookie;

class DashboardController extends Controller
{
    public function __construct(
        private ClubPosService $clubPos,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $cookieName = config('pos.devices.identity_cookie');
        $identity = $request->cookie($cookieName);

        if (!config('pos.devices.auto_register')) {
            $device = $this->clubPos->defaultDevice();
        } elseif ($identity) {
            $device = $this->clubPos->resolveDevice($identity);
        } else {
            $device = $this->clubPos->registerDevice();
            Cookie::queue(cookie()->forever($cookieName, $device->device_code));
        }

        $currentSession = PosSession::where("device_id", $device->id)
            ->where("user_id", $user->id)
            ->where("status", "open")
            ->latest()
            ->first();

        $openDockets = [];
        if ($currentSession) {
            $openDockets = PosDocket::where("session_id", $currentSession->id)
                ->where("status", "open")
                ->with(["items.menuItem", "payments"])
                ->get();
        }

        return Inertia::render("ClubPos/Dashboard", [
            "device" => $device,
            "openDockets" => $openDockets,
            "currentSession" => $currentSession,
            "club_pos" => [
                "pin_verified" => (bool) $request->session()->get("pos_pin_verified", false),
            ],
            "categories" => MenuCategory::where("type", "bar")
                ->where("is_active", true)
                ->orderBy("sort_order")
                ->with(["subcategories" => function ($q) {
                    $q->where("is_active", true)->orderBy("sort_order");
                }])
                ->get(),
            "menuItems" => MenuItem::where("service_area", "bar")
                ->where("is_available", true)
                ->with("category")
                ->orderBy("name")
                ->get(),
            "lowStockCount" => $this->lowStockCount(),
        ]);
    }

    private function lowStockCount(): int
    {
        $barItemIds = MenuItem::where("service_area", "bar")
            ->where("is_available", true)
            ->pluck("id");

        $healthyStock = DrinkStock::whereIn("menu_item_id", $barItemIds)
            ->whereColumn("full_bottles", ">", "low_stock_threshold")
            ->count();

        return $barItemIds->count() - $healthyStock;
    }

    public function posData()
    {
        return response()->json(["ok" => true]);
    }
}
