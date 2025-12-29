<?php
// ========================================================
// Admin\BookingAdminController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingAdminController extends Controller
{
    protected BookingService $service;

    public function __construct(BookingService $service)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $bookings = Booking::with(['room.roomType','user'])->latest()->paginate(25);
        return Inertia::render('Admin/Bookings/Index', compact('bookings'));
    }

    public function edit(Booking $booking)
    {
        $booking->load(['room','user']);
        $rooms = Room::with('roomType')->get();
        return Inertia::render('Admin/Bookings/Edit', compact('booking','rooms'));
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'status' => 'required|string',
            'guests' => 'nullable|integer|min:1'
        ]);

        // Use service to safely reassign/modify booking
        $this->service->updateBooking($booking, $data);

        AuditLogger::log('booking_admin_updated', 'Booking', $booking->id, ['data'=>$data]);

        return redirect()->route('admin.bookings.index')->with('success','Booking updated.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        AuditLogger::log('booking_admin_deleted', 'Booking', $booking->id);

        return back()->with('success','Booking removed.');
    }

    /**
     * reassign rooms
     */
    public function reassignRoom(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
        ]);

        $booking->update(['room_id' => $data['room_id']]);

        AuditLogger::log('booking_reassigned', 'Booking', $booking->id, ['to_room'=>$data['room_id']]);

        return back()->with('success','Room reassigned.');
    }
}
