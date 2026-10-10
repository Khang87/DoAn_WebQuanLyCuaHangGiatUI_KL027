<?php

namespace Tests\Feature;

use App\Models\HoaDon;
use App\Models\LichSuThayDoiHoaDon;
use App\Models\TinNhan;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
