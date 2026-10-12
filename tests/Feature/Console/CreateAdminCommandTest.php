<?php

namespace Tests\Feature\Console;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The desktop setup wizard calls `hotelms:create-admin` after migrating and
 * seeding a server installation, and parses the JSON line it prints. These
 * tests pin that contract: a usable, verified, role-bearing admin, idempotent
 * re-runs, an optional property binding, and a hard failure when input is
 * missing.
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_admin_with_roles(): void
    {
        $output = $this->runCommand([
            '--name' => 'Ada Admin',
            '--email' => 'Ada@Example.com',
            '--password' => 'secret-password',
        ]);

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Ada Admin', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertTrue($user->hasRole('superuser'));
        $this->assertTrue($user->hasRole('md'));

        $this->assertSame($user->id, $output['user_id']);
        $this->assertSame('ada@example.com', $output['email']);
        $this->assertNull($output['property_id']);
    }

    public function test_it_is_idempotent_for_the_same_email(): void
    {
        $this->runCommand(['--name' => 'Ada', '--email' => 'ada@example.com', '--password' => 'first-pass']);
        $first = User::where('email', 'ada@example.com')->firstOrFail();

        $this->runCommand(['--name' => 'Ada Updated', '--email' => 'ada@example.com', '--password' => 'second-pass']);

        $this->assertSame(1, User::where('email', 'ada@example.com')->count());
        $updated = User::where('email', 'ada@example.com')->firstOrFail();

        $this->assertSame($first->id, $updated->id);
        $this->assertSame('Ada Updated', $updated->name);
        $this->assertTrue(Hash::check('second-pass', $updated->password));
    }

    public function test_it_binds_and_returns_a_property_when_requested(): void
    {
        $output = $this->runCommand([
            '--name' => 'Ada',
            '--email' => 'ada@example.com',
            '--password' => 'secret-password',
            '--property' => 'Grand Plaza',
        ]);

        $property = Property::where('name', 'Grand Plaza')->first();
        $this->assertNotNull($property);
        $this->assertSame($property->id, $output['property_id']);
    }

    public function test_it_fails_when_required_input_is_missing(): void
    {
        $exitCode = Artisan::call('hotelms:create-admin', [
            '--name' => 'Ada',
            '--email' => 'ada@example.com',
        ]);

        $this->assertNotSame(0, $exitCode);
        $this->assertNull(User::where('email', 'ada@example.com')->first());
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private function runCommand(array $options): array
    {
        $report = tempnam(sys_get_temp_dir(), 'hotelms-admin-');
        $options['--output'] = $report;

        $exitCode = Artisan::call('hotelms:create-admin', $options);
        $this->assertSame(0, $exitCode, Artisan::output());

        $decoded = json_decode(trim((string) file_get_contents($report)), true);
        @unlink($report);

        $this->assertIsArray($decoded, 'command should write a JSON object to --output');

        return $decoded;
    }
}
