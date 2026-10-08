<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderListPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Order list tests require isolated SQLite in-memory storage.');
        }
        Schema::create('DonHang', function (Blueprint $t): void {
            $t->increments('DonHangID');
            $t->string('MaDonHang');
            $t->integer('KhachHangID');
            $t->integer('NhanVienID')->nullable();
            $t->integer('BookingID')->nullable();
            $t->integer('KhuyenMaiID')->nullable();
            $t->string('TrangThai');
            $t->decimal('ThanhTien');
            $t->dateTime('NgayTao');
        });
        foreach (['KhachHang' => ['KhachHangID', 'HoTen'], 'NhanVien' => ['NhanVienID', 'HoTen'], 'DichVu' => ['DichVuID', 'TenDichVu'], 'LoaiDoGiat' => ['LoaiDoGiatID', 'TenLoaiDoGiat'], 'DonViTinh' => ['DonViTinhID', 'TenDonViTinh'], 'KhuyenMai' => ['KhuyenMaiID', 'TenKhuyenMai']] as $name => [$id,$label]) {
            Schema::create($name, function (Blueprint $t) use ($id, $label): void {
                $t->increments($id);
                $t->string($label);
                if ($id === 'KhachHangID') {
                    $t->string('SoDienThoai')->nullable();
                }
            });
            DB::table($name)->insert([$id => 1, $label => 'List fixture']);
        }
        Schema::create('ChiTietDonHang', function (Blueprint $t): void {
            $t->increments('ChiTietDonHangID');
            $t->integer('DonHangID');
            $t->integer('DichVuID');
            $t->integer('LoaiDoGiatID');
            $t->integer('DonViTinhID');
            $t->decimal('SoLuong');
            $t->decimal('KhoiLuong');
        });
        Schema::create('Booking', fn (Blueprint $t) => $t->increments('BookingID'));
        DB::table('Booking')->insert(['BookingID' => 1]);
        foreach (['HoaDon' => 'HoaDonID', 'ThanhToan' => 'ThanhToanID'] as $name => $id) {
            Schema::create($name, function (Blueprint $t) use ($id): void {
                $t->increments($id);
                $t->integer('DonHangID');
            });
        }
    }

    public function test_populated_list_keeps_display_values_and_settled_lock_with_five_queries(): void
    {
        $this->seedOrder(1, OrderStatus::Paid);
        DB::table('HoaDon')->insert(['DonHangID' => 1]);
        DB::table('ThanhToan')->insert(['DonHangID' => 1]);
        DB::connection()->enableQueryLog();
        $page = app(OrderService::class)->getAll();
        $order = $page->first();
        $this->assertSame('List fixture', $order->khachHang->HoTen);
        $this->assertSame('List fixture', $order->chiTietDonHangs->first()->dichVu->TenDichVu);
        $this->assertEquals(2, $order->chiTietDonHangs->sum('SoLuong'));
        $this->assertEquals(1.5, $order->chiTietDonHangs->sum('KhoiLuong'));
        $this->assertSame(7500.0, $order->ThanhTien);
        $this->assertTrue($order->isLocked());
        $this->assertSame(1, $page->total());
        $this->assertLessThanOrEqual(5, count(DB::connection()->getQueryLog()), 'Only list-required data is fetched, without lazy queries while reading display values.');
        DB::connection()->disableQueryLog();
    }

    public function test_search_status_date_sort_and_pagination_still_select_correct_orders(): void
    {
        $this->seedOrder(1, OrderStatus::Paid);
        $this->seedOrder(2, OrderStatus::Received);
        $orders = app(OrderService::class)->getAll(['search' => 'List fixture', 'customer_id' => 1, 'status' => OrderStatus::Received->value, 'date_from' => '2026-10-08', 'date_to' => '2026-10-08']);
        $this->assertSame([2], $orders->pluck('DonHangID')->all());
        for ($id = 3; $id <= 12; $id++) {
            $this->seedOrder($id, OrderStatus::Received);
        }
        $page = app(OrderService::class)->getAll(['sort' => 'code_desc']);
        $this->assertSame(12, $page->total());
        $this->assertSame(10, $page->count());
        $this->assertSame('DH-12', $page->first()->MaDonHang);
        $this->assertSame(2, $page->lastPage());
    }

    private function seedOrder(int $id, OrderStatus $status): void
    {
        DB::table('DonHang')->insert(['DonHangID' => $id, 'MaDonHang' => sprintf('DH-%02d', $id), 'KhachHangID' => 1, 'NhanVienID' => 1, 'BookingID' => 1, 'KhuyenMaiID' => 1, 'TrangThai' => $status->value, 'ThanhTien' => 7500, 'NgayTao' => '2026-10-08 09:00:00']);
        DB::table('ChiTietDonHang')->insert(['DonHangID' => $id, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 1, 'SoLuong' => 2, 'KhoiLuong' => 1.5]);
    }
}
