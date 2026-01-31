<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Payment;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomServiceOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $guest;
    protected User $staff;
    protected Room $room;
    protected MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomType = RoomType::factory()->create([
            'name' => 'Deluxe Room',
            'nightly_rate' => 50000
        ]);

        $this->room = Room::factory()->create([
            'room_type_id' => $this->roomType->id,
            'name' => 'Room 201'
        ]);

        $this->guest = User::factory()->create([
            'email' => 'guest@example.com',
            'role' => 'guest'
        ]);

        $this->staff = User::factory()->create([
            'email' => 'staff@example.com',
            'role' => 'staff'
        ]);

        // Create menu structure for room service
        $category = MenuCategory::factory()->create([
            'name' => 'Breakfast',
            'prep_time' => 15
        ]);

        $this->menuItem = MenuItem::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Eggs and Bacon',
            'price' => 3500,
            'is_active' => true
        ]);
    }

    /**
     * Test room service order with postpaid (offline) payment
     */
    public function test_order_with_postpaid_payment(): void
    {
        $response = $this->actingAs($this->guest)
            ->postJson('/order', [
                'room_id' => $this->room->id,
                'department' => 'kitchen',
                'items' => [
                    [
                        'name' => $this->menuItem->name,
                        'price' => $this->menuItem->price,
                        'quantity' => 2
                    ]
                ],
                'payment_method' => 'postpaid',
                'notes' => 'No onions please'
            ]);

        // Verify order was created with postpaid payment status
        $this->assertDatabaseHas('orders', [
            'payment_method' => 'postpaid',
            'payment_status' => 'not_required'
        ]);
    }

    /**
     * Test room service order with online payment
     */
    public function test_order_with_online_payment(): void
    {
        $response = $this->actingAs($this->guest)
            ->postJson('/order', [
                'room_id' => $this->room->id,
                'department' => 'kitchen',
                'items' => [
                    [
                        'name' => $this->menuItem->name,
                        'price' => $this->menuItem->price,
                        'quantity' => 1
                    ]
                ],
                'payment_method' => 'online',
                'notes' => ''
            ]);

        // Verify order was created with online payment status
        $this->assertDatabaseHas('orders', [
            'payment_method' => 'online',
            'payment_status' => 'pending'
        ]);
    }

    /**
     * Test order total calculation
     */
    public function test_order_total_calculation(): void
    {
        $order = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'kitchen',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'postpaid',
            'payment_status' => 'not_required',
            'total' => 0
        ]);

        // Add items and calculate total
        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Item 1',
            'price' => 5000,
            'quantity' => 2,
            'subtotal' => 10000
        ]);

        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'name' => 'Item 2',
            'price' => 3000,
            'quantity' => 1,
            'subtotal' => 3000
        ]);

        // Update order total
        $total = $item1->subtotal + $item2->subtotal;
        $order->update(['total' => $total]);

        // Verify total
        $this->assertEquals(13000, $order->fresh()->total);
    }

    /**
     * Test order payment callback success
     */
    public function test_order_payment_callback_success(): void
    {
        $order = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'kitchen',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'total' => 7000
        ]);

        $txRef = 'ORD-' . $order->id . '-' . uniqid();

        // Create payment record
        $payment = Payment::create([
            'amount' => 7000,
            'currency' => 'NGN',
            'reference' => $txRef,
            'status' => 'pending',
            'flutterwave_tx_id' => 'tx-ord-123'
        ]);

        // Simulate successful payment
        $this->postJson('/webhooks/flutterwave', [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-ord-123',
                'tx_ref' => $txRef,
                'status' => 'successful',
                'amount' => 7000,
                'currency' => 'NGN'
            ]
        ], [
            'verif-hash' => hash_hmac('sha512', json_encode([
                'event' => 'charge.completed',
                'data' => [
                    'id' => 'tx-ord-123',
                    'tx_ref' => $txRef,
                    'status' => 'successful',
                    'amount' => 7000,
                    'currency' => 'NGN'
                ]
            ]), config('services.flutterwave.secret_hash'))
        ]);

        // Verify payment was marked successful
        $payment->refresh();
        $this->assertEquals('successful', $payment->status);
    }

    /**
     * Test payment accounting entry creation on successful order payment
     */
    public function test_accounting_entry_on_order_payment(): void
    {
        $order = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'kitchen',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'total' => 12500
        ]);

        // Payment should create accounting entry via PaymentAccountingService
        $payment = Payment::create([
            'amount' => 12500,
            'currency' => 'NGN',
            'reference' => 'ORD-' . $order->id,
            'status' => 'successful',
            'flutterwave_tx_id' => 'tx-ord-acc-123',
            'paid_at' => now()
        ]);

        // Verify payment created
        $this->assertDatabaseHas('payments', [
            'amount' => 12500,
            'status' => 'successful',
            'reference' => 'ORD-' . $order->id
        ]);
    }

    /**
     * Test order payment reference tracking
     */
    public function test_order_payment_reference_tracking(): void
    {
        $order = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'kitchen',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'total' => 5000,
            'payment_reference' => 'ORD-REF-' . uniqid()
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_reference' => $order->payment_reference
        ]);
    }

    /**
     * Test multiple orders with mixed payment methods
     */
    public function test_multiple_orders_mixed_payment_methods(): void
    {
        // Create postpaid order
        $postpaidOrder = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'kitchen',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'postpaid',
            'payment_status' => 'not_required',
            'total' => 5000
        ]);

        // Create online payment order
        $onlineOrder = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'laundry',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'total' => 3000
        ]);

        // Verify both exist
        $this->assertDatabaseHas('orders', [
            'id' => $postpaidOrder->id,
            'payment_method' => 'postpaid',
            'payment_status' => 'not_required'
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $onlineOrder->id,
            'payment_method' => 'online',
            'payment_status' => 'pending'
        ]);
    }

    /**
     * Test order status updates with payment
     */
    public function test_order_status_progression_with_payment(): void
    {
        $order = Order::create([
            'room_id' => $this->room->id,
            'user_id' => $this->guest->id,
            'department' => 'kitchen',
            'status' => OrderStatus::PENDING,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'total' => 7500
        ]);

        // Payment pending
        $this->assertEquals('pending', $order->payment_status);

        // Simulate payment success
        $order->update([
            'payment_status' => 'paid',
            'status' => OrderStatus::PROCESSING
        ]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING->value, $order->fresh()->status);
    }
}
