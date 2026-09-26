<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang chi tiết (show) của mọi module phải dùng chung một bộ khung giao diện.
 * Test này chốt lại các class/structure bắt buộc để sau này không view nào
 * tự thoải mái dựng lại layout riêng.
 */
class DetailViewConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Khung chuẩn: thanh tiêu đề + 2 cột (8/4) + thẻ .detail-panel.
     */
    private function assertStandardDetailLayout(string $html, string $title): void
    {
        $this->assertStringContainsString('detail-header', $html, 'Thiếu thanh tiêu đề chuẩn');
        $this->assertStringContainsString('bi-arrow-left', $html, 'Thiếu nút Quay lại');
        $this->assertStringContainsString($title, $html, 'Thiếu tiêu đề trang');
        $this->assertMatchesRegularExpression(
            '/detail-header__title[^>]*>\s*' . preg_quote($title, '/') . '/',
            $html,
            'Tiêu đề trang không nằm trong khung tiêu đề chuẩn'
        );
        $this->assertStringContainsString('col-lg-8', $html, 'Thiếu cột chính 8/12');
        $this->assertStringContainsString('col-lg-4', $html, 'Thiếu cột phụ 4/12');
        $this->assertStringContainsString('detail-panel', $html, 'Thiếu thẻ nội dung chuẩn');
    }

    public function test_order_detail_uses_the_standard_layout(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        \App\Models\OrderItem::factory()->create(['order_id' => $order->id]);

        $html = $this->actingAs($this->admin)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Đơn hàng ' . $order->code);
        $this->assertStringContainsString('detail-field__label', $html);
        $this->assertStringContainsString('detail-table', $html);
        $this->assertStringContainsString('detail-money', $html);
    }

    public function test_customer_detail_uses_the_standard_layout(): void
    {
        $customer = Customer::factory()->create(['name' => 'Nguyễn Văn A']);

        $html = $this->actingAs($this->admin)
            ->get(route('customers.show', $customer->id))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Khách hàng ' . $customer->name);
    }

    public function test_booking_detail_uses_the_standard_layout(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $html = $this->actingAs($this->admin)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Lịch hẹn ' . $booking->code);
    }

    public function test_service_detail_uses_the_standard_layout(): void
    {
        $service = \App\Models\Service::factory()->create(['name' => 'Giặt Hấp']);

        $html = $this->actingAs($this->admin)
            ->get(route('services.show', $service))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Dịch vụ ' . $service->name);
    }

    public function test_invoice_detail_uses_the_standard_layout(): void
    {
        $invoice = \App\Models\Invoice::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Hóa đơn ' . $invoice->code);
    }

    public function test_delivery_detail_uses_the_standard_layout(): void
    {
        $delivery = \App\Models\Delivery::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Phiếu giao nhận ' . $delivery->code);
    }

    public function test_payment_detail_uses_the_standard_layout(): void
    {
        $payment = \App\Models\Payment::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('payments.show', $payment))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Thanh toán');
    }

    public function test_account_detail_uses_the_standard_layout(): void
    {
        $account = User::factory()->create(['name' => 'Nguyễn Quản Lý']);

        $html = $this->actingAs($this->admin)
            ->get(route('accounts.show', $account->id))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Tài khoản ' . $account->name);
    }

    public function test_coupon_detail_uses_the_standard_layout(): void
    {
        $coupon = \App\Models\Coupon::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('coupons.show', $coupon))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Mã giảm giá ' . $coupon->code);
    }

    public function test_promotion_detail_uses_the_standard_layout(): void
    {
        $promotion = \App\Models\Promotion::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('promotions.show', $promotion))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Khuyến mãi ' . $promotion->code);
    }

    public function test_pricing_detail_uses_the_standard_layout(): void
    {
        $pricing = \App\Models\Pricing::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('pricings.show', $pricing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('detail-header', $html);
        $this->assertStringContainsString('detail-panel', $html);
    }

    public function test_service_category_detail_uses_the_standard_layout(): void
    {
        $category = \App\Models\ServiceCategory::factory()->create(['name' => 'Giặt ở']);

        $html = $this->actingAs($this->admin)
            ->get(route('service-categories.show', $category))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Danh mục ' . $category->name);
    }

    public function test_garment_detail_uses_the_standard_layout(): void
    {
        $garment = \App\Models\Garment::factory()->create(['name' => 'Áo thun']);

        $html = $this->actingAs($this->admin)
            ->get(route('garments.show', $garment))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Loại đồ giặt ' . $garment->name);
    }

    public function test_order_item_detail_uses_the_standard_layout(): void
    {
        $item = \App\Models\OrderItem::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('order-items.show', $item))
            ->assertOk()
            ->getContent();

        $this->assertStandardDetailLayout($html, 'Chi tiết mặt hàng');
    }

    public function test_review_detail_uses_the_standard_layout(): void
    {
        $review = \App\Models\Review::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('reviews.show', $review))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('detail-header', $html);
        $this->assertStringContainsString('detail-panel', $html);
    }

    public function test_notification_detail_uses_the_standard_layout(): void
    {
        $notification = \App\Models\Notification::create([
            'user_id' => $this->admin->id,
            'type' => 'order',
            'message' => 'Đơn hàng #DH001 đã hoàn thành',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('notifications.show', $notification->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('detail-header', $html);
        $this->assertStringContainsString('detail-panel', $html);
        $this->assertStringContainsString($notification->message, $html);
    }

    public function test_garment_condition_detail_view_exists(): void
    {
        $condition = \App\Models\GarmentCondition::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('garment-conditions.show', $condition))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('detail-header', $html);
        $this->assertStringContainsString('detail-panel', $html);
    }

    /**
     * Mọi trang chi tiết phải viết tiền tệ đúng chuẩn "VNĐ" (không phải "VND")
     * và dùng lớp .detail-money.
     */
    public function test_no_detail_view_uses_the_old_vnd_currency_suffix(): void
    {
        $views = glob(resource_path('views/admin/*/show.blade.php'));

        $this->assertNotEmpty($views);

        foreach ($views as $view) {
            $contents = file_get_contents($view);

            $this->assertDoesNotMatchRegularExpression(
                '/(?<!N)VND(?!Đ)/',
                $contents,
                basename(dirname($view)) . '/show.blade.php vẫn còn ghi "VND" thay vì "VNĐ"'
            );
        }
    }

    /**
     * Trang chi tiết không được tự chép lại script xác nhận xóa.
     */
    public function test_no_detail_view_duplicates_the_delete_confirmation_script(): void
    {
        $views = glob(resource_path('views/admin/*/show.blade.php'));

        foreach ($views as $view) {
            $contents = file_get_contents($view);

            $this->assertStringNotContainsString(
                'Swal.fire',
                $contents,
                basename(dirname($view)) . '/show.blade.php đang chép script Swal riêng, hãy dùng <x-admin.detail.confirm-form>'
            );
        }
    }
}
