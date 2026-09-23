<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Nguyễn Văn A',
        ]);
    }

    public function test_can_list_deliveries(): void
    {
        Delivery::create([
            'customer_id' => $this->customer->id,
            'method' => 'pickup',
            'address' => '123 Đường ABC',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('deliveries.index'));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
    }

    public function test_can_create_delivery(): void
    {
        $response = $this->actingAs($this->admin)->post(route('deliveries.store'), [
            'customer_id' => $this->customer->id,
            'method' => 'dropoff',
            'address' => '456 Đường XYZ',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '14:00',
            'status' => 'scheduled',
        ]);

        $response->assertRedirect(route('deliveries.index'));
        $this->assertDatabaseHas('deliveries', [
            'customer_id' => $this->customer->id,
            'method' => 'dropoff',
        ]);
    }

    public function test_can_delete_delivery(): void
    {
        $delivery = Delivery::create([
            'customer_id' => $this->customer->id,
            'method' => 'pickup',
            'address' => '789 Đường DEF',
            'pickup_date' => now()->toDateString(),
            'pickup_time' => '09:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('deliveries.destroy', $delivery));

        $response->assertRedirect(route('deliveries.index'));
        $this->assertDatabaseMissing('deliveries', [
            'id' => $delivery->id,
        ]);
    }
}
