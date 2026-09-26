<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Garment;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Promotion;
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
        $response->assertSee('DH001');
    }

    public function test_can_create_order(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH100',
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'weight_kg' => '5kg',
            'quantity_items' => '5 cái áo',
            'status' => 'processing',
            'items' => [
                ['service_id' => $this->service->id, 'garment_id' => null, 'price' => 50000, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect(route('orders.show', Order::where('code', 'DH100')->first()));

        // Tổng tiền luôn do server tính: 5 x 50.000 = 250.000
        $this->assertDatabaseHas('orders', [
            'code' => 'DH100',
            'customer_id' => $this->customer->id,
            'subtotal' => 250000,
            'discount_by_promotion' => 0,
            'discount_by_points' => 0,
            'total_amount' => 250000,
            'status' => 'processing',
        ]);
    }

    public function test_order_total_is_computed_server_side_and_ignores_client_value(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH101',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            // Client cố gán tổng tiền tuỳ ý
            'total_amount' => 99999999,
            'items' => [
                ['service_id' => $this->service->id, 'price' => 50000, 'quantity' => 2],
            ],
        ]);

        $order = Order::where('code', 'DH101')->firstOrFail();

        $this->assertSame(100000.0, (float) $order->subtotal);
        $this->assertSame(100000.0, (float) $order->total_amount);
    }

    public function test_order_applies_promotion_and_points_discount(): void
    {
        $promotion = Promotion::create([
            'name' => 'Giảm 10%',
            'code' => 'GIAM10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $customer = Customer::create(['code' => 'KH002', 'name' => 'Khách Có Điểm', 'points' => 100]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH102',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'promotion_id' => $promotion->id,
            'points_used' => 50,
            'items' => [
                ['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 10],
            ],
        ]);

        $order = Order::where('code', 'DH102')->firstOrFail();

        // Tạm tính 1.000.000 - voucher 10% (100.000) - 50 điểm (50.000) = 850.000
        $this->assertSame(1000000.0, (float) $order->subtotal);
        $this->assertSame(100000.0, (float) $order->discount_by_promotion);
        $this->assertSame(50, (int) $order->points_used);
        $this->assertSame(50000.0, (float) $order->discount_by_points);
        $this->assertSame(850000.0, (float) $order->total_amount);

        // Điểm của khách phải bị trừ trong cùng transaction
        $this->assertSame(50, (int) $customer->fresh()->points);
    }

    public function test_points_used_cannot_exceed_customer_balance(): void
    {
        $customer = Customer::create(['code' => 'KH003', 'name' => 'Khách Ít Điểm', 'points' => 10]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH103',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'points_used' => 9999,
            'items' => [
                ['service_id' => $this->service->id, 'price' => 50000, 'quantity' => 2],
            ],
        ]);

        $order = Order::where('code', 'DH103')->firstOrFail();

        $this->assertSame(10, (int) $order->points_used);
        $this->assertSame(10000.0, (float) $order->discount_by_points);
        $this->assertSame(0, (int) $customer->fresh()->points);
    }

    public function test_total_amount_is_never_negative(): void
    {
        $promotion = Promotion::create([
            'name' => 'Giảm cố định rất lớn',
            'code' => 'GIAMLON',
            'discount_type' => 'fixed',
            'discount_value' => 99999999,
            'min_order_amount' => 0,
            'status' => 'active',
        ]);

        $customer = Customer::create(['code' => 'KH004', 'name' => 'Khách Voucher Lớn', 'points' => 500]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH104',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'promotion_id' => $promotion->id,
            'points_used' => 100,
            'items' => [
                ['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1],
            ],
        ]);

        $order = Order::where('code', 'DH104')->firstOrFail();

        $this->assertSame(0.0, (float) $order->total_amount);
        $this->assertSame(100000.0, (float) $order->discount_by_promotion);
        $this->assertSame(0, (int) $order->points_used);
    }

    public function test_updating_order_restores_previously_redeemed_points(): void
    {
        $customer = Customer::create(['code' => 'KH005', 'name' => 'Khách Hoàn Điểm', 'points' => 100]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH105',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'points_used' => 40,
            'items' => [
                ['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(60, (int) $customer->fresh()->points);

        $order = Order::where('code', 'DH105')->firstOrFail();

        // Bỏ toàn bộ voucher điểm khi sửa lại đơn
        $this->actingAs($this->admin)->put(route('orders.update', $order), [
            'code' => 'DH105',
            'customer_id' => $customer->id,
            'status' => 'pending',
            'points_used' => 0,
            'items' => [
                ['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1],
            ],
        ]);

        $order->refresh();

        $this->assertSame(0, (int) $order->points_used);
        $this->assertSame(0.0, (float) $order->discount_by_points);
        $this->assertSame(100000.0, (float) $order->total_amount);
        // Điểm đã dùng phải được hoàn lại đủ
        $this->assertSame(100, (int) $customer->fresh()->points);
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
            'code' => 'DH003',
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
        $this->assertSoftDeleted('orders', [
            'id' => $order->id,
        ]);
    }

    public function test_kg_priced_service_uses_weight_instead_of_quantity(): void
    {
        $garment = Garment::create(['name' => 'Áo thường', 'status' => 'active']);
        Pricing::create([
            'service_id' => $this->service->id,
            'garment_id' => $garment->id,
            'name' => 'Giá theo kg',
            'unit' => 'kg',
            'price' => 25000,
            'status' => 'active',
            'effective_date' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH200',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'garment_id' => $garment->id,
                    'quantity' => 3,
                    'weight' => 5,
                ],
            ],
        ]);

        $order = Order::where('code', 'DH200')->firstOrFail();

        // Đơn vị kg => 5 kg x 25.000 = 125.000 (không phải 3 x 25.000).
        $this->assertSame(125000.0, (float) $order->subtotal);
        $this->assertSame(125000.0, (float) $order->total_amount);
    }

    public function test_non_kg_service_uses_quantity(): void
    {
        $garment = Garment::create(['name' => 'Áo khoác', 'status' => 'active']);
        Pricing::create([
            'service_id' => $this->service->id,
            'garment_id' => $garment->id,
            'name' => 'Giá theo cái',
            'unit' => 'cái',
            'price' => 45000,
            'status' => 'active',
            'effective_date' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH201',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'garment_id' => $garment->id,
                    'quantity' => 2,
                    'weight' => 9,
                ],
            ],
        ]);

        $order = Order::where('code', 'DH201')->firstOrFail();

        // Đơn vị cái => 2 x 45.000 = 90.000 (bỏ qua khối lượng).
        $this->assertSame(90000.0, (float) $order->subtotal);
    }

    public function test_voucher_usage_is_counted_and_released(): void
    {
        $promoA = Promotion::create([
            'name' => 'Voucher A', 'code' => 'VA', 'discount_type' => 'fixed',
            'discount_value' => 10000, 'min_order_amount' => 0, 'status' => 'active',
            'starts_at' => now()->subDay(), 'usage_limit' => 10,
        ]);
        $promoB = Promotion::create([
            'name' => 'Voucher B', 'code' => 'VB', 'discount_type' => 'fixed',
            'discount_value' => 20000, 'min_order_amount' => 0, 'status' => 'active',
            'starts_at' => now()->subDay(), 'usage_limit' => 10,
        ]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH202',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'promotion_id' => $promoA->id,
            'items' => [['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1]],
        ]);

        $this->assertSame(1, (int) $promoA->fresh()->used_count);

        $order = Order::where('code', 'DH202')->firstOrFail();

        // Đổi sang voucher B: A hoàn lượt, B ghi nhận lượt.
        $this->actingAs($this->admin)->put(route('orders.update', $order), [
            'code' => 'DH202',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'promotion_id' => $promoB->id,
            'items' => [['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1]],
        ]);

        $this->assertSame(0, (int) $promoA->fresh()->used_count);
        $this->assertSame(1, (int) $promoB->fresh()->used_count);

        // Xóa đơn thì hoàn lại lượt của voucher đang gắn.
        $this->actingAs($this->admin)->delete(route('orders.destroy', $order->fresh()));
        $this->assertSame(0, (int) $promoB->fresh()->used_count);
    }

    public function test_order_can_apply_voucher_by_code(): void
    {
        $promotion = Promotion::create([
            'name' => 'Voucher theo mã', 'code' => 'WELCOME10', 'discount_type' => 'percentage',
            'discount_value' => 10, 'min_order_amount' => 0, 'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH203',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'promotion_code' => 'welcome10',
            'items' => [['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1]],
        ]);

        $order = Order::where('code', 'DH203')->firstOrFail();

        $this->assertSame($promotion->id, (int) $order->promotion_id);
        $this->assertSame(10000.0, (float) $order->discount_by_promotion);
        $this->assertSame(90000.0, (float) $order->total_amount);
        $this->assertSame(1, (int) $promotion->fresh()->used_count);
    }

    public function test_voucher_at_usage_limit_gives_no_discount(): void
    {
        $promotion = Promotion::create([
            'name' => 'Voucher đã hết lượt', 'code' => 'HETLUOT', 'discount_type' => 'fixed',
            'discount_value' => 50000, 'min_order_amount' => 0, 'status' => 'active',
            'starts_at' => now()->subDay(), 'usage_limit' => 1, 'used_count' => 1,
        ]);

        $this->actingAs($this->admin)->post(route('orders.store'), [
            'code' => 'DH204',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'promotion_id' => $promotion->id,
            'items' => [['service_id' => $this->service->id, 'price' => 100000, 'quantity' => 1]],
        ]);

        $order = Order::where('code', 'DH204')->firstOrFail();

        // Đã đủ lượt => không giảm, và không tăng thêm used_count.
        $this->assertSame(0.0, (float) $order->discount_by_promotion);
        $this->assertSame(100000.0, (float) $order->total_amount);
        $this->assertSame(1, (int) $promotion->fresh()->used_count);
    }

    public function test_create_and_edit_pages_render_with_pricing_data(): void
    {
        $garment = Garment::create(['name' => 'Áo khoác dạng kg', 'status' => 'active']);
        Pricing::create([
            'service_id' => $this->service->id,
            'garment_id' => $garment->id,
            'name' => 'Giá theo kg',
            'unit' => 'kg',
            'price' => 25000,
            'status' => 'active',
            'effective_date' => now(),
        ]);

        // Bảng giá phải truy vấn được (trước đây thiếu cột pricings.deleted_at).
        $this->actingAs($this->admin)->get(route('orders.create'))->assertStatus(200);

        $order = Order::create([
            'code' => 'DH205',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->get(route('orders.edit', $order))->assertStatus(200);
    }
}
