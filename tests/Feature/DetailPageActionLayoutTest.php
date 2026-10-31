<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\DonHang;
use App\Models\GiaoNhan;
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

    public function test_order_list_rows_stay_one_line_tall(): void
    {
        $source = $this->source('orders/index');

        // Cụm nút thao tác không được xuống dòng: khi cho phép wrap, bảng tự
        // động bóp cột "Thao tác" về chiều ngang một nút và hàng thành rất cao.
        $this->assertStringNotContainsString('flex-wrap', $source);
        $this->assertStringContainsString('<td class="text-nowrap">', $source);
        $this->assertStringContainsString('d-flex align-items-center flex-nowrap gap-2', $source);

        // Hai cột chữ dài phải giới hạn bề ngang để không kéo giãn hàng.
        $this->assertSame(2, substr_count($source, 'text-truncate'));
        $this->assertStringContainsString('max-width: 180px;', $source);
        $this->assertStringContainsString('max-width: 240px;', $source);
    }

    public function test_role_detail_is_read_only_and_edit_forms_say_update(): void
    {
        $show = $this->source('roles/show');
        $this->assertStringNotContainsString('<form', $show, 'Trang chi tiết nhóm quyền không được chứa form.');
        $this->assertStringNotContainsString('type="checkbox"', $show);
        $this->assertStringContainsString("route('roles.index')", $show);

        $this->assertStringContainsString('route(\'roles.show\', $role->getKey())', $this->source('roles/index'));

        foreach (['services/edit', 'accounts/edit', 'accounts/profile'] as $view) {
            $source = $this->source($view);
            $this->assertStringContainsString('>Cập nhật', $source, "{$view} chưa dùng nhãn nút chuẩn.");
            $this->assertStringNotContainsString('Lưu thay đổi', $source);
        }
    }

    public function test_admin_forms_do_not_offer_reset_buttons_or_cancel_next_to_update(): void
    {
        foreach (File::allFiles(resource_path('views/admin')) as $view) {
            $source = $view->getContents();
            $this->assertDoesNotMatchRegularExpression('/<(?:button|input)\b[^>]*\btype=["\']reset["\']/i', $source, $view->getRelativePathname());
            $this->assertStringNotContainsString('Làm mới', $source, $view->getRelativePathname());
            $this->assertDoesNotMatchRegularExpression('/<(?:a|button)\b[^>]*>Hủy<\/(?:a|button)>\s*<button\b[^>]*>(?:(?!<\/button>)[\s\S])*Cập nhật<\/button>/u', $source, $view->getRelativePathname());
        }
    }

    public function test_create_and_edit_forms_share_the_submit_button_labels(): void
    {
        $themMoi = '<i class="bi bi-plus-lg me-1"></i>Thêm mới';
        $capNhat = '<i class="bi bi-check-lg me-1"></i>Cập nhật';

        $createViews = collect(File::glob(resource_path('views/admin/*/create.blade.php')))
            ->map(fn (string $path): string => str_replace('\\', '/', $path));
        $editViews = collect(File::glob(resource_path('views/admin/*/edit.blade.php')))
            ->map(fn (string $path): string => str_replace('\\', '/', $path))
            ->push(str_replace('\\', '/', resource_path('views/admin/deliveries/booking-document-edit.blade.php')));

        $this->assertGreaterThan(10, $createViews->count());
        $this->assertGreaterThan(10, $editViews->count());

        foreach ($createViews as $view) {
            $source = (string) File::get($view);
            $label = dirname($view).'/create';
            if (str_ends_with($view, '/admin/deliveries/create.blade.php')) {
                $this->assertSame(
                    preg_match_all('/<button\b[^>]*type="submit"/i', $source),
                    substr_count($source, $capNhat),
                    "{$label} phải dùng nhãn \"Cập nhật\" kèm icon dấu tích."
                );

                continue;
            }

            $this->assertSame(
                preg_match_all('/<button\b[^>]*type="submit"/i', $source),
                substr_count($source, $themMoi),
                "{$label} còn nút nộp không dùng nhãn \"Thêm mới\" kèm icon dấu cộng."
            );
        }

        foreach ($editViews as $view) {
            $source = (string) File::get($view);
            if (str_contains($view, 'bookings/edit.blade.php')) {
                $this->assertStringContainsString('<i class="bi bi-check-lg me-1"></i>{{ $inspectionMode', $source);
                $this->assertStringContainsString("'Cập nhật' }}", $source);

                continue;
            }

            $label = basename($view);
            $this->assertSame(
                preg_match_all('/<button\b[^>]*type="submit"/i', $source),
                substr_count($source, $capNhat),
                "{$label} còn nút nộp không dùng nhãn \"Cập nhật\" kèm icon dấu tích."
            );
            $this->assertStringNotContainsString($themMoi, $source, "{$label} dùng nhãn tạo mới trong form chỉnh sửa.");
        }

        $rolesForm = $this->source('roles/form');
        $this->assertStringContainsString(
            "<i class=\"bi bi-{{ \$editing ? 'check-lg' : 'plus-lg' }} me-1\"></i>{{ \$editing ? 'Cập nhật' : 'Thêm mới' }}",
            $rolesForm
        );
        $this->assertStringNotContainsString('Lưu quyền nhóm', $rolesForm);
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
        $order->setRelation('giaoNhans', collect([new GiaoNhan(['LoaiGiaoNhan' => 'GIAO_DO'])]));
        $this->assertSame(
            ['kind' => 'delivery', 'label' => 'Bắt đầu giao hàng'],
            collect(OrderWorkflow::nextAction($order))->only(['kind', 'label'])->all(),
        );

        $bookingOrder = new DonHang;
        $bookingOrder->TrangThai = OrderStatus::Washed->value;
        $bookingOrder->setRelation('booking', new Booking);
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

    public function test_pages_opened_from_the_order_list_return_to_the_order_list(): void
    {
        $this->assertStringContainsString(
            'listReturnParams()',
            $this->source('orders/index'),
            'Cột Thao tác ở danh sách đơn hàng phải gắn ngữ cảnh quay lại vào liên kết sang màn hình khác.',
        );

        foreach (['payments/create', 'invoices/create'] as $view) {
            $this->assertStringContainsString(
                "\$preselectedOrder ? route('orders.index')",
                $this->source($view),
                "Màn $view mở từ đơn hàng phải quay lại danh sách đơn hàng.",
            );
        }

        foreach (['invoices/show', 'deliveries/show', 'deliveries/edit'] as $view) {
            $this->assertStringContainsString(
                'OrderWorkflow::listBackUrl(',
                $this->source($view),
                "Màn $view dùng chung cho nhiều nơi nên phải tôn trọng ngữ cảnh quay lại.",
            );
        }

        $invoicesIndex = route('invoices.index');
        $this->assertSame($invoicesIndex, OrderWorkflow::listBackUrl($invoicesIndex));

        request()->merge([OrderWorkflow::LIST_RETURN_PARAM => OrderWorkflow::LIST_RETURN_VALUE]);
        $this->assertSame(route('orders.index'), OrderWorkflow::listBackUrl($invoicesIndex));
    }
}
