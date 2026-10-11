<?php

namespace App\Http\Controllers\Booking;

use App\Exceptions\ArrivalInsideLocalWindow;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Booking\BookingAuthority;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Shareable, guest-facing notice for arrivals the front desk owns.
 *
 * The link carries the arrival date, so it can be sent in a reply or printed.
 * If the date is not actually inside the local window, the guest is sent back
 * to the online search with the date filled in.
 */
class LocalWindowNoticeController extends Controller
{
    public function __construct(protected BookingAuthority $authority)
    {
    }

    public function __invoke(Request $request)
    {
        $checkIn = $request->filled('check_in')
            ? Carbon::parse($request->input('check_in'))
            : now();

        try {
            $this->authority->assertOnlineBookable($checkIn);
        } catch (ArrivalInsideLocalWindow $e) {
            return response()->view('booking.local-window', [
                'hotelName' => Setting::get('hotel_name'),
                'checkIn' => $e->checkIn->toFormattedDateString(),
                'checkInDate' => $e->checkIn->toDateString(),
                'cutoffHours' => $e->cutoffHours,
                'windowDescription' => $this->authority->effectiveWindowDescription(),
                'hotelPhone' => Setting::get('hotel_phone'),
                'whatsapp' => Setting::get('site_whatsapp'),
            ]);
        }

        return redirect()->route('booking.search', ['check_in' => $checkIn->toDateString()]);
    }
}
