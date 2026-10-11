<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderDeletionHistoryTest extends TestCase
{
    private int $nextOrderId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Order history tests require isolated SQLite in-memory storage.');
        }

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach ([
            'NhatKyHeThong', 'DanhGia', 'TinNhan', 'ThongBao', 'ThanhToan', 'HoaDon',
            'GiaoNhan', 'ChiTietDonHang', 'DonViTinh', 'LoaiDoGiat', 'DichVu',
            'Booking', 'KhuyenMai', 'NhanVien', 'KhachHang', 'DonHang',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_owner_cannot_physically_delete_order_with_related_business_records(): void
    {
        $orderId = $this->createOrder(OrderStatus::Delivered);
        DB::table('HoaDon')->insert([
            'DonHangID' => $orderId,
            'ThanhTien' => 120000,
            'TrangThai' => InvoiceStatus::Unpaid->value,
        ]);
        $order = app(OrderService::class)->find($orderId);

        try {
            app(OrderService::class)->delete($order, true);
            $this->fail('Orders with related business history must not be physically deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }

        $this->assertDatabaseHas('DonHang', ['DonHangID' => $orderId]);
        $this->assertDatabaseHas('HoaDon', ['DonHangID' => $orderId]);
    }

    private function createSchema(): void
    {
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('KhachHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->unsignedInteger('BookingID')->nullable();
            $table->string('TrangThai');
            $table->decimal('TongTien', 18, 2)->default(0);
            $table->integer('DiemSuDung')->default(0);
            $table->decimal('TienGiamDoDiem', 18, 2)->default(0);
            $table->decimal('TienGiamKhuyenMai', 18, 2)->default(0);
            $table->decimal('PhiGiaoHang', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->string('GhiChu')->nullable();
            $table->dateTime('NgayTao')->nullable();
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
        });
        Schema::create('NhanVien', fn (Blueprint $table) => $table->increments('NhanVienID'));
        Schema::create('KhuyenMai', fn (Blueprint $table) => $table->increments('KhuyenMaiID'));
        Schema::create('Booking', fn (Blueprint $table) => $table->increments('BookingID'));
        Schema::create('DichVu', fn (Blueprint $table) => $table->increments('DichVuID'));
        Schema::create('LoaiDoGiat', fn (Blueprint $table) => $table->increments('LoaiDoGiatID'));
        Schema::create('DonViTinh', fn (Blueprint $table) => $table->increments('DonViTinhID'));

        Schema::create('ChiTietDonHang', function (Blueprint $table): void {
            $table->increments('ChiTietDonHangID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('DichVuID')->nullable();
            $table->unsignedInteger('LoaiDoGiatID')->nullable();
            $table->unsignedInteger('DonViTinhID')->nullable();
        });
        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('TrangThai');
        });
        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('ThanhTien', 18, 2);
            $table->string('TrangThai');
        });
        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('SoTien', 18, 2);
            $table->string('PhuongThuc');
            $table->string('TrangThai');
        });
        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('LoaiThongBao')->nullable();
            $table->string('TieuDe')->nullable();
            $table->string('NoiDung')->nullable();
            $table->dateTime('ThoiGianGui')->nullable();
            $table->boolean('DaDoc')->default(false);
        });
        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->unsignedInteger('DonHangID')->nullable();
        });
        Schema::create('DanhGia', function (Blueprint $table): void {
            $table->increments('DanhGiaID');
            $table->unsignedInteger('DonHangID')->nullable();
        });
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedBigInteger('BanGhiID')->nullable();
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian');
        });
    }

    private function createOrder(OrderStatus $status): int
    {
        return (int) DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH-HISTORY-'.$this->nextOrderId++,
            'KhachHangID' => 1,
            'TrangThai' => $status->value,
            'DiemSuDung' => 150,
            'ThanhTien' => 120000,
            'NgayTao' => now(),
        ], 'DonHangID');
    }
}
