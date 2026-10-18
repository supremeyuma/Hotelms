<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosSession;
use App\Print\PrintJob;
use App\Services\ClubPosService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

class DocketController extends Controller
{
    public function __construct(
        private ClubPosService $clubPos,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'nullable|exists:pos_sessions,id',
            'table_identifier' => 'nullable|string|max:50',
            'customer_name' => 'nullable|string|max:100',
            'booking_id' => 'nullable|exists:bookings,id',
            'room_id' => 'nullable|exists:rooms,id',
        ]);

        $session = null;
        if (! empty($validated['session_id'])) {
            $session = PosSession::findOrFail($validated['session_id']);
        } else {
            $identity = $request->cookie(config('pos.devices.identity_cookie'));
            $device = (config('pos.devices.auto_register') && $identity)
                ? $this->clubPos->resolveDevice($identity)
                : $this->clubPos->defaultDevice();

            $session = $this->clubPos->getOrCreateSession($device, $request->user());
        }

        $docket = $this->clubPos->openDocket(
            $session,
            $request->user(),
            $validated['table_identifier'] ?? null,
            $validated['customer_name'] ?? null,
            $validated['booking_id'] ?? null,
            $validated['room_id'] ?? null,
        );

        return $this->respond($request, $docket->load('items', 'payments', 'staff', 'booking', 'room'), 201);
    }

    public function attachBooking(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);

        try {
            $docket = $this->clubPos->attachBooking($docket, $booking, $request->user());
        } catch (\RuntimeException $e) {
            return $this->respondError($request, $e->getMessage());
        }

        return $this->respond($request, $docket->load('items', 'payments', 'booking', 'room'));
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
            'quantity' => 'required|integer|min:1|max:9999',
        ]);

        $menuItem = MenuItem::findOrFail($validated['menu_item_id']);

        try {
            $docket = $this->clubPos->addItem($docket, $menuItem, (int) $validated['quantity'], $request->user());
        } catch (\RuntimeException $e) {
            return $this->respondError($request, $e->getMessage());
        }

        return $this->respond($request, $docket->load('items', 'payments'));
    }

    public function removeItem(Request $request, PosDocket $docket, PosDocketItem $item)
    {
        abort_unless($item->docket_id === $docket->id, 404);

        try {
            $docket = $this->clubPos->removeItem($docket, $item, $request->user());
        } catch (\RuntimeException $e) {
            return $this->respondError($request, $e->getMessage());
        }

        return $this->respond($request, $docket->load('items', 'payments'));
    }

    public function pay(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,card,room_charge,mobile_money,voucher',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference' => 'nullable|string|max:100',
            'payments.*.change_given' => 'nullable|numeric|min:0',
            'payments.*.meta' => 'nullable|array',
        ]);

        try {
            $ack = $this->clubPos->completeSale($docket, $validated['payments'], $request->user());
        } catch (\RuntimeException $e) {
            return $this->respondError($request, $e->getMessage());
        }

        // The receipt is queued, never awaited: a sale must not fail or hang
        // because the printer is out of paper or the bridge is down.
        $this->queueReceipt($ack['docket']);

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('queued_for_sync', $ack['queued_for_sync']);
        }

        return response()->json([
            'docket' => $ack['docket'],
            'queued_for_sync' => $ack['queued_for_sync'],
            'receipt_queued' => true,
        ]);
    }

    /**
     * Reprint a closed docket's receipt. This is the recovery path when the
     * bridge is down or the paper jams — not how a guest normally gets served.
     */
    public function reprint(Request $request, PosDocket $docket)
    {
        if (! in_array($docket->status, ['closed', 'voided'], true)) {
            return $this->respondError($request, 'Only a closed docket can be reprinted.');
        }

        $this->queueReceipt($docket, isReprint: true);

        if ($request->header('X-Inertia')) {
            return redirect()->back()->with('receipt_queued', true);
        }

        return response()->json(['receipt_queued' => true], 202);
    }

    /**
     * Enqueue the receipt now and drain queued jobs once the response has been
     * sent, so printing can never block or fail the request that sold the
     * goods.
     */
    private function queueReceipt(PosDocket $docket, bool $isReprint = false): PrintJob
    {
        $job = PrintJob::createForDocket($docket, $isReprint);

        app()->terminating(function (): void {
            app()->make(Kernel::class)->call('print:dispatch');
        });

        return $job;
    }

    public function void(Request $request, PosDocket $docket)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $docket = $this->clubPos->voidDocket($docket, $validated['reason'], $request->user());
        } catch (\RuntimeException $e) {
            return $this->respondError($request, $e->getMessage());
        }

        return $this->respond($request, $docket->load('items', 'payments'));
    }

    private function respond(Request $request, $payload, int $status = 200)
    {
        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json($payload, $status);
    }

    private function respondError(Request $request, string $message)
    {
        if ($request->header('X-Inertia')) {
            return redirect()->back()->withErrors(['docket' => $message]);
        }

        return response()->json(['error' => $message], 422);
    }
}
