<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\MaintenanceTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MaintenanceService
{
    public function reportIssue(Booking $booking, string $type, string $description, ?UploadedFile $file = null): MaintenanceTicket
    {
        $room = $booking->room ?: $booking->rooms()->first();
        $photoPath = $file ? $file->store('maintenance', 'public') : null;

        $ticketData = [
            'room_id' => $room?->id,
            'title' => $this->makeTitle($type, $room?->name ?? $room?->room_number),
            'description' => $description,
            'status' => 'open',
            'meta' => [
                'booking_id' => $booking->id,
                'issue_type' => $type,
                'photo_path' => $photoPath,
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'reported_at' => now()->toIso8601String(),
            ],
        ];

        return MaintenanceTicket::create($ticketData);
    }

    public function closeTicket(MaintenanceTicket $ticket): void
    {
        $ticket->update(['status' => 'closed']);
    }

    protected function makeTitle(string $type, ?string $roomName = null): string
    {
        $label = str($type)->replace('_', ' ')->title()->toString();

        return $roomName
            ? "{$label} issue in {$roomName}"
            : "{$label} issue reported";
    }
}
