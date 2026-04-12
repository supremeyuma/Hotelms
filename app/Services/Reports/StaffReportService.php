<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class StaffReportService
{
    protected array $workforceRoles = [
        'staff',
        'frontdesk',
        'laundry',
        'clean',
        'kitchen',
        'bar',
        'inventory',
        'accountant',
        'Accountant',
        'manager',
        'hr',
    ];

    public function query(array $filters)
    {
        $query = User::query()
            ->with(['roles', 'department', 'staffProfile'])
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->workforceRoles))
            ->withCount(['orders', 'bookings'])
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
            ->orderBy('name');

        return $this->applyMaintenanceTaskCount($query);
    }

    public function summary(): array
    {
        $staffQuery = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->workforceRoles));

        return [
            'total_staff' => (clone $staffQuery)->count(),
            'active_staff' => (clone $staffQuery)->whereNull('suspended_at')->count(),
            'suspended_staff' => (clone $staffQuery)->whereNotNull('suspended_at')->count(),
            'departments' => (clone $staffQuery)->whereNotNull('department_id')->distinct('department_id')->count('department_id'),
            'orders' => (clone $staffQuery)->withCount('orders')->get()->sum('orders_count'),
            'bookings' => (clone $staffQuery)->withCount('bookings')->get()->sum('bookings_count'),
            'maintenance_tasks' => $this->applyMaintenanceTaskCount(clone $staffQuery)->get()->sum('maintenance_tasks_count'),
        ];
    }

    public function roles()
    {
        return Role::query()
            ->whereIn('name', $this->workforceRoles)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function departments()
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function applyMaintenanceTaskCount(Builder $query): Builder
    {
        if (
            ! Schema::hasTable('maintenance_tickets')
            || ! Schema::hasColumn('maintenance_tickets', 'staff_id')
        ) {
            return $query->select('users.*')->selectRaw('0 as maintenance_tasks_count');
        }

        return $query->withCount('maintenanceTasks');
    }
}
