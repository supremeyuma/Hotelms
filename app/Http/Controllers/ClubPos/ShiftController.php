<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\PosSession;
use App\Models\PosDocket;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function __construct(
        private AuditLoggerService $audit,
    ) {}

    public function open(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:pos_devices,id',
            'cash_start' => 'nullable|numeric|min:0',
        ]);

        $existing = PosSession::where('user_id', $request->user()->id)
            ->where('device_id', $validated['device_id'])
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return $this->respond($request, $existing, 200);
        }

        $session = PosSession::create([
            'device_id' => $validated['device_id'],
            'user_id' => $request->user()->id,
            'opened_at' => now(),
            'cash_start' => $validated['cash_start'] ?? 0,
            'status' => 'open',
        ]);

        $this->audit->log('pos_shift_opened', $session, $session->id, [
            'user_id' => $request->user()->id,
            'device_id' => $validated['device_id'],
        ]);

        return $this->respond($request, $session, 201);
    }

    public function current(Request $request)
    {
        $session = PosSession::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->withCount(['dockets as open_dockets' => function ($q) {
                $q->where('status', 'open');
            }])
            ->withCount(['dockets as closed_dockets' => function ($q) {
                $q->where('status', 'closed');
            }])
            ->first();

        return response()->json($session);
    }

    public function show(PosSession $session)
    {
        $session->load([
            'dockets' => function ($q) {
                $q->with(['items', 'payments', 'staff'])->orderBy('docket_number');
            },
            'device',
            'user',
        ]);

        return response()->json($session);
    }

    public function close(Request $request, PosSession $session)
    {
        if ($session->status !== 'open') {
            return $this->respondError($request, 'Shift is not open.');
        }

        $openDockets = $session->dockets()->where('status', 'open')->count();
        if ($openDockets > 0) {
            return $this->respondError($request, "Cannot close shift. {$openDockets} docket(s) are still open.");
        }

        $validated = $request->validate([
            'cash_declared' => 'required|numeric|min:0',
        ]);

        $totalSales = $session->dockets()
            ->where('status', 'closed')
            ->sum('total');

        $cardTotal = $session->dockets()
            ->where('status', 'closed')
            ->whereHas('payments', function ($q) {
                $q->where('payment_method', 'card');
            })
            ->sum('total');

        $cashDeclared = $validated['cash_declared'];
        $expectedCash = $session->cash_start + $totalSales - $cardTotal;
        $variance = $cashDeclared - $expectedCash;

        $session->cash_declared = $cashDeclared;
        $session->cash_verified = $cashDeclared;
        $session->cash_variance = $variance;
        $session->card_total = $cardTotal;
        $session->total_sales = $totalSales;
        $session->docket_count = $session->dockets()->where('status', 'closed')->count();
        $session->closed_at = now();
        $session->status = 'closed';
        $session->supervisor_id = $request->user()->id;
        $session->reconciled_at = now();
        $session->save();

        $this->audit->log('pos_shift_closed', $session, $session->id, [
            'total_sales' => $totalSales,
            'cash_declared' => $cashDeclared,
            'expected_cash' => $expectedCash,
            'variance' => $variance,
        ]);

        return $this->respond($request, $session);
    }

    public function reconcile(Request $request, PosSession $session)
    {
        if ($session->status !== 'closed') {
            return $this->respondError($request, 'Shift must be closed before reconciliation.');
        }

        $validated = $request->validate([
            'cash_verified' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $expectedCash = $session->cash_start + $session->total_sales - $session->card_total;
        $variance = $validated['cash_verified'] - $expectedCash;

        $session->cash_verified = $validated['cash_verified'];
        $session->cash_variance = $variance;
        $session->notes = $validated['notes'] ?? $session->notes;
        $session->supervisor_id = $request->user()->id;
        $session->reconciled_at = now();
        $session->save();

        return $this->respond($request, $session);
    }

    private function respond(Request $request, $payload, int $status = 200)
    {
        if ($request->header('X-Inertia')) {
            return redirect()->back();
        }

        return response()->json($payload, $status);
    }

    private function respondError(Request $request, string $message, int $status = 422)
    {
        if ($request->header('X-Inertia')) {
            return redirect()->back()->withErrors(['shift' => $message]);
        }

        return response()->json(['error' => $message], $status);
    }
}
