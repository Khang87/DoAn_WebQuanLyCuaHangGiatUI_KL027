<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\KhachHang;
use App\Models\TaiKhoan;
use App\Models\ThanhToan;
use App\Models\ThongBao;
use App\Models\TinNhan;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class SchemaModuleCompatibilityTest extends TestCase
{
    public function test_booking_list_hides_reference_code_and_keeps_columns_aligned(): void
    {
        $booking = new Booking;
        $booking->setRawAttributes([
            'BookingID' => 1,
            'MaBooking' => 'DL-REFERENCE-1',
            'TrangThai' => BookingStatus::Pending->value,
            'HinhThucNhanDo' => 'Tại nhà',
            'HinhThucTraDo' => 'Tại cửa hàng',
            'NgayHen' => '2026-10-12',
            'GioHen' => '09:00',
        ]);
        $booking->setRelation('khachHang', null);
        $booking->setRelation('nhanVien', null);
        $booking->setRelation('donHangs', collect());

        $html = view('admin.bookings.index', [
            'bookings' => new LengthAwarePaginator(collect([$booking]), 1, 10),
        ])->render();

        $this->assertStringNotContainsString('DL-REFERENCE-1', $html);
        $this->assertStringContainsString('name="search"', $html);
        $this->assertStringContainsString('Tìm theo mã lịch', $html);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*>\s*Mã lịch hẹn\s*<\/th>/u', $html);
        $this->assertStringNotContainsString('bookings.destroy', $html);
        $this->assertStringNotContainsString('deleteBookingForm_', $html);
        $this->assertStringNotContainsString('Bạn có chắc muốn xóa lịch hẹn này?', $html);
        $this->assertStringNotContainsString('name="_method" value="DELETE"', $html);
        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $tableBody);
        $this->assertNotEmpty($tableBody);
        $this->assertSame(7, substr_count($tableBody[1], '<td'));
    }

    public function test_booking_list_empty_state_spans_the_remaining_columns(): void
    {
        $html = view('admin.bookings.index', [
            'bookings' => new LengthAwarePaginator(collect(), 0, 10),
        ])->render();

        $this->assertStringContainsString('colspan="7"', $html);
    }

    public function test_mobile_customer_avatar_has_priority_over_account_avatar(): void
    {
        $customer = $this->customer('https://example.test/mobile.jpg', 'https://example.test/account.jpg');

        $this->assertSame('https://example.test/mobile.jpg', $customer->avatar_url);
        $this->assertFalse($customer->isFillable('AvatarUrl'));
    }

    public function test_customer_avatar_falls_back_to_account_then_local_image(): void
    {
        $customer = $this->customer(null, 'https://example.test/account.jpg');
        $this->assertSame('https://example.test/account.jpg', $customer->avatar_url);
        $customer->setRelation('taiKhoan', null);
        $this->assertSame(asset('assets/images/user_1.jpg'), $customer->avatar_url);
    }

    public function test_payment_view_displays_mobile_avatar_and_escapes_stored_url(): void
    {
        $customer = $this->customer('https://example.test/mobile.jpg?a=1&b="2"', 'https://example.test/account.jpg');
        $customer->setAttribute('HoTen', 'Mobile customer');
        $order = new DonHang(['MaDonHang' => 'DH-MOBILE']);
        $order->setRelation('khachHang', $customer);
        $payment = new ThanhToan;
        $payment->setRawAttributes(['ThanhToanID' => 1, 'DonHangID' => 1, 'SoTien' => 10000,
            'PhuongThuc' => 'Tiền mặt', 'TrangThai' => 'Thành công']);
        $payment->setRelation('donHang', $order);

        $html = view('admin.payments.index', [
            'payments' => new LengthAwarePaginator(collect([$payment]), 1, 10),
            'methods' => [], 'statuses' => [],
        ])->render();

        $this->assertStringContainsString('src="https://example.test/mobile.jpg?a=1&amp;b=&quot;2&quot;"', $html);
        $this->assertStringNotContainsString('src="https://example.test/account.jpg"', $html);
        $this->assertStringContainsString('onerror="this.onerror=null;', $html);
    }

    public function test_payment_list_renders_each_status_once(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $payment = new ThanhToan;
            $payment->setRawAttributes([
                'ThanhToanID' => 1,
                'MaGiaoDich' => 'TM-REFERENCE-1',
                'DonHangID' => 1,
                'SoTien' => 10000,
                'PhuongThuc' => 'Tiền mặt',
                'TrangThai' => $status->value,
            ]);
            $payment->setRelation('donHang', null);

            $html = view('admin.payments.index', [
                'payments' => new LengthAwarePaginator(collect([$payment]), 1, 10),
                'methods' => [],
                'statuses' => [],
            ])->render();

            preg_match_all(
                '/<span\b[^>]*class="[^"]*\bbadge\b[^"]*"[^>]*>\s*(?:<i\b[^>]*><\/i>\s*)?'
                    .preg_quote($status->label(), '/').'\s*<\/span>/s',
                $html,
                $badges,
            );

            $this->assertCount(1, $badges[0]);
            $this->assertStringNotContainsString('TM-REFERENCE-1', $html);
            $this->assertStringContainsString('Tìm theo mã giao dịch', $html);
            $this->assertDoesNotMatchRegularExpression('/<th[^>]*>\s*Mã giao dịch\s*<\/th>/u', $html);
            preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $tableBody);
            $this->assertNotEmpty($tableBody);
            $this->assertSame(8, substr_count($tableBody[1], '<td'));
        }
    }

    public function test_payment_list_empty_state_spans_the_remaining_columns(): void
    {
        $html = view('admin.payments.index', [
            'payments' => new LengthAwarePaginator(collect(), 0, 10),
            'methods' => [],
            'statuses' => [],
        ])->render();

        $this->assertStringContainsString('colspan="8"', $html);
    }

    public function test_payment_detail_renders_paid_status_once(): void
    {
        $payment = new ThanhToan;
        $payment->setRawAttributes([
            'ThanhToanID' => 1,
            'SoTien' => 10000,
            'PhuongThuc' => 'Tiền mặt',
            'TrangThai' => PaymentStatus::Paid->value,
        ]);
        $payment->setRelation('donHang', null);

        $html = view('admin.payments.show', ['payment' => $payment])->render();

        preg_match_all(
            '/<span\b[^>]*class="[^"]*\bbadge\b[^"]*"[^>]*>\s*(?:<i\b[^>]*><\/i>\s*)?'
                .preg_quote(PaymentStatus::Paid->label(), '/').'\s*<\/span>/s',
            $html,
            $badges,
        );

        $this->assertCount(1, $badges[0]);
    }

    public function test_invoice_list_renders_each_status_once(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            $invoice = new HoaDon;
            $invoice->setRawAttributes([
                'HoaDonID' => 1,
                'MaHoaDon' => 'HD0001',
                'TrangThai' => $status->value,
            ]);
            $invoice->setRelation('donHang', null);

            $html = view('admin.invoices.index', [
                'invoices' => new LengthAwarePaginator(collect([$invoice]), 1, 10),
                'statuses' => InvoiceStatus::options(),
            ])->render();

            preg_match_all(
                '/<span\b[^>]*class="[^"]*\bbadge\b[^"]*"[^>]*>\s*(?:<i\b[^>]*><\/i>\s*)?'
                    .preg_quote($status->label(), '/').'\s*<\/span>/s',
                $html,
                $badges,
            );

            $this->assertCount(1, $badges[0]);
        }
    }

    public function test_notification_trigger_links_are_typed_and_remain_server_managed(): void
    {
        $notification = new ThongBao;
        $notification->setRawAttributes(['TinNhanID' => '12', 'BookingID' => '34']);

        $this->assertSame(12, $notification->TinNhanID);
        $this->assertSame(34, $notification->BookingID);
        $this->assertInstanceOf(TinNhan::class, $notification->tinNhan()->getRelated());
        $this->assertSame('TinNhanID', $notification->tinNhan()->getForeignKeyName());
        $this->assertInstanceOf(Booking::class, $notification->booking()->getRelated());
        $this->assertSame('BookingID', $notification->booking()->getForeignKeyName());
        $this->assertFalse($notification->isFillable('TinNhanID'));
        $this->assertFalse($notification->isFillable('BookingID'));
        $notification->fill(['BookingID' => 99]);
        $this->assertSame(34, $notification->BookingID);
    }

    private function customer(?string $mobileAvatar, ?string $accountAvatar): KhachHang
    {
        $customer = new KhachHang;
        $customer->setRawAttributes(['KhachHangID' => 1, 'AvatarUrl' => $mobileAvatar]);
        $account = new TaiKhoan;
        $account->setRawAttributes(['TaiKhoanID' => 2, 'AvatarURL' => $accountAvatar]);
        $customer->setRelation('taiKhoan', $account);

        return $customer;
    }
}
