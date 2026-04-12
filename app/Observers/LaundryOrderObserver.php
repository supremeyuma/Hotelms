<?php

namespace App\Observers;

use App\Models\LaundryOrder;
use App\Reporting\Projectors\LaundryProjector;

class LaundryOrderObserver
{
    /**
     * Handle the LaundryOrder "created" event.
     */
    public function created(LaundryOrder $laundryOrder)
    {
        // Project new laundry order into reporting layer
        LaundryProjector::project($laundryOrder);
    }

    /**
     * Handle the LaundryOrder "updated" event.
     */
    public function updated(LaundryOrder $laundryOrder)
    {
        // Track status changes (created → in_progress → ready → delivered)
        if ($laundryOrder->isDirty('status')) {
            LaundryProjector::projectOnStatusChange($laundryOrder, $laundryOrder->getOriginal('status'));
        }

        // Project all updates
        LaundryProjector::project($laundryOrder);
    }

    /**
     * Handle the LaundryOrder "deleted" event.
     */
    public function deleted(LaundryOrder $laundryOrder)
    {
        // Note: Deletion tracking can be added here if needed
    }
}
