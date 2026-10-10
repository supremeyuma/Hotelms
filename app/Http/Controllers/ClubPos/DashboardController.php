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
use App\Models\PosDevice;

class DashboardController extends Controller
{
    public function __construct(
        private ClubPosService $clubPos,
    ) {}

    public function index()
    {
        $user = request()->user();
        $device = PosDevice::first() ?? PosDevice::create([
            "device_code" => "POS-001",
            "device_name" => "Club POS",
            "device_type" => "club",
            "is_active" => true,
        ]);

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
            "drinkStock" => DrinkStock::with("menuItem")
                ->orderBy("quantity")
                ->get(),
        ]);
    }

    public function posData()
    {
        return response()->json(["ok" => true]);
    }
}
