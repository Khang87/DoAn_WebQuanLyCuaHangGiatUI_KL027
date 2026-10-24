<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Support\DeliveryWorkflow;
use App\Support\OrderWorkflow;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Quy tắc bố cục của Web Admin:
 * - Danh sách là nơi duy nhất chứa các nút hành động (cột "Thao tác").
 * - Màn chi tiết chỉ hiển thị thông tin; nếu một hành động cần nhập liệu thì mở
 *   từ danh sách kèm ?action=... và trang đó chỉ có đúng một nút nộp.
 *
 * Test bám vào source Blade để cấu trúc không bị ai khôi phục lại khối "Thao tác".
 */
class DetailPageActionLayoutTest extends TestCase
{
    private function source(string $view): string
    {
        return (string) File::get(resource_path('views/admin/'.$view.'.blade.php'));
    }

    public function test_no_detail_page_renders_an_action_block(): void
    {
        $root = str_replace('\\', '/', resource_path('views/admin/'));

        $detailViews = collect(File::allFiles(resource_path('views/admin')))
            ->filter(fn (\SplFileInfo $file): bool => $file->getFilename() === 'show.blade.php')
            ->map(function (\SplFileInfo $file) use ($root): string {
                $path = str_replace('\\', '/', $file->getPathname());

                return substr($path, strlen($root), -strlen('.blade.php'));
            });

        $this->assertGreaterThan(8, $detailViews->count());

        foreach ($detailViews as $view) {
            $source = $this->source($view);

            $this->assertStringNotContainsString('Thao tác</h5>', $source, "{$view} còn khôi phục tiêu đề khối Thao tác.");
            $this->assertStringNotContainsString('title="Thao tác"', $source, "{$view} còn panel Thao tác.");
        }
    }

    public function test_order_detail_keeps_only_the_receiving_form_opened_from_the_list(): void
    {
        $source = $this->source('orders/show');

        $this->assertSame(1, substr_count($source, '<form action='));
        $this->assertStringContainsString("request()->query('action') === \\App\\Support\\OrderWorkflow::INTENT_RECEIVING", $source);
        $this->assertStringNotContainsString('route(\'deliveries.advance-progress\'', $source);
        $this->assertStringNotContainsString("route('payments.create'", $source);
    }

    public function test_order_list_column_holds_the_actions_taken_out_of_the_detail(): void
    {
        $source = $this->source('orders/index');

        $this->assertStringContainsString('OrderWorkflow::nextAction($order)', $source);
        $this->assertStringContainsString('OrderWorkflow::receivingUrl($order)', $source);
        $this->assertStringContainsString('title="Kiểm tra và tiếp nhận"', $source);
        $this->assertStringContainsString("route('invoices.create'", $source);
        $this->assertStringContainsString("route('payments.create'", $source);
        $this->assertStringContainsString('orders.destroy', $source);
    }

    public function test_delivery_and_booking_details_are_read_only(): void
    {
        foreach (['deliveries/show', 'bookings/show'] as $view) {
            $source = $this->source($view);

            $this->assertStringNotContainsString('<form action=', $source, "{$view} còn form hành động.");
            $this->assertStringNotContainsString('x-admin.detail.confirm-form', $source, "{$view} còn nút xác nhận.");
        }

        $this->assertStringNotContainsString('bookings.inspection', $this->source('bookings/show'));
        $this->assertStringNotContainsString('deliveries.advance-progress', $this->source('deliveries/show'));
    }

    public function test_delivery_and_booking_lists_expose_the_moved_actions(): void
    {
        $deliverySource = $this->source('deliveries/index');
        $this->assertStringContainsString('DeliveryWorkflow::progressAction(', $deliverySource);
        $this->assertStringContainsString("route('deliveries.advance-progress'", $deliverySource);
        $this->assertStringContainsString("route('deliveries.cancel-return'", $deliverySource);

        $bookingSource = $this->source('bookings/index');
        $this->assertStringContainsString('title="Kiểm tra và tạo đơn"', $bookingSource);
        $this->assertStringContainsString('$inspectableBookingIds', $bookingSource);
    }

    public function test_review_detail_keeps_only_the_reply_form(): void
    {
        $detailSource = $this->source('reviews/show');

        $this->assertSame(1, substr_count($detailSource, '<form action='));
        $this->assertStringContainsString("route('reviews.respond'", $detailSource);
        $this->assertStringNotContainsString('reviews.toggle', $detailSource);

        $listSource = $this->source('reviews/index');
        $this->assertStringContainsString("route('reviews.toggle'", $listSource);
        $this->assertStringContainsString('title="Phản hồi"', $listSource);
    }

    public function test_order_workflow_next_action_follows_the_processing_ladder(): void
    {
        $order = new DonHang;
        $order->setRelation('booking', null);
        $order->setRelation('giaoNhans', collect());

        $expectations = [
            [OrderStatus::Pending, null],
            [OrderStatus::Received, 'Bắt đầu giặt'],
            [OrderStatus::Washing, 'Hoàn thành giặt'],
            [OrderStatus::Washed, 'Xác nhận khách đã nhận tại cửa hàng'],
            [OrderStatus::Delivered, null],
        ];

        foreach ($expectations as [$status, $label]) {
            $order->TrangThai = $status->value;
            $action = OrderWorkflow::nextAction($order);

            $this->assertSame($label, $action['label'] ?? null, "Bước sau {$status->label()} bị lệch nhãn hành động.");
        }

        $order->TrangThai = OrderStatus::Washed->value;
        $order->setRelation('giaoNhans', collect([new \App\Models\GiaoNhan(['LoaiGiaoNhan' => 'GIAO_DO'])]));
        $this->assertSame(
            ['kind' => 'delivery', 'label' => 'Bắt đầu giao hàng'],
            collect(OrderWorkflow::nextAction($order))->only(['kind', 'label'])->all(),
        );

        $bookingOrder = new DonHang;
        $bookingOrder->TrangThai = OrderStatus::Washed->value;
        $bookingOrder->setRelation('booking', new \App\Models\Booking);
        $bookingOrder->setRelation('giaoNhans', collect());
        $this->assertNull(OrderWorkflow::nextAction($bookingOrder));

        $this->assertTrue(OrderWorkflow::awaitsReceiving(tap(new DonHang, fn ($o) => $o->TrangThai = OrderStatus::Pending->value)));
    }

    public function test_delivery_workflow_progress_action_follows_the_leg_ladder(): void
    {
        $this->assertSame(
            'Bắt đầu nhận đồ',
            DeliveryWorkflow::progressAction('NHAN_DO', 'Tại nhà', DeliveryStatus::Pending)['label'],
        );
        $this->assertSame(
            'Xác nhận đã nhận đồ',
            DeliveryWorkflow::progressAction('NHAN_DO', 'Tại nhà', DeliveryStatus::Picking)['label'],
        );
        $this->assertSame(
            'Xác nhận đã bàn giao',
            DeliveryWorkflow::progressAction('GIAO_DO', 'Tại cửa hàng', DeliveryStatus::Pending)['label'],
        );
        $this->assertSame(
            'Bắt đầu giao đồ',
            DeliveryWorkflow::progressAction('GIAO_DO', 'Tại nhà', DeliveryStatus::Pending)['label'],
        );
        $this->assertSame(
            'Xác nhận đã giao đồ',
            DeliveryWorkflow::progressAction('GIAO_DO', 'Tại nhà', DeliveryStatus::Delivering)['label'],
        );
        $this->assertNull(DeliveryWorkflow::progressAction('GIAO_DO', 'Tại nhà', DeliveryStatus::Completed));
    }
}
