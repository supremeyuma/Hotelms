<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTicket;
use App\Http\Requests\Maintenance\StoreTicketRequest;
use App\Http\Requests\Maintenance\UpdateTicketRequest;
use App\Services\AuditLogger;

class MaintenanceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(MaintenanceTicket::class, 'ticket');
    }

    public function store(StoreTicketRequest $request)
    {
        $ticket = MaintenanceTicket::create($request->validated());

        AuditLogger::log('ticket_created', 'MaintenanceTicket', $ticket->id);

        return back()->with('success', 'Maintenance ticket created.');
    }

    public function update(UpdateTicketRequest $request, MaintenanceTicket $ticket)
    {
        $ticket->update($request->validated());

        AuditLogger::log('ticket_updated', 'MaintenanceTicket', $ticket->id);

        return back()->with('success', 'Maintenance updated.');
    }
}
