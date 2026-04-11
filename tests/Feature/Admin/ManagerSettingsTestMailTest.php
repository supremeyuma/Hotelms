<?php

namespace Tests\Feature\Admin;

use App\Mail\ManagerSettingsTestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagerSettingsTestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_send_test_mail_from_settings_page(): void
    {
        Mail::fake();

        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ], [
            'slug' => 'manager',
        ]);

        $manager = User::factory()->create([
            'name' => 'Ada Manager',
            'email' => 'ada.manager@example.com',
        ]);
        $manager->assignRole($managerRole);

        $response = $this->actingAs($manager)->post(route('admin.settings.test-mail'), [
            'test_email' => 'ops@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Test email sent successfully.');

        Mail::assertSent(ManagerSettingsTestMail::class, function (ManagerSettingsTestMail $mail) {
            return $mail->hasTo('ops@example.com')
                && $mail->activeMailer === config('mail.default')
                && $mail->sentBy === 'Ada Manager';
        });
    }

    public function test_test_mail_requires_a_valid_email_address(): void
    {
        Mail::fake();

        $managerRole = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ], [
            'slug' => 'manager',
        ]);

        $manager = User::factory()->create();
        $manager->assignRole($managerRole);

        $response = $this->from(route('admin.settings.index'))
            ->actingAs($manager)
            ->post(route('admin.settings.test-mail'), [
                'test_email' => 'not-an-email',
            ]);

        $response->assertRedirect(route('admin.settings.index'));
        $response->assertSessionHasErrors('test_email');

        Mail::assertNothingSent();
    }
}
