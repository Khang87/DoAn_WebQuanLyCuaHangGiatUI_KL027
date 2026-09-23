<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Khách Hàng A',
        ]);
        $this->service = Service::create([
            'name' => 'Giặt Sấy',
            'price' => 50000,
            'status' => 'active',
        ]);
    }

    public function test_can_list_orders(): void
    {
        Order::create([
            'code' => 'DH001',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'quantity_items' => '3 áo',
            'total_amount' => 150000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('#DH001');
    }

    public function test_can_create_order(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), [
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'weight_kg' => '5kg',
            'quantity_items' => '5 cái áo',
            'total_amount' => 250000,
            'status' => 'processing',
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'total_amount' => 250000,
            'status' => 'processing',
        ]);
    }

    public function test_can_show_order(): void
    {
        $order = Order::create([
            'code' => 'DH002',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'quantity_items' => '2 cái quần',
            'total_amount' => 100000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('orders.show', $order));

        $response->assertStatus(200);
        $response->assertSee('DH002');
    }

    public function test_can_update_order(): void
    {
        $order = Order::create([
            'code' => 'DH003',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'quantity_items' => '1 bộ',
            'total_amount' => 100000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->put(route('orders.update', $order), [
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'quantity_items' => '1 bộ',
            'total_amount' => 100000,
            'status' => 'completed',
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_can_delete_order(): void
    {
        $order = Order::create([
            'code' => 'DH004',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'quantity_items' => '1 cái',
            'total_amount' => 50000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('orders.destroy', $order));

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);
    }
}
