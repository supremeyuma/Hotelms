<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsChargeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_charge_settings_on_settings_page(): void
    {
        $manager = $this->createUserWithRole('manager');

        Setting::set('tax_enabled', true);
        Setting::set('tax_rate', 0.075);
        Setting::set('service_charge_enabled', true);
        Setting::set('service_charge_rate', 0.1);

        $response = $this->actingAs($manager)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Index')
            ->where('settings.tax_enabled', true)
            ->where('settings.tax_rate', 7.5)
            ->where('settings.service_charge_enabled', true)
            ->where('settings.service_charge_rate', 10)
        );
    }

    public function test_superuser_can_update_charge_settings(): void
    {
        $superuser = $this->createUserWithRole('superuser');

        $response = $this->actingAs($superuser)->put(route('admin.settings.update'), [
            'site_name' => 'Harbor Crest Hotel',
            'contact_email' => 'ops@harbor.test',
            'contact_phone' => '+2348000000000',
            'hotel_phone' => '+2348000000000',
            'hotel_address' => '1 Marina Road, Lagos',
            'map_embed_url' => 'https://example.com/map',
            'site_whatsapp' => '+2348000000000',
            'payment_provider_flutterwave_enabled' => true,
            'payment_provider_paystack_enabled' => true,
            'payment_default_provider' => 'flutterwave',
            'booking_show_room_images' => true,
            'booking_show_room_type_images' => true,
            'tax_enabled' => true,
            'tax_rate' => 7.5,
            'service_charge_enabled' => true,
            'service_charge_rate' => 10,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame('1', (string) Setting::get('tax_enabled'));
        $this->assertEquals(0.075, (float) Setting::get('tax_rate'));
        $this->assertSame('1', (string) Setting::get('service_charge_enabled'));
        $this->assertEquals(0.1, (float) Setting::get('service_charge_rate'));
    }

    protected function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ], [
            'slug' => $roleName,
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
