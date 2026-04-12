<?php

namespace App\Observers;

use App\Models\Order;
use App\Reporting\Projectors\OrderProjector;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order)
    {
        // Project new order into reporting layer
        OrderProjector::project($order);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order)
    {
        // Track status changes
        if ($order->isDirty('status')) {
            OrderProjector::projectOnStatusChange($order, $order->getOriginal('status'));
        }

        // Project all updates
        OrderProjector::project($order);
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order)
    {
        // Note: Deletion tracking can be added here if needed
    }
}
