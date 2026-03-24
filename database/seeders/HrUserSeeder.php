<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class HrUserSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::firstOrCreate(['name' => 'Human Resources']);

        $role = Role::firstOrCreate(
            ['name' => 'hr', 'guard_name' => 'web'],
            ['slug' => 'hr', 'permissions' => json_encode(['manage_staff', 'manage_hr_records', 'view_staff_reports'])]
        );

        if (empty($role->slug)) {
            $role->slug = 'hr';
            $role->save();
        }

        $user = User::withTrashed()->firstOrNew(['email' => 'hr@email.com']);
        $user->uuid = $user->uuid ?: (string) Str::uuid();
        $user->name = 'HR Officer';
        $user->password = '11111111';
        $user->department_id = $department->id;
        $user->email_verified_at = $user->email_verified_at ?: now();
        if ($user->trashed()) {
            $user->restore();
        }
        $user->save();
        $user->syncRoles(['hr']);

        $profile = StaffProfile::firstOrNew(['user_id' => $user->id]);
        $profile->position = 'Human Resources';
        $profile->phone = $profile->phone ?: '+2348000000000';
        $profile->meta = array_merge($profile->meta ?? [], ['employment_status' => 'active']);
        $profile->storeActionCode('111111');
        $profile->save();
    }
}
