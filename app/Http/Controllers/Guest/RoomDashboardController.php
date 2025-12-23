<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RoomServiceService;
use App\Services\MaintenanceService;
use App\Services\BillingService;
use App\Services\BookingExtensionService;
use App\Services\CheckoutService;
use App\Events\ServiceRequested;
use App\Events\MaintenanceReported;
use App\Events\BillingUpdated;

class RoomDashboardController extends Controller
{
    protected RoomServiceService $roomService;
    protected MaintenanceService $maintenanceService;
    protected BillingService $billingService;
    protected BookingExtensionService $extensionService;
    protected CheckoutService $checkoutService;

    public function __construct(
        RoomServiceService $roomService,
        MaintenanceService $maintenanceService,
        BillingService $billingService,
        BookingExtensionService $extensionService,
        CheckoutService $checkoutService
    ) {
        $this->roomService = $roomService;
        $this->maintenanceService = $maintenanceService;
        $this->billingService = $billingService;
        $this->extensionService = $extensionService;
        $this->checkoutService = $checkoutService;
    }

    public function index(Request $request)
    {
         $booking = $request->booking;

        // Assuming the pivot table is bookings_rooms
        $booking->load(['rooms' => function ($query) {
            $query->with('roomAccessToken');
        }]);

        // Get the token for the specific room
        $room = $request->room;
        $accessToken = $room->roomAccessToken?->token;

        return Inertia::render('Guest/RoomDashboard', [
            'room' => $request->room,
            'booking' => $request->booking,
            'accessToken' => $accessToken,
            'outstandingBill' => $this->billingService->calculateOutstanding($request->booking),
        ]);
    }

    public function serviceRequest(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:cleaning,kitchen,bar,laundry',
            'notes' => 'nullable|string|max:500',
        ]);

        $serviceRequest = $this->roomService->createRequest(
            $request->booking,
            $request->type,
            ['notes' => $request->notes]
        );

        event(new ServiceRequested($serviceRequest));

        return back()->with('success', 'Service request submitted.');
    }

    public function reportMaintenance(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:plumbing,electrical,furniture,other',
            'description' => 'required|string|max:1000',
            'file' => 'nullable|file|mimes:jpg,png,jpeg|max:5120',
        ]);

        $ticket = $this->maintenanceService->reportIssue(
            $request->booking,
            $request->type,
            $request->description,
            $request->file('file')
        );

        event(new MaintenanceReported($ticket));

        return back()->with('success', 'Maintenance issue reported.');
    }

    public function extendStay(Request $request)
    {
        $request->validate([
            'new_checkout' => 'required|date|after:' . $request->booking->check_out,
        ]);

        $booking = $this->extensionService->extendStay(
            $request->booking,
            $request->new_checkout
        );

        return back()->with('success', 'Stay extended successfully.');
    }

    public function checkout(Request $request)
    {
        try {
            $booking = $this->checkoutService->checkout($request->booking);
            return redirect()->route('guest.room.dashboard', ['token' => $request->booking->access_token])
                ->with('success', 'Checked out successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function payBill(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $this->billingService->payBill($request->booking, $request->amount);

        event(new BillingUpdated($request->booking));

        return back()->with('success', 'Payment successful.');
    }

    public function billHistory(Request $request)
    {
        $charges = $request->booking->charges()->get();
        $payments = $request->booking->payments()->get();

        $history = $charges->map(fn($c) => [
            'id' => $c->id,
            'type' => 'charge',
            'description' => $c->description,
            'amount' => $c->amount,
            'created_at' => $c->created_at,
        ])->merge(
            $payments->map(fn($p) => [
                'id' => $p->id,
                'type' => 'payment',
                'description' => 'Payment',
                'amount' => $p->amount,
                'created_at' => $p->created_at,
            ])
        )->sortBy('created_at')->values();

        return response()->json(['billHistory' => $history]);
    }
}
