<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffAction\VerifyActionCodeRequest;
use App\Services\StaffActionService;
use App\Services\AuditLogger;

class StaffActionController extends Controller
{
    public function verify(VerifyActionCodeRequest $request, StaffActionService $service)
    {
        $this->authorize('verify', auth()->user());

        $valid = $service->verifyCode($request->action_code);

        AuditLogger::log('staff_action_code_verified', 'User', auth()->id(), [
            'valid' => $valid
        ]);

        return response()->json([
            'valid' => $valid
        ]);
    }
}
