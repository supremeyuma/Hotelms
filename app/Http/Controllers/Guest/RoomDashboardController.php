<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
use App\Models\LaundryItem;
use Illuminate\Support\Facades\DB;
use App\Models\Receipt;
use App\Models\RoomCleaning;

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

    /**
     * Guest room dashboard
     */
    public function index(Request $request)
    {
        $room = $request->room;
        $booking = $request->booking;

        return Inertia::render('Guest/RoomDashboard', [
            'room' => $room,
            'booking' => $booking,
            'accessToken' => $room->roomAccessToken?->token,
            'outstandingBill' => $this->billingService
                ->outstandingForRoom($booking, $room->id),
            'laundryItems' => LaundryItem::all(),
            'cleaningStatus' => RoomCleaning::where('room_id', $room->id)
                ->whereNull('cleaned_at')
                ->latest()
                ->value('status'),
            'orders' => Order::where('room_id', $room->id)
                ->where('booking_id', $booking->id)
                ->get(),
            'showOrders' => request()->boolean('showOrders'),
        ]);
    }

    /**
     * ROOM-SPECIFIC outstanding balance
     */
    protected function outstandingForRoom($room): float
    {
        $charges = $room->charges()->sum('amount');
        $payments = $room->payments()->sum('amount');

        return max($charges - $payments, 0);
    }

    /**
     * Guest bill history (ROOM-SCOPED)
     */
    public function billHistory(Request $request)
    {
        $room = $request->room;
        $booking = $request->booking;

        return response()->json([
            'history' => $room->charges()
                ->select('id', 'description', 'amount', 'created_at')
                ->get()
                ->map(fn ($c) => [
                    'type' => 'charge',
                    'description' => $c->description,
                    'amount' => $c->amount,
                    'created_at' => $c->created_at,
                ])
                ->merge(
                    $room->payments()
                        ->select('id', 'amount', 'created_at')
                        ->get()
                        ->map(fn ($p) => [
                            'type' => 'payment',
                            'description' => 'Payment',
                            'amount' => $p->amount,
                            'created_at' => $p->created_at,
                        ])
                )
                ->sortBy('created_at')
                ->values(),
            'outstanding' => $this->billingService
                ->outstandingForRoom($booking, $room->id),
            'currency' => 'NGN',
        ]);
    }

     public function payBill(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'reference' => 'nullable|string|max:100',
        ]);

        $room = $request->room;
        $booking = $request->booking;

        $reference = $request->reference ?? 'MOCK-' . strtoupper(uniqid());

        if (\DB::table('payments')->where('reference', $reference)->exists()) {
            return response()->json([
                'success' => true,
                'outstanding' => $this->billingService
                    ->outstandingForRoom($booking, $room->id),
                'message' => 'Payment already processed.',
            ]);
        }

        $this->billingService->addPayment(
            booking: $booking,
            roomId: $room->id,
            amount: $request->amount,
            method: 'guest_portal',
            reference: $reference
        );

        return response()->json([
            'success' => true,
            'outstanding' => $this->billingService
                ->outstandingForRoom($booking, $room->id),
        ]);
    }


    /* ======================
       EXISTING METHODS BELOW
       ====================== */

    public function serviceRequest(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:cleaning,kitchen,bar,laundry',
            'notes' => 'nullable|string|max:500',
        ]);

        $serviceRequest = $this->roomService->createRequest(
            $request->booking,
            $request->type,
            ['notes' => $request->notes, 'room_id' => $request->room->id],
        );

        event(new ServiceRequested($serviceRequest));

        return back()->with('success', 'Service request submitted.');
    }

    public function reportMaintenance(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:plumbing,electrical,furniture,other',
            'description' => 'required|string|max:1000',
        ]);

        $ticket = $this->maintenanceService->reportIssue(
            $request->booking,
            $request->type,
            $request->description
        );

        event(new MaintenanceReported($ticket));

        return back()->with('success', 'Maintenance issue reported.');
    }

    public function extendStay(Request $request)
    {
        $request->validate([
            'new_checkout' => 'required|date|after:' . $request->booking->check_out,
        ]);

        $this->extensionService->extendStay(
            $request->booking,
            $request->new_checkout
        );

        return back()->with('success', 'Stay extended successfully.');
    }

    public function checkout(Request $request)
    {
        $this->checkoutService->checkout($request->booking);

        return back()->with('success', 'Checked out successfully.');
    }
}