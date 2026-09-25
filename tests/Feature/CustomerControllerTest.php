<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_customers(): void
    {
        Customer::create([
            'code' => 'KH001',
            'name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'phone' => '0901234567',
        ]);

        $response = $this->actingAs($this->admin)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('Nguyễn Văn A');
    }

    public function test_can_create_customer(): void
    {
        $response = $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Trần Thị B',
            'email' => 'b@example.com',
            'phone' => '0987654321',
            'address' => 'Hà Nội',
            'type' => 'VIP',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'Trần Thị B',
            'email' => 'b@example.com',
        ]);
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Lê Văn C',
            'email' => 'c@example.com',
            'phone' => '0911223344',
        ]);

        $response = $this->actingAs($this->admin)->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertSee('Lê Văn C');
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Tên Cũ',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Tên Mới',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Tên Mới',
        ]);
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::create([
            'code' => 'KH001',
            'name' => 'Xóa Tôi',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }
}
