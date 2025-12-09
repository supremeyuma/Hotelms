<?php
// ========================================================
// Admin\StaffController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StaffProfile;
use Spatie\Permission\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','role:manager|md']);
    }

    public function index()
    {
        $staff = User::with('roles','staffProfile')->paginate(20);
        return Inertia::render('Admin/Staff/Index', compact('staff'));
    }

    public function create()
    {
        $roles = Role::all();
        return Inertia::render('Admin/Staff/Create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:191',
            'email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:6',
            'role'=>'required|string|exists:roles,name',
            'phone'=>'nullable|string',
            'action_code'=>'nullable|string|min:4'
        ]);

        $user = User::create([
            'name'=>$data['name'],
            'email'=>$data['email'],
            'password'=>Hash::make($data['password']),
        ]);

        // assign role via spatie
        $user->assignRole($data['role']);

        // staff profile
        $profile = StaffProfile::create([
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'action_code_hash' => isset($data['action_code']) ? bcrypt($data['action_code']) : null,
        ]);

        AuditLogger::log('staff_created', 'User', $user->id, ['role'=>$data['role']]);

        return redirect()->route('staff.index')->with('success','Staff created');
    }

    public function edit(User $staff)
    {
        $roles = Role::all();
        $staff->load('staffProfile','roles');
        return Inertia::render('Admin/Staff/Edit', compact('staff','roles'));
    }

    public function update(Request $request, User $staff)
    {
        $data = $request->validate([
            'name'=>'required|string|max:191',
            'email'=>'required|email|unique:users,email,'.$staff->id,
            'password'=>'nullable|string|min:6',
            'role'=>'nullable|string|exists:roles,name',
            'phone'=>'nullable|string',
            'action_code'=>'nullable|string|min:4'
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $staff->update($data);

        if (!empty($data['role'])) {
            $staff->syncRoles([$data['role']]);
        }

        if ($staff->staffProfile) {
            if (!empty($data['action_code'])) {
                $staff->staffProfile->update(['action_code_hash' => bcrypt($data['action_code'])]);
            }
            $staff->staffProfile->update(['phone' => $data['phone'] ?? $staff->staffProfile->phone]);
        } else {
            StaffProfile::create([
                'user_id' => $staff->id,
                'phone' => $data['phone'] ?? null,
                'action_code_hash' => !empty($data['action_code']) ? bcrypt($data['action_code']) : null,
            ]);
        }

        AuditLogger::log('staff_updated', 'User', $staff->id, ['data'=>$data]);

        return redirect()->route('staff.index')->with('success','Staff updated');
    }

    public function destroy(User $staff)
    {
        $staff->delete();

        AuditLogger::log('staff_deleted', 'User', $staff->id);

        return back()->with('success','Staff removed.');
    }
}
