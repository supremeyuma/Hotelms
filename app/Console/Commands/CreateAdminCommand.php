<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Creates the administrator account for a LAN-server installation.
 *
 * The desktop setup wizard runs this after `migrate` and `db:seed`, then reads
 * the single JSON line printed on stdout to learn the new user and property ids.
 *
 * The command is idempotent: re-running it for an existing email updates the
 * name/password rather than failing, so an interrupted setup can resume.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'hotelms:create-admin
        {--name= : Full name of the administrator}
        {--email= : Login email address}
        {--password= : Initial password}
        {--property= : Property name to bind this node to (optional)}
        {--output= : Write the JSON result to this file instead of relying on stdout}';

    protected $description = 'Create the administrator account and optional property binding for a server installation';

    public function handle(): int
    {
        $name = trim((string) $this->option('name'));
        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');
        $propertyName = trim((string) ($this->option('property') ?? ''));

        $missing = array_values(array_filter([
            $name === '' ? '--name' : null,
            $email === '' ? '--email' : null,
            $password === '' ? '--password' : null,
        ]));

        if ($missing !== []) {
            $this->error('Missing required option(s): '.implode(', ', $missing));

            return self::FAILURE;
        }

        // roles.slug is NOT NULL on every supported driver; findOrCreate() would
        // insert without one, so the slug is supplied explicitly.
        $superuserRole = Role::firstOrCreate(
            ['name' => 'superuser', 'guard_name' => 'web'],
            ['slug' => 'superuser']
        );
        $mdRole = Role::firstOrCreate(
            ['name' => 'md', 'guard_name' => 'web'],
            ['slug' => 'md']
        );

        [$user, $propertyId] = DB::transaction(function () use ($name, $email, $password, $propertyName, $superuserRole, $mdRole) {
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = $password;
            $user->email_verified_at = now();
            $user->suspended_at = null;
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            $user->syncRoles([$superuserRole->name, $mdRole->name]);

            $propertyId = null;
            if ($propertyName !== '') {
                $propertyId = Property::firstOrCreate(['name' => $propertyName])->id;
            }

            return [$user, $propertyId];
        });

        // A single machine-readable line; the setup step parses this.
        $json = json_encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'property_id' => $propertyId,
            'roles' => $user->getRoleNames()->all(),
        ]);

        $outputPath = (string) ($this->option('output') ?? '');
        if ($outputPath !== '') {
            file_put_contents($outputPath, $json);
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
