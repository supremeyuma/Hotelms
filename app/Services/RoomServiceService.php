<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Events\GuestRequestCreated;

class RoomServiceService
{
    public function createRequest(Booking $booking, string $type, array $data = []): ServiceRequest
    {
        $request = ServiceRequest::create([
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'type' => $type,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        broadcast(new GuestRequestCreated($request))->toOthers();

        return $request;
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
