<?php
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


