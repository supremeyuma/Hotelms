<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portability regression: every value App\Enums\OrderStatus can write must be
 * storable on whatever engine backs the app. Before
 * 2026_10_18_093000_drop_orders_status_check_on_pgsql, PostgreSQL rejected
 * processing/confirmed/cancelled with SQLSTATE 23514 (orders_status_check)
 * while MySQL accepted them — run this against a pgsql connection to see the
 * constraint bite.
 */
class OrderStatusPortabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_order_status_case_persists_and_reads_back(): void
    {
        foreach (OrderStatus::cases() as $case) {
            $order = Order::create([
                'order_code' => 'ORD-PORT-'.strtoupper($case->name),
                'status' => $case->value,
                'total' => 0,
            ]);

            $this->assertSame(
                $case->value,
                Order::query()->findOrFail($order->id)->status
            );
        }

        $this->assertEqualsCanonicalizing(
            array_map(fn (OrderStatus $case) => $case->value, OrderStatus::cases()),
            Order::query()->pluck('status')->all()
        );
    }
}
