<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class StaffActionService
{
    /**
     * Verify a staff action code and log the action
     *
     * @param int $staffId The ID of the staff performing the action
     * @param string $actionCode The plain-text action code to validate
     * @param string $actionType A descriptive string of the action being performed
     * @param Request|null $request Optional, for IP logging
     * @return array ['success' => bool, 'message' => string]
     */
    public function performAction(int $staffId, string $actionCode, string $actionType, Request $request = null): array
    {
        // Fetch staff user with profile
        $staff = User::with('staffProfile')->find($staffId);

        if (!$staff || !$staff->staffProfile) {
            return [
                'success' => false,
                'message' => 'Staff profile not found.'
            ];
        }

        // Verify hashed action code
        $valid = Hash::check($actionCode, $staff->staffProfile->action_code_hash);

        // Record action attempt in AuditLog
        AuditLog::create([
            'user_id'    => $staffId,
            'action'     => $actionType,
            'ip_address' => $request?->ip() ?? request()->ip(),
            'metadata'   => [
                'staff_name' => $staff->name,
                'action_valid' => $valid,
            ],
        ]);

        return $valid
            ? ['success' => true, 'message' => 'Action code verified successfully.']
            : ['success' => false, 'message' => 'Invalid action code.'];
    }
}
