<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\KhuyenMai;
use App\Models\LichSuThayDoiHoaDon;
use App\Models\ThanhToan;
use App\Models\TinNhan;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InvoiceHistoryObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('MatKhau')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro');
            $table->string('TrangThai');
        });

        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai');
        });

        Schema::create('TaiKhoan_VaiTro', function (Blueprint $table): void {
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('VaiTroID');
            $table->primary(['TaiKhoanID', 'VaiTroID']);
        });

        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
            $table->primary(['VaiTroID', 'QuyenID']);
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->string('MaHoaDon');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->decimal('TongTien', 18, 2);
            $table->decimal('GiamGia', 18, 2)->default(0);
            $table->decimal('PhiGiaoHang', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2);
            $table->dateTime('NgayLap');
            $table->string('TrangThai');
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('BookingID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai');
            $table->decimal('TongTien', 18, 2)->default(0);
            $table->integer('DiemSuDung')->default(0);
            $table->decimal('TienGiamDoDiem', 18, 2)->default(0);
            $table->decimal('TienGiamKhuyenMai', 18, 2)->default(0);
            $table->decimal('PhiGiaoHang', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2)->default(0);
        });

        Schema::create('LichSuThayDoiHoaDon', function (Blueprint $table): void {
            $table->increments('LichSuID');
            $table->unsignedInteger('HoaDonID');
            $table->unsignedInteger('TaiKhoanID');
            $table->dateTime('ThoiGian');
            $table->string('TruongThayDoi', 100);
            $table->string('GiaTriCu', 500)->nullable();
            $table->string('GiaTriMoi', 500)->nullable();
            $table->string('LyDo', 500)->nullable();
        });

        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->unsignedInteger('NguoiGuiID');
            $table->unsignedInteger('NguoiNhanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('NoiDung', 1000);
            $table->dateTime('ThoiGianGui');
            $table->string('TrangThai')->default('Đã gửi');
        });

        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('LoaiThongBao')->nullable();
            $table->string('TieuDe');
            $table->string('NoiDung');
            $table->dateTime('ThoiGianGui');
            $table->boolean('DaDoc')->default(false);
        });

        $actor = User::query()->create([
            'TenDangNhap' => 'invoice-auditor',
            'MatKhau' => 'not-used',
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($actor);
    }

    public function test_invoice_status_and_amount_changes_are_recorded_in_invoice_history_not_chat(): void
    {
        $invoice = $this->createInvoice();

        $invoice->update([
            'TrangThai' => 'Đã thanh toán',
            'TongTien' => 120,
            'GiamGia' => 10,
            'PhiGiaoHang' => 5,
            'ThanhTien' => 115,
        ]);

        $history = LichSuThayDoiHoaDon::query()
            ->where('HoaDonID', $invoice->HoaDonID)
            ->orderBy('LichSuID')
            ->get();

        $this->assertSame(
            ['Trạng thái', 'Tổng tiền', 'Giảm giá', 'Phí giao hàng', 'Thành tiền'],
            $history->pluck('TruongThayDoi')->all(),
        );
        $this->assertSame('Chưa thanh toán', $history[0]->GiaTriCu);
        $this->assertSame('Đã thanh toán', $history[0]->GiaTriMoi);
        $this->assertEquals(100.0, (float) $history[1]->GiaTriCu);
        $this->assertSame(1, $history[0]->TaiKhoanID);
        $this->assertSame(0, TinNhan::query()->count());

        $invoice->update(['NgayLap' => '2026-10-03 10:00:00']);
        $this->assertSame(5, LichSuThayDoiHoaDon::query()->count());
    }

    public function test_invoice_details_load_and_render_the_recorded_history(): void
    {
        $invoice = $this->createInvoice();
        $invoice->update(['TrangThai' => 'Đã thanh toán']);

        $detailedInvoice = app(InvoiceService::class)->findDetailed($invoice->HoaDonID);

        $this->assertNotNull($detailedInvoice);
        $this->assertTrue($detailedInvoice->relationLoaded('lichSuThayDoiHoaDons'));
        $this->assertTrue($detailedInvoice->lichSuThayDoiHoaDons->first()->relationLoaded('taiKhoan'));

        $html = view('admin.invoices.show', [
            'invoice' => $detailedInvoice,
            'totalPaid' => 100,
            'balance' => 0,
        ])->render();

        $this->assertStringContainsString('Lịch sử thay đổi', $html);
        $this->assertStringContainsString('Trạng thái', $html);
        $this->assertStringContainsString('invoice-auditor', $html);
        $this->assertStringNotContainsString('Tổng đã thu', $html);
        $this->assertStringNotContainsString('Còn nợ', $html);
        preg_match_all(
            '/<span\b[^>]*class="[^"]*\bbadge\b[^"]*"[^>]*>\s*(?:<i\b[^>]*><\/i>\s*)?'
                .preg_quote('Đã thanh toán', '/').'\s*<\/span>/s',
            $html,
            $badges,
        );

        $this->assertCount(1, $badges[0]);
    }

    public function test_invoice_summary_displays_mobile_payment_method_and_saved_delivery_fees(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill([
            'TongTien' => 75000,
            'GiamGia' => 12000,
            'PhiGiaoHang' => 52000,
            'ThanhTien' => 115000,
            'TrangThai' => 'Đã thanh toán',
        ]);
        $booking = new Booking;
        $booking->forceFill([
            'HinhThucNhanDo' => 'Tại nhà',
            'HinhThucTraDo' => 'Tại nhà',
            'PhuongThucThanhToan' => 'Tiền mặt',
            'PickupDeliveryFee' => 26000,
            'DeliveryFee' => 26000,
            'PickupDistanceMeters' => 8100,
            'DeliveryDistanceMeters' => 8100,
        ]);
        $order = new DonHang;
        $order->forceFill([
            'DonHangID' => 1,
            'MaDonHang' => 'DH00000001',
            'TrangThai' => OrderStatus::Delivered->value,
            'TongTien' => 75000,
            'TienGiamKhuyenMai' => 7000,
            'TienGiamDoDiem' => 5000,
            'DiemSuDung' => 5000,
            'PhiGiaoHang' => 52000,
            'ThanhTien' => 120000,
        ]);
        $order->setRelation('khachHang', null);
        $order->setRelation('booking', $booking);
        $order->setRelation('khuyenMai', (new KhuyenMai)->forceFill([
            'MaKhuyenMai' => 'GIAM7K',
        ]));
        $laundryDetail = (new ChiTietDonHang)->forceFill([
            'SoLuong' => null,
            'KhoiLuong' => 0.5,
            'DonGia' => 1000,
            'ThanhTien' => 500,
        ]);
        $laundryDetail->setRelation('dichVu', null);
        $laundryDetail->setRelation('loaiDoGiat', null);
        $laundryDetail->setRelation('donViTinh', null);
        $order->setRelation('chiTietDonHangs', collect([$laundryDetail]));
        $order->setRelation('thanhToans', collect([
            (new ThanhToan)->forceFill([
                'PhuongThuc' => 'Tiền mặt',
                'MaGiaoDich' => 'TM_DH00000001_20261101170642',
                'TrangThai' => PaymentStatus::Paid->value,
            ]),
        ]));
        $invoice->setRelation('donHang', $order);
        $invoice->setRelation('lichSuThayDoiHoaDons', collect());

        $html = view('admin.invoices.show', [
            'invoice' => $invoice,
            'totalPaid' => 115000,
            'balance' => 0,
        ])->render();

        $this->assertStringNotContainsString('Thanh toán Tiền mặt', $html);
        $this->assertStringContainsString('Phương thức thanh toán', $html);
        $this->assertStringContainsString('<td class="text-end">0.50</td>', $html);
        $this->assertStringNotContainsString('0.50 kg', $html);
        $this->assertStringContainsString('Tổng tiền đơn hàng', $html);
        $this->assertStringContainsString('Tiền mặt', $html);
        $this->assertMatchesRegularExpression('/Phí nhận đồ\s*:\s*8\.1 km/u', $html);
        $this->assertMatchesRegularExpression('/Phí giao đồ\s*:\s*8\.1 km/u', $html);
        $this->assertStringNotContainsString('GIAM7K', $html);
        $this->assertStringNotContainsString('Điểm đã sử dụng', $html);
        $this->assertStringNotContainsString('5.000 điểm', $html);
        $this->assertStringContainsString('Giảm từ mã khuyến mãi', $html);
        $this->assertStringContainsString('-7.000 đ', $html);
        $this->assertStringContainsString('Giảm từ điểm', $html);
        $this->assertStringContainsString('-5.000 đ', $html);
        $this->assertStringNotContainsString('Tổng tiền được giảm', $html);
        $this->assertStringNotContainsString('12.000 đ', $html);
        $this->assertStringContainsString('115.000 đ', $html);
        $this->assertStringNotContainsString('120.000 đ', $html);
        $this->assertStringNotContainsString('115.000 VNĐ', $html);
        $this->assertStringContainsString('Giá cuối cùng có thể được điều chỉnh sau khi cửa hàng kiểm nhận đồ.', $html);
        $this->assertStringContainsString('Thanh toán', $html);
        $this->assertStringContainsString('Đã thanh toán', $html);
        $this->assertStringContainsString('Mã giao dịch: TM_DH00000001_20261101170642', $html);
        $this->assertSame(
            1,
            substr_count($html, 'badge status-badge bg-info-subtle text-info-emphasis border border-info rounded-pill px-3 py-2'),
        );
        $this->assertSame(1, substr_count($html, '<i class="fas fa-check-circle me-1"></i>'));
        $this->assertStringNotContainsString('Tổng đã thu', $html);
        $this->assertStringNotContainsString('Còn nợ', $html);
    }

    public function test_invoice_summary_uses_combined_delivery_fee_when_delivery_legs_are_unavailable(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill([
            'TongTien' => 75000,
            'PhiGiaoHang' => 9000,
            'ThanhTien' => 84000,
        ]);
        $order = (new DonHang)->forceFill([
            'DonHangID' => 1,
            'MaDonHang' => 'DH00000001',
            'TongTien' => 75000,
            'PhiGiaoHang' => 9000,
            'ThanhTien' => 84000,
        ]);
        $order->setRelation('khachHang', null);
        $order->setRelation('booking', null);
        $order->setRelation('chiTietDonHangs', collect());
        $order->setRelation('khuyenMai', null);
        $order->setRelation('thanhToans', collect());
        $invoice->setRelation('donHang', $order);
        $invoice->setRelation('lichSuThayDoiHoaDons', collect());

        $html = view('admin.invoices.show', [
            'invoice' => $invoice,
            'totalPaid' => 0,
            'balance' => 84000,
        ])->render();

        $this->assertStringContainsString('Phí giao nhận', $html);
        $this->assertStringContainsString('9.000 đ', $html);
        $this->assertDoesNotMatchRegularExpression('/Phí giao đồ\s*·\s*0 km/u', $html);
    }

    public function test_invoice_created_from_order_inherits_its_delivery_fee_and_discounts(): void
    {
        DB::table('DonHang')->insert([
            'MaDonHang' => 'DH-INVOICE-FEE',
            'TrangThai' => OrderStatus::Received->value,
            'TongTien' => 75000,
            'DiemSuDung' => 5000,
            'TienGiamDoDiem' => 5000,
            'TienGiamKhuyenMai' => 7000,
            'PhiGiaoHang' => 52000,
            'ThanhTien' => 115000,
        ]);
        $order = DonHang::query()->findOrFail(1);

        $invoice = app(InvoiceService::class)->createFromOrder($order);

        $this->assertSame(75000.0, $invoice->TongTien);
        $this->assertSame(12000.0, $invoice->GiamGia);
        $this->assertSame(52000.0, $invoice->PhiGiaoHang);
        $this->assertSame(115000.0, $invoice->ThanhTien);
    }

    public function test_invoice_detail_keeps_print_layout_without_a_button_or_automatic_printing(): void
    {
        $invoice = $this->createInvoice();
        $invoice->TrangThai = 'Đã thanh toán';
        $invoice->setRelation('donHang', null);
        $invoice->setRelation('lichSuThayDoiHoaDons', collect());

        foreach ([null, '0', 'false', '1'] as $print) {
            request()->query->set('print', $print);
            $html = view('admin.invoices.show', [
                'invoice' => $invoice,
                'totalPaid' => 100,
                'balance' => 0,
            ])->render();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($document);

            $this->assertSame(0, $xpath->query('//button[contains(normalize-space(.), "In hóa đơn")]')->length);
            $this->assertSame(0, $xpath->query('//h5[normalize-space(.)="Thao tác"]')->length);
            $this->assertSame(1, $xpath->query('//a[@href="'.route('invoices.index').'" and contains(normalize-space(.), "Quay lại")]')->length);
            $this->assertStringNotContainsString('window.print()', $html);
            $this->assertStringContainsString('Thanh toán', $html);
            $this->assertStringContainsString('Thông tin hóa đơn', $html);
            $this->assertStringContainsString('Lịch sử thay đổi', $html);
            $this->assertStringContainsString('@media print', $html);
        }
    }

    public function test_invoice_list_print_button_shares_view_permission_without_navigation(): void
    {
        $invoice = $this->createInvoice();
        $invoice->TrangThai = 'Đã thanh toán';
        $invoice->setRelation('donHang', null);

        foreach ([true, false] as $canView) {
            Gate::define('invoices.view', fn () => $canView);
            $html = view('admin.invoices.index', [
                'invoices' => new LengthAwarePaginator(collect([$invoice]), 1, 10),
            ])->render();
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($document);
            $buttons = $xpath->query('//tbody//button[@title="In hóa đơn"]');

            $this->assertCount($canView ? 1 : 0, $buttons);
            $this->assertSame(0, $xpath->query('//tbody//a[@title="In hóa đơn"]')->length);
            if ($canView) {
                $button = $buttons->item(0);
                $this->assertSame('button', $button->getAttribute('type'));
                $this->assertSame(route('invoices.show', $invoice), $button->getAttribute('data-invoice-print'));
                $this->assertFalse($button->hasAttribute('target'));
                $this->assertFalse($button->hasAttribute('href'));
                $this->assertSame('In hóa đơn', $button->getAttribute('aria-label'));
                $this->assertStringContainsString('btn-order-action', $button->getAttribute('class'));
                $this->assertSame(1, $xpath->query('../a[@title="Xem"]', $button)->length);
                $this->assertSame(1, $xpath->query('//*[@id="invoice-print-error" and @role="alert" and @hidden]')->length);
            }
        }
    }

    public function test_order_view_omits_pending_invoice_controls_and_preserves_existing_invoice_guard(): void
    {
        $detailSource = file_get_contents(resource_path('views/admin/orders/show.blade.php'));
        $listSource = file_get_contents(resource_path('views/admin/orders/index.blade.php'));

        $this->assertStringNotContainsString('đang chờ thanh toán; thu tiền xong hóa đơn mới hiển thị.', $detailSource);
        $this->assertStringNotContainsString('Sửa hóa đơn chờ thu', $detailSource);
        $this->assertStringNotContainsString('Xóa hóa đơn chờ thu', $detailSource);

        // Nút hóa đơn/thanh toán đã dời ra cột "Thao tác" của danh sách, màn chi
        // tiết không còn khối thao tác nào nữa.
        $this->assertStringNotContainsString("route('payments.create', ['order_id' => \$order->getKey()])", $detailSource);
        $this->assertStringNotContainsString('@if($orderInvoice?->isPaid())', $detailSource);
        $this->assertStringContainsString('@elseif(! $orderInvoice && in_array($order->TrangThai,', $listSource);
        $this->assertStringContainsString("route('payments.create', ['order_id' => \$order->getKey()])", $listSource);
        $this->assertStringContainsString('@if($orderInvoice?->isPaid())', $listSource);
    }

    private function createInvoice(): HoaDon
    {
        return HoaDon::query()->create([
            'MaHoaDon' => 'HD001',
            'TongTien' => 100,
            'GiamGia' => 0,
            'PhiGiaoHang' => 0,
            'ThanhTien' => 100,
            'NgayLap' => '2026-10-02 10:00:00',
            'TrangThai' => 'Chưa thanh toán',
        ]);
    }
}
