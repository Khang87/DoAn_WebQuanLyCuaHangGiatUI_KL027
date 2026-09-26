<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Garment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bảo vệ toàn hệ thống: xoá mềm bất kỳ bản ghi cha nào, các trang danh sách
 * và chi tiết vẫn phải render được (200) chứ không fatal vì quan hệ null.
 *
 * Quan hệ giữa các model đều nạp cả bản ghi đã xoá mềm nên lịch sử không bị
 * đứt liên kết.
 */
class SoftDeleteRelationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Dựng một đơn hàng đầy đủ quan hệ: khách -> dịch vụ -> loại đồ -> chi tiết,
     * rồi trả về mảng các route danh sách/chi tiết liên quan.
     */
    private function seedFullGraph(): array
    {
        $customer = Customer::factory()->create(['name' => 'Khách Gốc']);
        $service = Service::factory()->create(['name' => 'Dịch Vụ Gốc']);
        $garment = Garment::factory()->create(['name' => 'Loại Đồ Gốc']);
        $promotion = Promotion::factory()->create();
        $booking = Booking::factory()->create(['customer_id' => $customer->id]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'promotion_id' => $promotion->id,
            'booking_id' => $booking->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'garment_id' => $garment->id,
            'quantity' => 2,
            'price' => 50000,
        ]);

        $invoice = Invoice::factory()->create(['order_id' => $order->id]);
        Payment::factory()->create(['order_id' => $order->id, 'invoice_id' => $invoice->id]);
        Delivery::factory()->create(['order_id' => $order->id, 'customer_id' => $customer->id]);
        Review::factory()->create(['order_id' => $order->id, 'customer_id' => $customer->id]);
        Pricing::factory()->create(['service_id' => $service->id, 'garment_id' => $garment->id]);

        return compact('customer', 'service', 'garment', 'promotion', 'booking', 'order', 'invoice');
    }

    #[DataProvider('softDeletedParentProvider')]
    public function test_pages_still_render_after_parent_is_soft_deleted(string $parentKey): void
    {
        $graph = $this->seedFullGraph();

        // Xoá mềm bản ghi cha
        $graph[$parentKey]->delete();

        $routes = [
            route('orders.index'),
            route('orders.show', $graph['order']->id),
            route('order-items.index'),
            route('invoices.index'),
            route('invoices.show', $graph['invoice']->id),
            route('payments.index'),
            route('deliveries.index'),
            route('bookings.index'),
            route('reviews.index'),
            route('pricings.index'),
            route('customers.index'),
            route('services.index'),
            route('garments.index'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($this->admin)->get($url)->assertStatus(200);
        }
    }

    public static function softDeletedParentProvider(): array
    {
        return [
            'customer' => ['customer'],
            'service' => ['service'],
            'garment' => ['garment'],
            'promotion' => ['promotion'],
            'booking' => ['booking'],
            'invoice' => ['invoice'],
            'order' => ['order'],
        ];
    }

    public function test_soft_deleted_parent_name_is_still_displayed(): void
    {
        $graph = $this->seedFullGraph();

        $graph['customer']->update(['name' => 'Nguyễn Thị Bích']);
        $graph['customer']->delete();

        $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertStatus(200)
            ->assertSee('Nguyễn Thị Bích');
    }

    public function test_soft_deleted_service_name_still_shows_in_order_items(): void
    {
        $graph = $this->seedFullGraph();

        $graph['service']->update(['name' => 'Giặt Lụa Cao Cấp']);
        $graph['service']->delete();

        $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertStatus(200)
            ->assertSee('Giặt Lụa Cao Cấp');
    }
}
