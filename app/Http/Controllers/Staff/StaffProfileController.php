<?php
// ========================================================
// Staff\StaffProfileController.php
// Namespace: App\Http\Controllers\Staff
// ========================================================
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\StaffActionRequest;
use App\Models\User;
use App\Models\StaffProfile;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;

class StaffProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:staff|manager|md']);
    }

    /**
     * Show current staff profile
     */
    public function showProfile(Request $request)
    {
        $user = $request->user()->load('staffProfile','roles');
        return Inertia::render('Staff/Profile', ['user' => $user]);
    }

    /**
     * Update profile (name, phone, email)
     */
    public function updateProfile(UserRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        // allow email/password updates via UserRequest rules
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);

        AuditLogger::log('staff_profile_updated', 'User', $user->id, ['changes' => $data]);

        return back()->with('success','Profile updated.');
    }

    /**
     * Update staff action code (must be validated)
     */
    public function updateActionCode(StaffActionRequest $request)
    {
        $user = $request->user();
        $profile = $user->staffProfile ?? StaffProfile::create(['user_id'=>$user->id]);

        // mutate in model to hash, but here we explicitly hash
        $profile->action_code_hash = bcrypt($request->action_code);
        $profile->save();

        AuditLogger::log('staff_action_code_changed', 'StaffProfile', $profile->id, ['staff' => $user->id]);

        return back()->with('success','Action code updated.');
    }
}
