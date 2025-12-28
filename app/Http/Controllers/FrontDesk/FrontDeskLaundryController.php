<?php

// app/Http/Controllers/FrontDeskLaundryController.php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\GuestRequest;
use Inertia\Inertia;

class FrontDeskLaundryController extends Controller
{
    /**
     * Show recent laundry requests (newest → oldest)
     */
    public function index()
    {
        $requests = GuestRequest::with(['requestable.room', 'requestable.items.item', 'requestable.statusHistories'])
            ->where('type', 'laundry')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('FrontDesk/LaundryRequests', compact('requests'));
    }

    /**
     * Show individual request details
     */
    public function show(GuestRequest $guestRequest)
    {
        $this->authorize('view', $guestRequest);

        return Inertia::render('FrontDesk/LaundryRequestDetail', [
            'request' => $guestRequest->load(['requestable.room', 'requestable.items.item', 'requestable.images', 'requestable.statusHistories']),
        ]);
    }
}
