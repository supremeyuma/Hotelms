<?php

namespace Tests\Feature\ClubPos;

use App\Models\DrinkStock;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'club.pos.access', 'slug' => 'club.pos.access', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);

        $this->outsider = User::factory()->create();
    }

    private function barItem(array $attributes = []): MenuItem
    {
        return MenuItem::factory()->create(array_merge([
            'service_area' => 'bar',
            'is_available' => true,
        ], $attributes));
    }

    public function test_the_stock_page_requires_permission(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('club.pos.stock.index'))
            ->assertForbidden();
    }

    public function test_the_stock_page_renders_for_authorized_staff(): void
    {
        $this->actingAs($this->staff)
            ->get(route('club.pos.stock.index'))
            ->assertOk();
    }

    public function test_the_stock_data_endpoint_returns_only_bar_items(): void
    {
        $this->barItem(['name' => 'Gin']);
        MenuItem::factory()->create(['service_area' => 'kitchen', 'is_available' => true]);

        $response = $this->actingAs($this->staff)
            ->getJson(route('club.pos.stock.data'));

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $response->assertJsonStructure([
            '*' => ['menu_item' => ['id', 'name'], 'stock', 'low_stock'],
        ]);
    }

    public function test_adding_stock_increments_full_bottles(): void
    {
        $item = $this->barItem();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.stock.add', $item), ['bottles' => 5])
            ->assertOk();

        $this->assertDatabaseHas('drink_stock', [
            'menu_item_id' => $item->id,
            'full_bottles' => 5,
        ]);

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.stock.add', $item), ['bottles' => 3])
            ->assertOk();

        $this->assertSame(8, DrinkStock::where('menu_item_id', $item->id)->first()->full_bottles);
    }

    public function test_stocktake_sets_the_counted_bottles(): void
    {
        $item = $this->barItem();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.stock.add', $item), ['bottles' => 10])
            ->assertOk();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.stock.stocktake', $item), ['actual_bottles' => 7])
            ->assertOk();

        $this->assertSame(7, DrinkStock::where('menu_item_id', $item->id)->first()->full_bottles);
    }

    public function test_stock_actions_require_permission(): void
    {
        $item = $this->barItem();

        $this->actingAs($this->outsider)
            ->postJson(route('club.pos.stock.add', $item), ['bottles' => 5])
            ->assertForbidden();
    }

    public function test_the_sales_report_requires_permission(): void
    {
        $this->actingAs($this->outsider)
            ->getJson(route('club.pos.reports.sales.data'))
            ->assertForbidden();
    }

    public function test_the_sales_report_defaults_to_today_and_returns_its_structure(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('club.pos.reports.sales.data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'summary' => ['total_sales', 'total_dockets', 'average_per_docket', 'date_from', 'date_to'],
            'category_breakdown',
            'payment_method_breakdown',
            'top_items',
            'hourly_breakdown',
            'dockets',
        ]);
    }

    public function test_the_sales_page_renders_for_authorized_staff(): void
    {
        $this->actingAs($this->staff)
            ->get(route('club.pos.reports.sales'))
            ->assertOk();
    }

    public function test_the_trends_report_returns_its_structure(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('club.pos.reports.trends.data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'daily_trend',
            'top_items',
            'staff_performance',
            'payment_method_trend',
            'period_days',
        ]);
    }

    public function test_the_trends_page_renders_for_authorized_staff(): void
    {
        $this->actingAs($this->staff)
            ->get(route('club.pos.reports.trends'))
            ->assertOk();
    }
}
