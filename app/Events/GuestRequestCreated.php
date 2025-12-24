<?php

namespace App\Events;

use App\Models\GuestRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class GuestRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public GuestRequest $request;

    public function __construct(GuestRequest $request)
    {
        $this->request = $request->load('booking', 'room');
    }

    public function broadcastOn()
    {
        return new Channel('frontdesk');
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->request->id,
            'type' => $this->request->type,
            'status' => $this->request->status,
            'room' => $this->request->room,
            'booking' => $this->request->booking,
            'created_at' => $this->request->created_at->toDateTimeString(),
        ];
    }
}
