<?php

// app/Events/LaundryOrderUpdated.php

namespace App\Events;

use App\Models\LaundryOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LaundryOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LaundryOrder $order;

    public function __construct(LaundryOrder $order)
    {
        $this->order = $order->load(['room', 'items.item', 'images', 'statusHistories']);
    }

    public function broadcastOn()
    {
        return new Channel('laundry-orders'); // public channel for staff/frontdesk
    }

    public function broadcastWith()
    {
        return [
            'order' => $this->order,
        ];
    }
}
