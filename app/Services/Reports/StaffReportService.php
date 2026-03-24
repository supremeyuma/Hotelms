<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        'manager',
        'hr',
    ];

    public function query(array $filters)
    {
        return User::query()
            ->with(['roles', 'department'])
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->workforceRoles))
            ->withCount(['orders','bookings','maintenanceTasks'])
            ->when($filters['name'] ?? null, fn($q,$v)=>$q->where('name','like',"%$v%"))
            ->when($filters['role'] ?? null, fn ($query, $role) =>
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $role))
            );
    }

    public function summary()
    {
        return [
            'active_staff' => User::query()
                ->whereNull('suspended_at')
                ->whereHas('roles', fn ($query) => $query->whereIn('name', $this->workforceRoles))
                ->count()
        ];
    }
}
