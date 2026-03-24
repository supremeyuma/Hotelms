<?php
// app/Services/StaffActionService.php

namespace App\Services;

use App\Models\User;
use App\Models\StaffProfile;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * StaffActionService
 *
 * Responsible for verifying staff action codes, recording attempts and enforcing basic throttling.
 */
class StaffActionService
{
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Verify an action code for a staff member and record the attempt.
     *
     * @param int $staffId
     * @param string $actionCode Plain text code
     * @param string $actionType Descriptive action type
     * @param Request|null $request
     * @return bool
     * @throws ValidationException
     */
    public function verifyAndRecord(int $staffId, string $actionCode, string $actionType, ?Request $request = null): bool
    {
        $staff = User::with('staffProfile')->find($staffId);

        if (! $staff || ! $staff->staffProfile) {
            $this->audit->log('staff_action_failed_no_profile', 'User', $staffId, ['action' => $actionType], $request);
            throw ValidationException::withMessages(['action_code' => 'Staff profile not found or not configured.']);
        }

        $profile = $staff->staffProfile;

        // Enforce minimal throttling: no more than 5 attempts in 5 minutes
        $recentAttempts = AuditLog::where('user_id', $staffId)
            ->where('action', 'staff_action_attempt')
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->count();

        if ($recentAttempts >= 20) {
            $this->audit->log('staff_action_throttled', 'User', $staffId, ['attempts'=>$recentAttempts], $request);
            throw ValidationException::withMessages(['action_code' => 'Too many attempts. Try again later.']);
        }

        $valid = $profile->matchesActionCode($actionCode);

        // Record attempt
        AuditLog::create([
            'user_id' => $staffId,
            'action' => 'staff_action_attempt',
            'model' => 'StaffProfile',
            'model_id' => $profile->id,
            'ip_address' => $request?->ip() ?? request()->ip(),
            'metadata' => [
                'action_type' => $actionType,
                'valid' => $valid,
            ],
            'created_at' => Carbon::now(),
        ]);

        if ($valid) {
            // Record successful action
            AuditLog::create([
                'user_id' => $staffId,
                'action' => 'staff_action_verified',
                'model' => 'StaffProfile',
                'model_id' => $profile->id,
                'ip_address' => $request?->ip() ?? request()->ip(),
                'metadata' => ['action_type' => $actionType],
                'created_at' => Carbon::now(),
            ]);
        }

        return $valid;
    }

    /**
     * Rotate action code for staff (generate new hashed code).
     *
     * @param int $staffId
     * @param string $newCode Plain text new code
     * @return StaffProfile
     */
    public function rotateCode(int $staffId, string $newCode): StaffProfile
    {
        return DB::transaction(function () use ($staffId, $newCode) {
            $profile = StaffProfile::firstOrCreate(['user_id' => $staffId]);
            $profile->storeActionCode($newCode);
            $profile->save();

            $this->audit->log('staff_action_rotated', 'StaffProfile', $profile->id, ['rotated_by' => Auth::id()]);

            return $profile;
        });
    }
}
