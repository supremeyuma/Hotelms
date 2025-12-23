<?php

// app/Events/ServiceRequested.php
namespace App\Events;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceRequested
{
    use Dispatchable, SerializesModels;

    public ServiceRequest $serviceRequest;

    public function __construct(ServiceRequest $serviceRequest)
    {
        $this->serviceRequest = $serviceRequest;
    }
}

// app/Events/MaintenanceReported.php
namespace App\Events;
use App\Models\MaintenanceTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceReported
{
    use Dispatchable, SerializesModels;

    public MaintenanceTicket $ticket;

    public function __construct(MaintenanceTicket $ticket)
    {
        $this->ticket = $ticket;
    }
}

// app/Events/BillingUpdated.php
namespace App\Events;
use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillingUpdated
{
    use Dispatchable, SerializesModels;

    public Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }
}
