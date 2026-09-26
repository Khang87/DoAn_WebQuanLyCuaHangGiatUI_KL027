<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * orders.customer_id là NOT NULL (FK cascade) nên quan hệ customer chỉ mất
 * khi khách bị XOÁ MỀM. Lỗi "Attempt to read property id on null" phát sinh
 * từ đúng tình huống này.
 */
class OrderNullCustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function orderFor(Customer $customer): Order
    {
        return Order::create([
            'code' => 'DH-NULL-' . $customer->id,
            'customer_id' => $customer->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);
    }

    public function test_index_renders_when_customer_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create(['name' => 'Khách Đã Xoá Mềm']);
        $this->orderFor($customer);

        $customer->delete();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $response = $this->actingAs($this->admin)->get(route('orders.index'));

        $response->assertStatus(200);
    }

    public function test_index_still_shows_name_of_soft_deleted_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Nguyễn Văn A']);
        $order = $this->orderFor($customer);
        $customer->delete();

        $response = $this->actingAs($this->admin)->get(route('orders.index'));

        $response->assertStatus(200);
        // Lịch sử đơn phải giữ tên khách dù khách đã bị xoá mềm
        $response->assertSee('Nguyễn Văn A');
    }

    public function test_show_page_renders_when_customer_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create(['name' => 'Khách Xem Đơn']);
        $order = $this->orderFor($customer);
        $customer->delete();

        $response = $this->actingAs($this->admin)->get(route('orders.show', $order->id));

        $response->assertStatus(200);
    }

    public function test_order_index_avatar_block_never_dereferences_null_customer(): void
    {
        $customer = Customer::factory()->create();
        $this->orderFor($customer);
        $customer->delete();

        // Nếu khối @php trong view còn truy cập $order->customer->id,
        // trang sẽ trả 500 thay vì 200.
        $response = $this->actingAs($this->admin)->get(route('orders.index'));

        $response->assertOk();
    }
}
