<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RoomAccessToken;
use App\Services\BillingService;

class CheckoutService
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function checkout(Booking $booking): Booking
    {
        if (!$this->billingService->canCheckout($booking)) {
            throw new \Exception("Outstanding balance must be cleared before checkout.");
        }

        $booking->update(['status' => 'checked_out']);

        // Revoke room access token
        RoomAccessToken::where('booking_id', $booking->id)->delete();

        return $booking;
    }
}
