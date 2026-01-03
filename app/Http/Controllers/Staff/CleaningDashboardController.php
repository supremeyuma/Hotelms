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

    public function update(Request $request)
    {
        $data = $request->validate([
            'room_id'     => ['required', 'exists:rooms,id'],
            'action'      => ['required', 'in:cleaning,clean'],
            'action_code' => ['required', 'string'],
        ]);

        // 🔐 Verify staff via action code
        $staff = StaffProfile::whereNotNull('action_code')
            ->get()
            ->first(fn ($p) => password_verify($data['action_code'], $p->action_code));

        if (! $staff) {
            throw ValidationException::withMessages([
                'action_code' => 'Invalid action code.'
            ]);
        }

        // 🧹 Create or update cleaning record
        $cleaning = RoomCleaning::firstOrCreate(
            [
                'room_id' => $data['room_id'],
                'cleaned_at' => null,
            ],
            [
                'staff_id' => $staff->user_id,
                'status' => 'dirty',
            ]
        );

        if ($data['action'] === 'cleaning') {
            $cleaning->update([
                'status' => 'cleaning',
                'staff_id' => $staff->user_id,
            ]);
        }

        if ($data['action'] === 'clean') {
            $cleaning->update([
                'status' => 'clean',
                'staff_id' => $staff->user_id,
                'cleaned_at' => now(),
            ]);
        }

        CleaningLog::create([
            'room_id' => $cleaning->room_id,
            'user_id' => $staff->user_id,
            'action'  => $data['action'],
        ]);

        return back();
    }
}
