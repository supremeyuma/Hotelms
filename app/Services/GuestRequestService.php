<?php

namespace App\Services;

use App\Models\GuestRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuestRequestService
{
    /**
     * Mark a guest request as acknowledged by a staff member
     */
    public function acknowledgeRequest(GuestRequest $request, User $staff): GuestRequest
    {
        return DB::transaction(function () use ($request, $staff) {
            $request->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => $staff->id,
                'status' => 'acknowledged',
            ]);

            return $request;
        });
    }

    /**
     * Mark a guest request as completed
     */
    public function completeRequest(GuestRequest $request): GuestRequest
    {
        return DB::transaction(function () use ($request) {
            $request->update([
                'completed_at' => now(),
                'status' => 'completed',
            ]);

            return $request;
        });
    }
}
