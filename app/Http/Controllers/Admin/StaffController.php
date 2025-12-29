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
use App\Services\AuditLoggerService as AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use App\Services\ActionCodeService;

class StaffController extends Controller
{
    protected AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->auditLogger = $auditLogger;
    }

    public function index()
    {
        $staff = User::with('roles','staffProfile', 'threads')->paginate(20);
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
            'name'     => 'required|string|max:191',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|string|exists:roles,name',
            'phone'    => 'nullable|string',
        ]);

        // Create user
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        $user->assignRole($data['role']);

        // Generate secure action code
        $actionCode = ActionCodeService::generate();

        StaffProfile::create([
            'user_id'          => $user->id,
            'phone'            => $data['phone'] ?? null,
            'action_code' => ActionCodeService::encrypt($actionCode),
        ]);

        $this->auditLogger->log(
            'staff_created',
            'User',
            $user->id,
            ['role' => $data['role']]
        );

        // Flash plaintext code ONCE
        return redirect()
            ->route('admin.staff.index')
            ->with('success', 'Staff created successfully.')
            ->with('action_code', $actionCode);
    }

    public function edit(User $staff)
    {
        $roles = Role::all();
        $staff->load(['staffProfile','roles',
            'notes' => function ($query) { $query->latest()->limit(1); },
            'notes.admin']);


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

        if (empty($data['password'])) {
            $data['password'] = "11111111";//$data['password'];
        } else {
            unset($data['password']);
        }

        // Only update fillable user fields
        $staff->update([
            'name' => $data['name'],
            'email' => $data['email'],
            // password included only if set above
        ] + (isset($data['password']) ? ['password' => $data['password']] : []));

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

        $this->auditLogger->log('staff_updated', 'User', $staff->id, ['data' => $data]);

        return redirect()->route('admin.staff.index')->with('success','Staff updated');
    }

    public function addNote(Request $request, User $staff)
    {
        $data = $request->validate([
            'type' => 'required|in:query,commendation',
            'message' => 'required|string|max:1000',
        ]);

        $note = $staff->notes()->create([
            'admin_id' => auth()->id(),
            'type' => $data['type'],
            'message' => $data['message'],
        ]);

        $this->auditLogger->log(
            'staff_note_added',
            'StaffNote',
            $note->id,
            ['staff_id' => $staff->id]
        );

        return back()->with('success', 'Note added.');
    }


    public function suspend(User $staff)
    {
        $staff->suspend();

         $this->auditLogger->log('staff_suspended', 'User', $staff->id);

        return back()->with('success', 'Staff suspended.');
    }

    public function reinstate(User $staff)
    {
        $staff->reinstate();

         $this->auditLogger->log('staff_reinstated', 'User', $staff->id);

        return back()->with('success', 'Staff reinstated.');
    }


    public function destroy(User $staff)
    {
        $this->auditLogger->log('staff_deleted', 'User', $staff->id);

        // remove user record (soft delete if model uses SoftDeletes)
        $staff->delete();

        return redirect()->route('admin.staff.index')->with('success','Staff removed.');
    }
}
