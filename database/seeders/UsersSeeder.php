<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the admin, manager, inventory, staff and guest accounts.
 *
 * Two role stores are written deliberately:
 *
 *  - `users.role_id` — the legacy FK. BookingsSeeder, OrdersSeeder and
 *    StaffProfilesSeeder still join on `roles.slug` through this column, so it
 *    must stay populated or those seeders silently produce nothing.
 *  - the Spatie `model_has_roles` pivot via `syncRoles()` — what the `role:`
 *    middleware and every policy actually read.
 *
 * A previous version wrote only the legacy column, and looked up slugs
 * (`md`, `manager`, `inventory_manager`, `staff`, `guest`) that RolesSeeder did
 * not define, so a fresh install produced accounts that could not open a single
 * dashboard. Both stores are now written from the same operational name.
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Checked by the login matrix in tests/Feature/Auth/SeededAccountLoginTest.
        // Every operational role the routes serve gets an account here, so a fresh
        // install can reach every dashboard without also running the optional
        // LocalHotelDemoSeeder (which builds its own separate demo dataset).
        $this->account('Chinonso Okafor', 'ceo@email.com', '1234', 'md');

        $this->account('Aisha Bello', 'manager@email.com', 'ManagerPass1!', 'manager');

        // Operational role is `inventory`; the old code looked up the
        // nonexistent `inventory_manager` slug and got null.
        $this->account('Daniel Mensah', 'inventory@email.com', 'Inventory123!', 'inventory');

        // Department leads. Emails and password match LocalHotelDemoSeeder so the
        // two seeders cannot confuse whoever runs either one.
        $this->account('Front Desk Lead', 'frontdesk@email.com', '11111111', 'frontdesk');
        $this->account('Housekeeping Lead', 'clean@email.com', '11111111', 'clean');
        $this->account('Laundry Lead', 'laundry@email.com', '11111111', 'laundry');
        $this->account('Kitchen Lead', 'kitchen@email.com', '11111111', 'kitchen');
        $this->account('Bar Lead', 'bar@email.com', '11111111', 'bar');
        $this->account('General Staff', 'staff@email.com', '11111111', 'staff');

        $staff = [
            ['name' => 'Grace Adebayo', 'email' => 'grace.ad@email.com'],
            ['name' => 'Emeka Nwosu', 'email' => 'emeka.nw@email.com'],
            ['name' => 'Bolaji Ojo', 'email' => 'bolaji.oj@email.com'],
            ['name' => 'Sadiq Ibrahim', 'email' => 'sadiq.ib@email.com'],
            ['name' => 'Ruth Eze', 'email' => 'ruth.ez@email.com'],
            ['name' => 'Tunde Oladipo', 'email' => 'tunde.ol@email.com'],
            ['name' => 'Mercy Okoye', 'email' => 'mercy.ok@email.com'],
            ['name' => 'John Doe', 'email' => 'john.doe@email.com'],
            ['name' => 'Femi Johnson', 'email' => 'femi.jo@email.com'],
            ['name' => 'Amina Yusuf', 'email' => 'amina.yu@email.com'],
        ];

        foreach ($staff as $person) {
            $this->account($person['name'], $person['email'], 'StaffPass1!', 'staff');
        }

        $guests = [
            ['name' => 'Adebimpe Oladunni', 'email' => 'bimpe.guest@example.com'],
            ['name' => 'Michael Smith', 'email' => 'michael.smith@example.com'],
            ['name' => 'Ngozi Chukwu', 'email' => 'ngozi.c@example.com'],
            ['name' => 'Samantha Brown', 'email' => 'samantha.b@example.com'],
            ['name' => 'Olufemi Adeyemi', 'email' => 'femi.a@example.com'],
            ['name' => 'Chen Wei', 'email' => 'chen.wei@example.com'],
            ['name' => 'Maria Rodriguez', 'email' => 'maria.rodriguez@example.com'],
            ['name' => 'Kwame Nkrumah', 'email' => 'kwame.n@example.com'],
            ['name' => 'Lilian Okon', 'email' => 'lilian.ok@example.com'],
            ['name' => 'Peter Obi', 'email' => 'peter.ob@example.com'],
        ];

        foreach ($guests as $person) {
            $this->account($person['name'], $person['email'], 'GuestPass1!', 'guest');
        }
    }

    /**
     * Create (or refresh) one account and give it its operational role in both
     * role stores.
     *
     * Uses the User model rather than a raw insert because `syncRoles()` is a
     * model method. `firstOrNew` keeps the seeder re-runnable.
     */
    private function account(string $name, string $email, string $password, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->uuid = $user->uuid ?: (string) Str::uuid();
        $user->name = $name;
        $user->password = $password;      // hashed by User::setPasswordAttribute
        $user->email_verified_at = now(); // seeded accounts must pass verified-only gates
        $user->role_id = DB::table('roles')->where('slug', $role)->value('id');
        $user->save();

        $user->syncRoles([$role]);

        return $user;
    }
}
