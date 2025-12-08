<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffActionRequest;
use App\Services\StaffActionService;
use Illuminate\Http\Request;
use App\Services\AuditLogger;

class StaffActionController extends Controller
{
    protected $staffActionService;

    public function __construct(StaffActionService $staffActionService)
    {
        $this->staffActionService = $staffActionService;
    }

    /**
     * Verify staff action code
     */
    public function verify(StaffActionRequest $request)
    {
        // Authenticated staff ID
        $staffId = auth()->id();
        $action  = $request->input('action_type'); // e.g. "delete_booking"

        // Perform action and validate code
        $result = $this->staffActionService->performAction(
            $staffId,
            $request->action_code,
            'verify_staff_action_code', // Action type string
            $request
        );

        AuditLogger::log('staff_action_code_verified', 'User', auth()->id(), [
            'actionType' => $action
        ]);

        return response()->json($result);
    }
}




