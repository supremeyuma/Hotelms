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
        $ticketData = [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'type' => $type,
            'description' => $description,
            'status' => 'open',
        ];

        if ($file) {
            $ticketData['photo_path'] = $file->store('maintenance', 'public');
        }

        return MaintenanceTicket::create($ticketData);
    }

    public function closeTicket(MaintenanceTicket $ticket): void
    {
        $ticket->update(['status' => 'closed']);
    }
}
