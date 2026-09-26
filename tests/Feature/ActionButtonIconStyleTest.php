<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Cụm nút thao tác (Xem / Sửa / Xóa) trong bảng phải có ngoại hình trung tính
 * và icon LUÔN ĐEN.
 *
 * Vì đây là quy tắc thuần CSS nên test bám vào nội dung stylesheet: nếu ai đó
 * xoá hoặc nới lỏng quy tắc, test này sẽ đỏ trước khi giao diện lệch.
 */
class ActionButtonIconStyleTest extends TestCase
{
    private function css(): string
    {
        $path = public_path('assets/css/laundry.css');

        $this->assertFileExists($path);

        return (string) File::get($path);
    }

    /**
     * Rule ép icon về đen phải tồn tại và áp cho cả hai tên lớp:
     * - .btn-order-action: tên lớp thực sự đang dùng trong view
     * - .btn-action:      tên lớp trong quy chuẩn, để view mới dùng được ngay
     */
    public function test_action_button_icons_are_forced_to_black(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action i[^{]*\{[^}]*color:\s*#111827\s*!important/s',
            $css,
            'Thiếu quy tắc ép icon nút thao tác (.btn-order-action i) về #111827'
        );

        $this->assertMatchesRegularExpression(
            '/\.btn-action i[^{]*\{[^}]*color:\s*#111827\s*!important/s',
            $css,
            'Thiếu quy tắc ép icon nút thao tác (.btn-action i) về #111827'
        );
    }

    /**
     * Icon font (Bootstrap Icons / FontAwesome) dùng `color`, còn SVG nội tuyến
     * dùng `fill`/`stroke` — cả ba thuộc tính đều phải bị ép.
     */
    public function test_icon_fill_and_stroke_are_also_forced(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action svg[^{]*\{[^}]*fill:\s*#111827\s*!important/s',
            $css,
            'Thiếu ép fill cho SVG trong nút thao tác'
        );

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action svg[^{]*\{[^}]*stroke:\s*#111827\s*!important/s',
            $css,
            'Thiếu ép stroke cho SVG trong nút thao tác'
        );
    }

    /**
     * Khung nút: viền xám nhạt #d1d5db trên nền trắng, bo góc 8px.
     */
    public function test_action_button_frame_is_neutral(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action[^{]*\{[^}]*border:\s*1px solid #d1d5db\s*!important/s',
            $css,
            'Thiếu viền xám #d1d5db cho nút thao tác'
        );

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action[^{]*\{[^}]*background-color:\s*#ffffff\s*!important/s',
            $css,
            'Thiếu nền trắng cho nút thao tác'
        );

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action\s*\{[^}]*border-radius:\s*8px/s',
            $css,
            'Thiếu bo góc 8px cho nút thao tác'
        );
    }

    /**
     * Hover dịu mắt, không đổi màu icon.
     */
    public function test_hover_only_changes_background_and_keeps_icon_black(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action:hover[^{]*\{[^}]*background-color:\s*#f3f4f6\s*!important/s',
            $css,
            'Thiếu nền hover #f3f4f6 cho nút thao tác'
        );

        $this->assertMatchesRegularExpression(
            '/\.btn-order-action:hover[^{]*\{[^}]*border-color:\s*#9ca3af\s*!important/s',
            $css,
            'Thiếu viền hover #9ca3af cho nút thao tác'
        );
    }

    /**
     * Nút thao tác không được còn quy tắc cũ làm icon đổi màu theo biến thể
     * (xanh/cam/đỏ). Trước đây có .view:hover { color: #1d4ed8 } ...
     */
    public function test_no_legacy_coloured_icon_hover_rules_remain(): void
    {
        $css = $this->css();

        $this->assertDoesNotMatchRegularExpression(
            '/\.btn-order-action\.(view|edit|delete):hover[^{]*\{[^}]*color:/s',
            $css,
            'Còn quy tắc hover cũ đổi màu icon của nút thao tác'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\.btn-order-action\s*\{[^}]*color:\s*#475569/s',
            $css,
            'Còn màu icon xám-xanh cũ #475569 trên nút thao tác'
        );
    }

    /**
     * Nút mang ý nghĩa bằng màu (Xóa đỏ, Sửa cảnh báo, xoá dòng mặt hàng) phải
     * được LOẠI TRỪ khỏi quy tắc phẳng màu, nếu không sẽ mất tín hiệu phá huỷ.
     */
    public function test_dangerous_buttons_are_excluded_from_the_flat_style(): void
    {
        $css = $this->css();

        $flatRule = '.table td .btn:not(.btn-outline-danger):not(.btn-outline-warning):not(.remove-item)';

        $this->assertStringContainsString(
            $flatRule,
            $css,
            'Quy tắc phẳng màu phải loại trừ .btn-outline-danger, .btn-outline-warning và .remove-item'
        );

        // Nút loại trừ thực sự phải tồn tại trong view, nếu không loại trừ này vô nghĩa.
        // Nút Xóa màu đỏ nằm ở các bảng danh sách (index).
        $indexViews = '';
        foreach (glob(resource_path('views/admin/*/index.blade.php')) as $view) {
            $indexViews .= (string) File::get($view);
        }

        $this->assertStringContainsString(
            'btn-outline-danger',
            $indexViews,
            'Không còn nút Xóa màu đỏ nào trong bảng danh sách'
        );

        // Nút xoá dòng mặt hàng nằm trong bảng mặt hàng của form đơn hàng.
        $orderForms = (string) File::get(resource_path('views/admin/orders/create.blade.php'))
            . (string) File::get(resource_path('views/admin/orders/edit.blade.php'));

        $this->assertStringContainsString(
            'remove-item',
            $orderForms,
            'Không còn nút xoá dòng .remove-item nào trong form đơn hàng'
        );
    }
}
