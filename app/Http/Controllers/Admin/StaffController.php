<?php
// ========================================================
// Admin\StaffController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\StaffNote;
use App\Models\User;
use App\Models\StaffProfile;
use App\Services\AuditLoggerService as AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\ActionCodeService;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    protected AuditLogger $auditLogger;
    protected array $manageableRoles = [
        'staff',
        'frontdesk',
        'laundry',
        'clean',
        'kitchen',
        'bar',
        'inventory',
        'accountant',
        'manager',
        'hr',
    ];

    public function __construct(AuditLogger $auditLogger)
    {
        $this->middleware(['auth','role:manager|hr|md']);
        $this->auditLogger = $auditLogger;
    }

    public function index()
    {
        $filters = request()->only(['search', 'role', 'department', 'status']);
        $staffQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->manageableRoles));

        $staff = (clone $staffQuery)
            ->with(['roles', 'staffProfile', 'department'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, $role) =>
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $role))
            )
            ->when($filters['department'] ?? null, fn ($query, $departmentId) =>
                $query->where('department_id', $departmentId)
            )
            ->when(($filters['status'] ?? null) === 'active', fn ($query) =>
                $query->whereNull('suspended_at')
            )
            ->when(($filters['status'] ?? null) === 'suspended', fn ($query) =>
                $query->whereNotNull('suspended_at')
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
            'roles' => $this->availableRoles(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'summary' => [
                [
                    'label' => 'Total staff',
                    'value' => (clone $staffQuery)->count(),
                    'helper' => 'All staff records under management',
                    'route' => route($this->indexRoute()),
                ],
                [
                    'label' => 'Active staff',
                    'value' => (clone $staffQuery)->whereNull('suspended_at')->count(),
                    'helper' => 'Currently active team members',
                    'route' => route($this->indexRoute(), ['status' => 'active']),
                ],
                [
                    'label' => 'Suspended staff',
                    'value' => (clone $staffQuery)->whereNotNull('suspended_at')->count(),
                    'helper' => 'Records currently suspended',
                    'route' => route($this->indexRoute(), ['status' => 'suspended']),
                ],
            ],
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Staff/Create', [
            'roles' => $this->availableRoles(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:191',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|string|exists:roles,name',
            'phone'    => 'nullable|string',
            'position' => 'nullable|string|max:191',
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'department_id' => $data['department_id'] ?? null,
        ]);

        $user->assignRole($data['role']);

        $actionCode = ActionCodeService::generate();

        $profile = StaffProfile::create([
            'user_id'          => $user->id,
            'phone'            => $data['phone'] ?? null,
            'position'         => $data['position'] ?? null,
            'meta'             => [
                'employment_status' => 'active',
                'created_by_hr_id' => auth()->id(),
            ],
        ]);
        $profile->storeActionCode($actionCode);
        $profile->save();

        $this->auditLogger->log(
            'staff_created',
            'User',
            $user->id,
            ['role' => $data['role']]
        );

        return redirect()
            ->route($this->indexRoute())
            ->with('success', 'Staff created successfully.')
            ->with('action_code', $actionCode);
    }

    public function edit(User $staff)
    {
        abort_unless($this->isManageableStaff($staff), 404);

        $staff->load(['staffProfile', 'roles', 'department',
            'notes' => function ($query) { $query->latest(); },
            'notes.admin']);


        return Inertia::render('Admin/Staff/Edit', [
            'staff' => $staff,
            'roles' => $this->availableRoles(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'noteSummary' => [
                [
                    'label' => 'Queries',
                    'value' => $staff->notes()->where('type', 'query')->count(),
                ],
                [
                    'label' => 'Commendations',
                    'value' => $staff->notes()->where('type', 'commendation')->count(),
                ],
                [
                    'label' => 'Performance',
                    'value' => $staff->notes()->where('type', 'performance')->count(),
                ],
                [
                    'label' => 'Disciplinary',
                    'value' => $staff->notes()->where('type', 'disciplinary')->count(),
                ],
            ],
            'threadSummary' => [
                [
                    'label' => 'Conversations',
                    'value' => $staff->threads()->count(),
                ],
                [
                    'label' => 'Queries',
                    'value' => $staff->threads()->where('type', 'query')->count(),
                ],
                [
                    'label' => 'Commendations',
                    'value' => $staff->threads()->where('type', 'commendation')->count(),
                ],
            ],
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($this->isManageableStaff($staff), 404);

        $data = $request->validate([
            'name'=>'required|string|max:191',
            'email'=>'required|email|unique:users,email,'.$staff->id,
            'password'=>'nullable|string|min:6',
            'role'=>['required', 'string', Rule::exists('roles', 'name')],
            'phone'=>'nullable|string',
            'position'=>'nullable|string|max:191',
            'department_id' => ['nullable', 'exists:departments,id'],
            'action_code'=>'nullable|string|min:4'
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $staff->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'department_id' => $data['department_id'] ?? null,
        ] + (isset($data['password']) ? ['password' => $data['password']] : []));

        $staff->syncRoles([$data['role']]);

        $profile = $staff->staffProfile ?? new StaffProfile([
            'user_id' => $staff->id,
        ]);

        $profile->fill([
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'meta' => array_merge($profile->meta ?? [], [
                'employment_status' => $staff->is_suspended ? 'suspended' : 'active',
                'updated_by_hr_id' => auth()->id(),
            ]),
        ]);

        if (! empty($data['action_code'])) {
            $profile->storeActionCode($data['action_code']);
        }

        if (! $profile->exists) {
            $profile->fill([
                'user_id' => $staff->id,
            ]);
        }
        $profile->save();

        $this->auditLogger->log('staff_updated', 'User', $staff->id, ['data' => $data]);

        return redirect()->route($this->indexRoute())->with('success','Staff updated');
    }

    public function addNote(Request $request, User $staff)
    {
        abort_unless($this->isManageableStaff($staff), 404);

        $data = $request->validate([
            'type' => 'required|in:query,commendation,performance,disciplinary',
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
        abort_unless($this->isManageableStaff($staff), 404);

        $staff->suspend();

        $staff->staffProfile?->update([
            'meta' => array_merge($staff->staffProfile->meta ?? [], [
                'employment_status' => 'suspended',
                'updated_by_hr_id' => auth()->id(),
            ]),
        ]);

         $this->auditLogger->log('staff_suspended', 'User', $staff->id);

        return back()->with('success', 'Staff suspended.');
    }

    public function reinstate(User $staff)
    {
        abort_unless($this->isManageableStaff($staff), 404);

        $staff->reinstate();

        $staff->staffProfile?->update([
            'meta' => array_merge($staff->staffProfile->meta ?? [], [
                'employment_status' => 'active',
                'updated_by_hr_id' => auth()->id(),
            ]),
        ]);

         $this->auditLogger->log('staff_reinstated', 'User', $staff->id);

        return back()->with('success', 'Staff reinstated.');
    }


    public function destroy(User $staff)
    {
        abort_unless($this->isManageableStaff($staff), 404);

        $this->auditLogger->log('staff_deleted', 'User', $staff->id);

        $staff->delete();

        return redirect()->route($this->indexRoute())->with('success','Staff removed.');
    }

    protected function availableRoles()
    {
        return Role::query()
            ->whereIn('name', $this->manageableRoles)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function isManageableStaff(User $staff): bool
    {
        return $staff->roles()->whereIn('name', $this->manageableRoles)->exists();
    }

    protected function routePrefix(): string
    {
        return request()->route()?->named('hr.*') ? 'hr.staff' : 'admin.staff';
    }

    protected function indexRoute(): string
    {
        return $this->routePrefix() . '.index';
    }
}
