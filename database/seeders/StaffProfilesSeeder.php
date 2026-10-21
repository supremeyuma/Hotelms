<?php

namespace Database\Seeders;

use App\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds staff_profiles for the seeded staff users (position, phone, action code).
 *
 * Uses the StaffProfile model rather than raw inserts because the action code is
 * stored by the model (`storeActionCode()` encrypts it through ActionCodeService
 * and detects whichever column the schema currently has). Raw inserts here went
 * stale twice: the column was renamed `action_code_hash` -> `action_code`, and
 * the inserts also targeted emails from a different seeder that never existed.
 */
class StaffProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $staffUsers = DB::table('users')->whereIn('role_id', function ($q) {
            $q->select('id')->from('roles')->where('slug', 'staff');
        })->get();

        foreach ($staffUsers as $index => $u) {
            $profile = StaffProfile::firstOrNew(['user_id' => $u->id]);

            $profile->position = 'Staff';
            $profile->phone = sprintf('+23480%07d', 1000000 + $index);
            $profile->meta = ['shift' => ($index % 3 === 0) ? 'morning' : 'evening'];
            $profile->storeActionCode('1234'); // default action code for demo
            $profile->save();
        }
    }
}
