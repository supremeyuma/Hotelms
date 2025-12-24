<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RoomBillingUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public Room $room;

    public function __construct(Room $room)
    {
        $this->room = $room;
    }

    public function broadcastOn()
    {
        return new Channel("room.{$this->room->id}.billing");
    }

    public function broadcastAs()
    {
        return 'billing.updated';
    }
}
