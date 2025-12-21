<?php
// app/Http/Controllers/Staff/FrontDeskController.php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;

class FrontDeskController extends Controller
{
    public function checkout(Booking $booking)
    {
        $booking->update(['status' => 'checked_out']);

        return back();
    }
}
