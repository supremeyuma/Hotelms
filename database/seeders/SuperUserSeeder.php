<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SuperUserSeeder extends Seeder
{
    public function run(): void
    {
        $hasRoleSlugColumn = Schema::hasColumn('roles', 'slug');

        $mdRole = Role::findOrCreate('md', 'web');

        if ($hasRoleSlugColumn && blank($mdRole->slug)) {
            $mdRole->slug = 'md';
            $mdRole->save();
        }

        $superUserRole = Role::findOrCreate('superuser', 'web');

        if ($hasRoleSlugColumn && blank($superUserRole->slug)) {
            $superUserRole->slug = 'superuser';
            $superUserRole->save();
        }

        $user = User::withTrashed()->firstOrNew([
            'email' => 'superuser@hotelms.com',
        ]);

        $user->uuid = $user->uuid ?: (string) Str::uuid();
        $user->name = 'HotelMS Superuser';
        $user->password = 'superuser@!234';
        $user->email_verified_at = now();
        $user->role_id = $mdRole->id;
        $user->suspended_at = null;
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        // Keep the dedicated superuser role for identity, and retain md so
        // existing md-only middleware and policies continue to grant access.
        $user->syncRoles(['superuser', 'md']);
    }
}
