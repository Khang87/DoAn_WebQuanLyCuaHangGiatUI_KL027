<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Nguyễn Văn A',
        ]);
        $this->order = \App\Models\Order::create([
            'code' => 'DH-DEL-001',
            'customer_id' => $this->customer->id,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);
    }

    public function test_can_list_deliveries(): void
    {
        Delivery::create([
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'method' => 'giao_do',
            'address' => '123 Đường ABC',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('deliveries.index'));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
    }

    public function test_can_search_deliveries(): void
    {
        Delivery::create([
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'method' => 'giao_do',
            'address' => '123 Đường ABC',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '10:00',
            'status' => 'pending',
            'notes' => 'Ghi chú test',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('deliveries.index', ['search' => 'Nguyễn Văn A']));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
    }

    public function test_search_returns_empty_state_when_no_results(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('deliveries.index', ['search' => 'nonexistent']));

        $response->assertStatus(200);
        $response->assertSee('Chưa có dữ liệu giao nhận');
    }

    public function test_can_delete_delivery(): void
    {
        $delivery = Delivery::create([
            'customer_id' => $this->customer->id,
            'order_id' => $this->order->id,
            'method' => 'giao_do',
            'address' => '789 Đường DEF',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '09:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('deliveries.destroy', $delivery));

        $response->assertRedirect(route('deliveries.index'));
        $this->assertSoftDeleted('deliveries', ['id' => $delivery->id]);
    }
}

