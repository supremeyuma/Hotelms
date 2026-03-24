<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AccountantUserSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::firstOrCreate(['name' => 'Accounts']);

        $role = Role::firstOrCreate(
            ['name' => 'Accountant', 'guard_name' => 'web'],
            ['slug' => 'accountant', 'permissions' => json_encode([
                'view_financial_reports',
                'manage_accounting_periods',
                'view_audit_logs',
                'review_outstanding_balances',
            ])]
        );

        if (empty($role->slug)) {
            $role->slug = 'accountant';
            $role->save();
        }

        $user = User::withTrashed()->firstOrNew(['email' => 'account@email.com']);
        $user->uuid = $user->uuid ?: (string) Str::uuid();
        $user->name = 'Account Officer';
        $user->password = '11111111';
        $user->department_id = $department->id;
        $user->email_verified_at = $user->email_verified_at ?: now();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->save();
        $user->syncRoles([$role->name]);

        $profile = StaffProfile::firstOrNew(['user_id' => $user->id]);
        $profile->position = 'Accountant';
        $profile->phone = $profile->phone ?: '+2348000000001';
        $profile->meta = array_merge($profile->meta ?? [], ['employment_status' => 'active']);
        $profile->storeActionCode('111111');
        $profile->save();
    }
}
