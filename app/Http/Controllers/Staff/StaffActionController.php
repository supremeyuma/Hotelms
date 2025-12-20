<?php
// ========================================================
// Staff\StaffActionController.php
// Namespace: App\Http\Controllers\Staff
// ========================================================
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffActionRequest;
use App\Services\StaffActionService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffActionController extends Controller
{
    protected StaffActionService $service;

    public function __construct(StaffActionService $service)
    {
        $this->service = $service;
        $this->middleware(['auth','role:Staff|manager|md']);
    }

    /**
     * Quick action page (get)
     */
    public function index(Request $request)
    {
        return Inertia::render('Staff/QuickAction', [
            'user' => $request->user()
        ]);
    }

    /**
     * recordAction - verify code and log
     * Accepts: staff_action_code, action_type, related_model_type, related_model_id
     */
    public function recordAction(StaffActionRequest $request)
    {
        $data = $request->validated();

        $staffId = auth()->id();
        $actionType = $data['action_type'] ?? 'staff_action';
        $relatedModel = $data['related_model'] ?? null;
        $relatedId = $data['related_id'] ?? null;

        $result = $this->service->performAction($staffId, $data['action_code'], $actionType, $request);

        // Attach related model info to audit
        AuditLogger::log('staff_action_record', 'StaffActionAttempt', $staffId, [
            'result' => $result,
            'related' => [$relatedModel, $relatedId]
        ]);

        return response()->json($result);
    }
}
