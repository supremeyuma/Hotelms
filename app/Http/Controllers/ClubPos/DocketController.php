<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\PosDocket;
use App\Models\MenuItem;
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
            'session_id' => 'required|exists:pos_sessions,id',
            'table_identifier' => 'nullable|string|max:50',
            'customer_name' => 'nullable|string|max:100',
            'booking_id' => 'nullable|exists:bookings,id',
            'room_id' => 'nullable|exists:rooms,id',
        ]);

        $session = \App\Models\PosSession::findOrFail($validated['session_id']);
        $docket = $this->clubPos->openDocket(
            $session,
            $request->user(),
            $validated['table_identifier'] ?? null,
            $validated['customer_name'] ?? null,
            $validated['booking_id'] ?? null,
            $validated['room_id'] ?? null,
        );

        return response()->json($docket->load('items', 'payments', 'staff'), 201);
    }

    public function show(PosDocket $docket)
    {
        return response()->json(
            $docket->load(['items', 'items.menuItem', 'payments', 'staff', 'session'])
        );
    }

    public function addItem(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        if ($docket->status !== 'open') {
            return response()->json(['error' => 'Docket is not open.'], 422);
        }

        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);

        $this->clubPos->addItem($docket, $menuItem, $validated['quantity'], $request->user());

        return response()->json($docket->fresh()->load('items', 'payments'));
    }

    public function pay(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|string|in:cash,card,room_charge,mobile_money,voucher',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'payments.*.change_given' => 'nullable|numeric|min:0',
        ]);

        $this->clubPos->closeDocket($docket, $validated['payments'], $request->user());

        return response()->json($docket->fresh()->load('items', 'payments', 'staff'));
    }

    public function void(Request $request, PosDocket $docket)
    {
        if (!$request->user()->can('club.pos.docket.void')) {
            return response()->json(['error' => 'Unauthorized to void dockets.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->clubPos->voidDocket($docket, $validated['reason'], $request->user());

        return response()->json($docket->fresh());
    }
}
