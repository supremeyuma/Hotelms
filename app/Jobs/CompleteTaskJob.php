<?php

namespace App\Jobs;

use App\Models\MaintenanceTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompleteTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MaintenanceTicket $ticket;

    public function __construct(MaintenanceTicket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function handle(): void
    {
        if ($this->ticket->status !== 'in_progress') return;

        $this->ticket->update(['status' => 'completed']);
    }
}
