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
}
