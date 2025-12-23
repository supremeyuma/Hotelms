<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ServiceRequest;

class RoomServiceService
{
    public function createRequest(Booking $booking, string $type, array $data = []): ServiceRequest
    {
        return ServiceRequest::create([
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'type' => $type,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function markInProgress(ServiceRequest $request): void
    {
        $request->update(['status' => 'in_progress']);
    }

    public function markCompleted(ServiceRequest $request): void
    {
        $request->update(['status' => 'completed']);
    }
}
