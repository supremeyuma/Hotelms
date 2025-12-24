<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\GuestRequest;
use App\Services\GuestRequestService;

class GuestRequestController extends Controller
{
    protected GuestRequestService $requestService;

    public function __construct(GuestRequestService $requestService)
    {
        $this->requestService = $requestService;
    }

    public function index()
    {
        $requests = GuestRequest::with('booking', 'room')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('FrontDesk/GuestRequests/Index', [
            'guestRequests' => $requests
        ]);
    }

    public function acknowledge(GuestRequest $request)
    {
        $this->requestService->acknowledgeRequest($request, auth()->user());

        return back()->with('success', 'Request acknowledged.');
    }

    public function complete(GuestRequest $request)
    {
        $this->requestService->completeRequest($request);

        return back()->with('success', 'Request marked as completed.');
    }
}
