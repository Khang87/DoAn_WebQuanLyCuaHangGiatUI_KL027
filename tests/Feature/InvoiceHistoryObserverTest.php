<?php

namespace Tests\Feature;

use App\Models\HoaDon;
use App\Models\LichSuThayDoiHoaDon;
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
        $this->assertStringContainsString('Tổng đã thu', $html);
        preg_match_all(
            '/<span\b[^>]*class="[^"]*\bbadge\b[^"]*"[^>]*>\s*(?:<i\b[^>]*><\/i>\s*)?'
                .preg_quote('Đã thanh toán', '/').'\s*<\/span>/s',
            $html,
            $badges,
        );

        $this->assertCount(1, $badges[0]);
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
            $this->assertStringContainsString('Tổng hợp', $html);
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
        $source = file_get_contents(resource_path('views/admin/orders/show.blade.php'));

        $this->assertStringNotContainsString('đang chờ thanh toán; thu tiền xong hóa đơn mới hiển thị.', $source);
        $this->assertStringNotContainsString('Sửa hóa đơn chờ thu', $source);
        $this->assertStringNotContainsString('Xóa hóa đơn chờ thu', $source);
        $this->assertStringContainsString('@elseif(! $orderInvoice && in_array($order->TrangThai,', $source);
        $this->assertStringContainsString("route('payments.create', ['order_id' => \$order->getKey()])", $source);
        $this->assertStringContainsString('@if($orderInvoice?->isPaid())', $source);
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
