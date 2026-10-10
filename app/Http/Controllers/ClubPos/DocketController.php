<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Services\ClubPosService;
use Illuminate\Http\Request;

class DocketController extends Controller
{
    public function __construct(
        private ClubPosService $clubPos,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            "session_id" => "nullable|exists:pos_sessions,id",
            "table_identifier" => "nullable|string|max:50",
            "customer_name" => "nullable|string|max:100",
            "booking_id" => "nullable|exists:bookings,id",
            "room_id" => "nullable|exists:rooms,id",
        ]);

        $session = null;
        if (!empty($validated["session_id"])) {
            $session = PosSession::findOrFail($validated["session_id"]);
        } else {
            $device = PosDevice::first() ?? PosDevice::create([
                "device_code" => "POS-001",
                "device_name" => "Club POS",
                "device_type" => "club",
                "is_active" => true,
            ]);
            $session = $this->clubPos->getOrCreateSession($device, $request->user());
        }

        $docket = $this->clubPos->openDocket(
            $session,
            $request->user(),
            $validated["table_identifier"] ?? null,
            $validated["customer_name"] ?? null,
            $validated["booking_id"] ?? null,
            $validated["room_id"] ?? null,
        );

        return response()->json($docket->load("items", "payments", "staff"), 201);
    }

    public function show(PosDocket $docket)
    {
        return response()->json(
            $docket->load(["items", "items.menuItem", "payments", "staff", "session"])
        );
    }

    public function addItem(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            "menu_item_id" => "required|exists:menu_items,id",
            "quantity" => "required|integer|min:1|max:9999",
        ]);

        $menuItem = MenuItem::findOrFail($validated["menu_item_id"]);

        try {
            $docket = $this->clubPos->addItem($docket, $menuItem, (int) $validated["quantity"], $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        }

        return response()->json($docket->load("items", "payments"));
    }

    public function removeItem(Request $request, PosDocket $docket, PosDocketItem $item)
    {
        try {
            $docket = $this->clubPos->removeItem($docket, $item, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        }

        return response()->json($docket->load("items", "payments"));
    }

    public function pay(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            "payments" => "required|array|min:1",
            "payments.*.method" => "required|in:cash,card,room_charge,mobile_money,voucher",
            "payments.*.amount" => "required|numeric|min:0",
            "payments.*.reference" => "nullable|string|max:100",
            "payments.*.change_given" => "nullable|numeric|min:0",
            "payments.*.meta" => "nullable|array",
        ]);

        try {
            $docket = $this->clubPos->closeDocket($docket, $validated["payments"], $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        }

        return response()->json($docket->load("items", "payments"));
    }

    public function void(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            "reason" => "required|string|max:500",
        ]);

        try {
            $docket = $this->clubPos->voidDocket($docket, $validated["reason"], $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        }

        return response()->json($docket->load("items", "payments"));
    }
}