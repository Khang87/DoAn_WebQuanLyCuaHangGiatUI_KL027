<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\TaiKhoan;
use App\Models\ThanhToan;
use App\Models\ThongBao;
use App\Models\TinNhan;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class SchemaModuleCompatibilityTest extends TestCase
{
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
