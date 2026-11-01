<?php

namespace Tests\Feature\Licensing;

use App\Models\Licence;
use App\Models\LicencePlan;
use App\Models\User;
use App\Services\Licensing\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The platform owner's licence desk.
 *
 * Two things matter here beyond the screens working: a hotel manager must never
 * reach it, and a deployment without the signing key must not even admit that it
 * exists.
 */
class LicenceAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.private_key' => base64_encode(
                file_get_contents(base_path('tests/Fixtures/licensing/private.pem'))
            ),
            'licensing.checkout_enabled' => false,
            'licensing.term_months' => 12,
        ]);
    }

    public function test_a_superuser_sees_the_licence_desk(): void
    {
        $this->actingAs($this->userWithRole('superuser'))
            ->get(route('admin.licences.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Licences/Index')
                ->has('licences.data')
                ->has('stats')
                ->where('checkoutEnabled', false));
    }

    public function test_a_manager_cannot_reach_the_licence_desk(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.licences.index'))
            ->assertForbidden();
    }

    public function test_the_licence_desk_screens_render(): void
    {
        $user = $this->userWithRole('superuser');
        $licence = $this->licence(['machine_id' => 'machine-abc']);

        $this->actingAs($user)->get(route('admin.licences.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Licences/Create')
                ->has('plans', 3));

        $this->actingAs($user)->get(route('admin.licences.plans'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Licences/Plans')
                ->has('plans', 3));

        $this->actingAs($user)->get(route('admin.licences.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Licences/Orders'));

        $this->actingAs($user)->get(route('admin.licences.show', $licence->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Licences/Show')
                ->where('licence.property_name', 'Lagos Harbour Hotel')
                ->where('licence.plan', 'Mid-size hotel')
                ->where('licence.machine_id', 'machine-abc')
                ->where('licence.amount', 300000));
    }

    public function test_the_licence_desk_does_not_exist_without_a_signing_key(): void
    {
        config(['licensing.private_key' => null]);

        $this->actingAs($this->userWithRole('superuser'))
            ->get(route('admin.licences.index'))
            ->assertNotFound();
    }

    public function test_a_licence_can_be_revoked_and_restored(): void
    {
        $user = $this->userWithRole('superuser');
        $licence = $this->licence();

        $this->actingAs($user)
            ->post(route('admin.licences.revoke', $licence->id))
            ->assertRedirect();

        $this->assertSame(Licence::STATUS_REVOKED, $licence->fresh()->status);

        $this->actingAs($user)
            ->post(route('admin.licences.restore', $licence->id))
            ->assertRedirect();

        // Never activated, so it goes back to issued rather than active.
        $this->assertSame(Licence::STATUS_ISSUED, $licence->fresh()->status);
    }

    public function test_clearing_the_machine_lets_the_same_key_move_pc(): void
    {
        $user = $this->userWithRole('superuser');
        $licence = $this->licence(['machine_id' => 'machine-abc']);

        $this->actingAs($user)
            ->post(route('admin.licences.clear-machine', $licence->id))
            ->assertRedirect();

        $this->assertNull($licence->fresh()->machine_id);
    }

    public function test_reissuing_revokes_the_old_key_and_shows_a_new_one(): void
    {
        $user = $this->userWithRole('superuser');
        $licence = $this->licence(['machine_id' => 'machine-abc']);

        $response = $this->actingAs($user)
            ->post(route('admin.licences.reissue', $licence->id));

        $replacement = Licence::query()->latest('id')->first();

        $this->assertNotSame($licence->id, $replacement->id);
        $response->assertRedirect(route('admin.licences.show', $replacement->id));
        $response->assertSessionHas('licence_key');

        $this->assertSame(Licence::STATUS_REVOKED, $licence->fresh()->status);
        $this->assertSame(Licence::STATUS_ISSUED, $replacement->status);
        $this->assertNull($replacement->machine_id);
        $this->assertStringContainsString("Replaces licence #{$licence->id}", $replacement->notes);
        $this->assertSame($licence->amount, $replacement->amount);
    }

    public function test_a_licence_can_be_issued_by_hand_at_the_tier_price(): void
    {
        $user = $this->userWithRole('superuser');

        $response = $this->actingAs($user)->post(route('admin.licences.store'), [
            'property_name' => 'Abuja Grand',
            'rooms' => 30,
            'currency' => 'USD',
            'term_months' => 12,
            'customer_name' => 'Ada Obi',
            'customer_email' => 'ada@example.com',
            'payment_reference' => 'BANK-7788',
        ]);

        $licence = Licence::sole();

        $response->assertRedirect(route('admin.licences.show', $licence->id));
        $response->assertSessionHas('licence_key');

        $this->assertSame('Abuja Grand', $licence->property_name);
        $this->assertSame(30, $licence->room_count);
        $this->assertSame('USD', $licence->currency);
        $this->assertSame(70.0, (float) $licence->monthly_amount);
        $this->assertSame(840.0, (float) $licence->amount);
        $this->assertSame('BANK-7788', $licence->payment_reference);
        $this->assertSame('Mid-size hotel', $licence->licencePlan->name);
    }

    public function test_the_price_bands_can_be_changed_without_a_deployment(): void
    {
        $user = $this->userWithRole('superuser');

        $plans = LicencePlan::query()->orderBy('sort_order')->get()->map(fn (LicencePlan $plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'min_rooms' => $plan->min_rooms,
            'max_rooms' => $plan->max_rooms,
            'monthly_naira' => $plan->min_rooms === 1 ? 20000 : (float) $plan->monthly_naira,
            'monthly_usd' => (float) $plan->monthly_usd,
            'sort_order' => $plan->sort_order,
            'is_active' => true,
        ])->all();

        $this->actingAs($user)
            ->put(route('admin.licences.plans.update'), ['plans' => $plans])
            ->assertRedirect();

        $small = LicencePlan::where('min_rooms', 1)->first();

        $this->assertSame(20000.0, (float) $small->monthly_naira);
    }

    public function test_an_impossible_band_is_clamped_instead_of_hidden(): void
    {
        $user = $this->userWithRole('superuser');
        $plan = LicencePlan::where('min_rooms', 1)->first();

        $this->actingAs($user)
            ->put(route('admin.licences.plans.update'), [
                'plans' => [[
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'min_rooms' => 20,
                    'max_rooms' => 5,
                    'monthly_naira' => (float) $plan->monthly_naira,
                    'monthly_usd' => (float) $plan->monthly_usd,
                    'sort_order' => $plan->sort_order,
                    'is_active' => true,
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(20, $plan->fresh()->max_rooms);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['slug' => $role],
        );

        $user = User::factory()->create();
        $user->assignRole($roleModel);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function licence(array $overrides = []): Licence
    {
        $issued = app(LicenceService::class)->issue(
            propertyName: 'Lagos Harbour Hotel',
            customerName: 'Ada Obi',
            customerEmail: 'ada@example.com',
            termMonths: 12,
            paymentReference: 'LIC-TEST-1',
            sale: [
                'plan' => LicencePlan::forRooms(30),
                'rooms' => 30,
                'currency' => 'NGN',
                'monthly_amount' => 25000.0,
                'amount' => 300000.0,
            ],
        );

        if ($overrides !== []) {
            $issued['licence']->forceFill($overrides)->save();
        }

        return $issued['licence']->fresh();
    }
}
