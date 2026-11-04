<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DanhGia;
use App\Models\DanhMucLoaiDoGiat;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\KhachHang;
use App\Models\NhatKyHeThong;
use App\Models\ThanhToan;
use App\Models\User;
use Tests\TestCase;

class VietnamTimeRenderingTest extends TestCase
{
    public function test_utc_database_timestamps_and_local_application_timestamps_render_in_vietnam_time(): void
    {
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);

        $booking = new Booking(['NgayTao' => '2026-11-04 08:55:28']);
        $bookingOrder = new DonHang(['BookingID' => 458, 'NgayTao' => '2026-11-04 08:16:23']);
        $manualOrder = new DonHang(['NgayTao' => '2026-11-04 15:12:55']);
        $invoice = new HoaDon(['NgayLap' => '2026-11-04 08:16:23']);
        $review = new DanhGia(['NgayDanhGia' => '2026-10-31 10:06:04']);
        $rpcPayment = new ThanhToan([
            'MaGiaoDich' => 'RPC-fixture',
            'ThoiGian' => '2026-11-02 12:36:49',
        ]);
        $manualPayment = new ThanhToan([
            'MaGiaoDich' => 'TM-fixture',
            'ThoiGian' => '2026-11-02 19:36:49',
        ]);
        $customer = new KhachHang(['NgayTao' => '2026-11-03 14:51:25']);
        $service = new DichVu(['NgayTao' => '2026-11-04 07:11:44']);
        $authAccount = new User([
            'TenDangNhap' => 'auth-fixture',
            'UserAuthId' => 'fixture-auth-id',
            'NgayTao' => '2026-11-03 14:51:25',
        ]);
        $localAccount = new User([
            'TenDangNhap' => 'staff@example.test',
            'NgayTao' => '2026-11-03 14:51:25',
        ]);
        $garmentCategory = new DanhMucLoaiDoGiat;
        $garmentCategory->setRawAttributes(['NgayTao' => '2026-10-04 11:40:43+00'], true);
        $triggerLog = new NhatKyHeThong([
            'HanhDong' => 'Thay đổi trạng thái đơn hàng',
            'ThoiGian' => '2026-11-04 08:28:14',
        ]);
        $applicationLog = new NhatKyHeThong([
            'TaiKhoanID' => 17,
            'HanhDong' => 'Thay đổi trạng thái đơn hàng',
            'ThoiGian' => '2026-11-04 15:28:14',
        ]);

        $this->assertSame('2026-11-04 15:55:28', $booking->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 15:16:23', $bookingOrder->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 15:12:55', $manualOrder->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 15:16:23', $invoice->NgayLap->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-31 17:06:04', $review->NgayDanhGia->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 19:36:49', $rpcPayment->ThoiGian->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 19:36:49', $manualPayment->ThoiGian->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 21:51:25', $customer->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 14:11:44', $service->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 21:51:25', $authAccount->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 14:51:25', $localAccount->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-04 18:40:43', $garmentCategory->NgayTao->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 15:28:14', $triggerLog->ThoiGian->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 15:28:14', $applicationLog->ThoiGian->format('Y-m-d H:i:s'));
    }
}
