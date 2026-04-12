<?php

// app/Http/Controllers/FrontDeskLaundryController.php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\GuestRequest;
use App\Models\LaundryOrder;
use App\Enums\LaundryStatus;
use App\Services\LaundryOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FrontDeskLaundryController extends Controller
{
    /**
     * Show recent laundry requests (newest → oldest)
     */
    public function index()
    {
        $requests = GuestRequest::with(['requestable.room', 'requestable.items.item', 'requestable.statusHistories.changer'])
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
            'request' => $guestRequest->load(['requestable.room', 'requestable.items.item', 'requestable.images', 'requestable.statusHistories.changer']),
        ]);
    }

    /**
     * Update laundry order status from frontdesk
     */
    public function updateStatus(
        Request $request,
        LaundryOrder $order,
        LaundryOrderService $service
    ) {
        $request->validate([
            'status' => ['required', 'string'],
        ]);

        // 🔒 HARD BACKEND GUARD
        if (
            $order->charge &&
            $order->charge->payment_mode === 'prepaid' &&
            $order->charge->status === 'unpaid'
        ) {
            return back()->with(
                'error',
                'Laundry order cannot be processed until payment is completed.'
            );
        }

        $newStatus = LaundryStatus::from($request->status);

        $service->updateStatus(
            $order,
            $newStatus,
            auth()->id()
        );

        return back()->with('success', 'Laundry order status updated.');
    }

    /**
     * Print laundry order tag
     */
    public function print(LaundryOrder $order)
    {
        $order->load(['room', 'items.item']);

        return Inertia::render('Staff/Laundry/Print', [
            'order' => $order,
        ]);
    }
}
