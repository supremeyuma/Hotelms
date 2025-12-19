<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffReportService
{
    public function query(array $filters)
    {
        return User::query()->role('staff')
            ->withCount(['orders','bookings','maintenanceTasks'])
            ->when($filters['name'] ?? null, fn($q,$v)=>$q->where('name','like',"%$v%"));
    }

    public function summary()
    {
        return [
            'active_staff' => User::query()->role('staff')->count()
        ];
    }
}
