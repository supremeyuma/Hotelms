<?php
// ========================================================
// PublicController.php
// Namespace: App\Http\Controllers
// ========================================================
namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Property;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicController extends Controller
{
    /**
     * Homepage with featured properties, rooms and settings.
     */
    public function homepage()
    {
        $properties = Property::with(['rooms.roomType', 'rooms.images'])->take(6)->get();
        $featuredRooms = Room::with('roomType','images')->where('status','available')->take(6)->get();
        $settings = Setting::all()->pluck('value','key')->toArray();

        return Inertia::render('Public/Home', [
            'properties' => $properties,
            'featuredRooms' => $featuredRooms,
            'settings' => $settings,
        ]);
    }

    /**
     * Show room types for a property or global list
     */
    public function showRoomTypes(Request $request)
    {
        $query = RoomType::with('property')->latest();

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $roomTypes = $query->paginate(12);

        return Inertia::render('Public/RoomTypes', [
            'roomTypes' => $roomTypes,
        ]);
    }

    /**
     * Show single room detail page
     */
    public function showRoom(Room $room)
    {
        $room->load(['roomType','images','property']);
        return Inertia::render('Public/RoomDetail', [
            'room' => $room,
        ]);
    }

    /**
     * Contact form submission
     */
    public function submitContactForm(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string',
            'phone' => 'nullable|string',
        ]);

        // Store as a setting entry or send email - minimal demo: audit log
        AuditLogger::log('contact_submitted', 'ContactForm', 0, [
            'payload' => $data,
        ]);

        return back()->with('success', 'Thanks! We received your message.');
    }

    /**
     * Generic static pages router - loads blade or inertia by view key
     */
    public function staticPage(string $pageKey)
    {
        // Example mapping - prefer Vue pages if exist
        $allowed = ['terms','privacy','about', 'gallery','contact'];
        if (! in_array($pageKey, $allowed)) {
            abort(404);
        }

        return Inertia::render("Public/".ucfirst($pageKey));
    }
}
