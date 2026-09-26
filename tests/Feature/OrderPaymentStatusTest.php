<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::factory()->create();
    }

    private function makeOrder(float $total): Order
    {
        return Order::create([
            'code' => 'DH' . $total,
            'customer_id' => $this->customer->id,
            'subtotal' => $total,
            'total_amount' => $total,
            'status' => 'pending',
        ]);
    }

    public function test_unpaid_order_is_pending(): void
    {
        $order = $this->makeOrder(100000);

        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('Chờ thanh toán', $order->payment_status_label);
    }

    public function test_partially_paid_order_is_partial(): void
    {
        $order = $this->makeOrder(100000);
        Payment::create([
            'order_id' => $order->id,
            'amount' => 40000,
            'method' => 'cash',
            'status' => 'partial',
        ]);

        $this->assertSame('partial', $order->payment_status);
        $this->assertSame('Thanh toán một phần', $order->payment_status_label);
    }

    public function test_fully_paid_order_is_paid(): void
    {
        $order = $this->makeOrder(100000);
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('Đã thanh toán', $order->payment_status_label);
    }

    public function test_failed_payment_does_not_count_as_paid(): void
    {
        $order = $this->makeOrder(100000);
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'bank_transfer',
            'status' => 'failed',
        ]);

        $this->assertSame('pending', $order->payment_status);
    }

    public function test_refunded_payment_does_not_count_as_paid(): void
    {
        $order = $this->makeOrder(100000);
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'refunded',
        ]);

        $this->assertSame('pending', $order->payment_status);
    }

    public function test_index_shows_payment_status_column(): void
    {
        $order = $this->makeOrder(100000);
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->admin)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('Mã đơn hàng');
        $response->assertSee('Khách hàng');
        $response->assertSee('Trạng thái');
    }

    public function test_index_shows_phone_column(): void
    {
        $this->customer->update(['phone' => '0905123456']);
        $this->makeOrder(100000);

        $response = $this->actingAs($this->admin)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('Số điện thoại');
        $response->assertSee('0905123456');
    }
}
