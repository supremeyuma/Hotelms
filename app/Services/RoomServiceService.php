<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RoomCleaning;
use App\Models\ServiceRequest;
use App\Events\GuestRequestCreated;
use App\Models\GuestRequest;

class RoomServiceService
{
    public function createRequest(Booking $booking, string $type, array $data = []): ServiceRequest
    {
        $serviceRequest = ServiceRequest::create([
            'booking_id' => $booking->id,
            'room_id' => $data['room_id'] ?? null,
            'type' => $type,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        $guestRequest = GuestRequest::create([
            'booking_id' => $booking->id,
            'room_id' => $data['room_id'] ?? null,
            'type' => $type,
            'requestable_type' => ServiceRequest::class,
            'requestable_id' => $serviceRequest->id,
            'status' => 'pending',
        ]);

        // 🧹 CLEANING-SPECIFIC SIDE EFFECT
        if ($type === 'cleaning') {
            RoomCleaning::firstOrCreate(
            [
                'room_id' => $data['room_id'],
                'cleaned_at' => null,
            ],
            [
                'status' => 'cleaner_requested',
            ]
        );
        }

        broadcast(new GuestRequestCreated($guestRequest))->toOthers();

        return $serviceRequest;
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
