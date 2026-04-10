<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\DiscountCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class BookingDiscountController extends Controller
{
    public function __construct(
        protected DiscountCodeService $discountCodeService
    ) {
    }

    public function applyToSession(Request $request)
    {
        $booking = Session::get('booking');

        if (! $booking) {
            return redirect()->route('booking.search');
        }

        $data = $request->validate([
            'discount_code' => ['required', 'string', 'max:50'],
        ]);

        $preview = $this->discountCodeService->previewForBookingSelection($booking, $data['discount_code']);

        $booking['discount_code'] = $preview['code'];
        $booking['discount_preview'] = $preview;

        Session::put('booking', $booking);

        return back()->with('success', 'Discount code applied to this reservation.');
    }

    public function removeFromSession()
    {
        $booking = Session::get('booking');

        if (! $booking) {
            return redirect()->route('booking.search');
        }

        unset($booking['discount_code'], $booking['discount_preview']);
        Session::put('booking', $booking);

        return back()->with('success', 'Discount code removed from this reservation.');
    }

    public function applyToBooking(Request $request, Booking $booking)
    {
        abort_if($booking->payment_status === 'paid', 403);
        abort_if($booking->status !== 'pending_payment', 403);

        $data = $request->validate([
            'discount_code' => ['required', 'string', 'max:50'],
        ]);

        $preview = $this->discountCodeService->previewForBooking($booking, $data['discount_code']);
        $this->discountCodeService->reserveForBooking($booking, $preview);

        return back()->with('success', 'Discount code applied before payment.');
    }

    public function removeFromBooking(Booking $booking)
    {
        abort_if($booking->payment_status === 'paid', 403);
        abort_if($booking->status !== 'pending_payment', 403);

        $this->discountCodeService->removeFromBooking($booking);

        return back()->with('success', 'Discount code removed.');
    }
}
