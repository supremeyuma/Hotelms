<?php

// app/Http/Controllers/Staff/CleaningDashboardController.php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomCleaning;
use Illuminate\Http\Request;
use App\Models\StaffProfile;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use App\Models\CleaningLog;
use App\Services\CleaningInventoryService;


class CleaningDashboardController extends Controller
{
    public function index()
    {
        $rooms = Room::with(['latestCleaning'])
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Staff/Cleaning/Dashboard', [
            'rooms' => $rooms
        ]);
    }

    public function update(Request $request, CleaningInventoryService $inventoryService)
    {
        $data = $request->validate([
            'room_id'     => ['required', 'exists:rooms,id'],
            'action'      => ['required', 'in:cleaning,clean'],
            'action_code' => ['required', 'string'],
        ]);

        // 🔐 Verify staff via action code
        $staffProfile = StaffProfile::whereNotNull('action_code')
            ->get()
            ->first(fn ($p) => password_verify($data['action_code'], $p->action_code));

        if (! $staffProfile) {
            throw ValidationException::withMessages([
                'action_code' => 'Invalid action code.'
            ]);
        }

        $room = Room::findOrFail($data['room_id']);

        // 🧹 Create or fetch active cleaning record
        $cleaning = RoomCleaning::firstOrCreate(
            [
                'room_id' => $room->id,
                'cleaned_at' => null,
            ],
            [
                'staff_id' => $staffProfile->user_id,
                'status' => 'dirty',
            ]
        );

        if ($data['action'] === 'cleaning') {
            $cleaning->update([
                'status' => 'cleaning',
                'staff_id' => $staffProfile->user_id,
            ]);
        }

        if ($data['action'] === 'clean') {
            $cleaning->update([
                'status' => 'clean',
                'staff_id' => $staffProfile->user_id,
                'cleaned_at' => now(),
            ]);

            // 📦 AUTO-DEDUCT CLEANING INVENTORY
            $inventoryService->consumeForRoom(
                room: $room,
                staffId: $staffProfile->user_id
            );
        }

        // 🧾 Activity log
        CleaningLog::create([
            'room_id' => $room->id,
            'user_id' => $staffProfile->user_id,
            'action'  => $data['action'],
        ]);

        return back()->with('success', 'Cleaning status updated.');
    }
}
