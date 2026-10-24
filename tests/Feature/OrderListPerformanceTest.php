<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
        Schema::create('TaiKhoan', function (Blueprint $t): void {
            $t->increments('TaiKhoanID');
            $t->integer('KhachHangID');
            $t->string('AvatarURL')->nullable();
            $t->string('MatKhau');
        });
        DB::table('TaiKhoan')->insert([
            'TaiKhoanID' => 101,
            'KhachHangID' => 1,
            'AvatarURL' => 'https://example.test/avatars/customer-1.jpg',
            'MatKhau' => 'fixture-password-must-not-be-selected',
        ]);
        Schema::create('NhatKyHeThong', function (Blueprint $t): void {
            $t->increments('NhatKyID');
            $t->integer('BanGhiID');
            $t->string('BangDuLieu');
            $t->string('HanhDong');
            $t->json('DuLieuCu')->nullable();
            $t->json('DuLieuMoi')->nullable();
            $t->dateTime('ThoiGian');
        });
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
        Schema::create('GiaoNhan', function (Blueprint $t): void {
            $t->increments('GiaoNhanID');
            $t->integer('DonHangID');
            $t->integer('NhanVienID')->nullable();
            $t->string('LoaiGiaoNhan');
            $t->string('HinhThuc')->nullable();
            $t->string('TrangThai')->nullable();
        });
        DB::table('Booking')->insert(['BookingID' => 1]);
        foreach (['HoaDon' => 'HoaDonID', 'ThanhToan' => 'ThanhToanID'] as $name => $id) {
            Schema::create($name, function (Blueprint $t) use ($id): void {
                $t->increments($id);
                $t->integer('DonHangID');
                $t->string('TrangThai');
            });
        }
    }

    public function test_populated_list_keeps_display_values_and_settled_lock_with_ten_queries(): void
    {
        $this->seedOrder(1, OrderStatus::Paid);
        DB::table('HoaDon')->insert(['DonHangID' => 1, 'TrangThai' => InvoiceStatus::Paid->value]);
        DB::table('ThanhToan')->insert(['DonHangID' => 1, 'TrangThai' => PaymentStatus::Paid->value]);
        DB::table('NhatKyHeThong')->insert([
            'BanGhiID' => 1,
            'BangDuLieu' => 'DonHang',
            'HanhDong' => 'Thay đổi trạng thái đơn hàng',
            'DuLieuCu' => json_encode(['TrangThai' => OrderStatus::Received->value]),
            'DuLieuMoi' => json_encode(['TrangThai' => OrderStatus::Paid->value]),
            'ThoiGian' => '2026-10-08 10:00:00',
        ]);
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $page = app(OrderService::class)->getAll();
            $this->assertCount(10, $connection->getQueryLog(), 'Pagination, orders, customers, accounts, details, services, status changes, booking, delivery legs and invoices.');
            $order = $page->first();
            $this->assertSame('List fixture', $order->khachHang->HoTen);
            $this->assertTrue($order->khachHang->relationLoaded('taiKhoan'));
            $this->assertSame('https://example.test/avatars/customer-1.jpg', $order->khachHang->taiKhoan->AvatarURL);
            $this->assertSame('https://example.test/avatars/customer-1.jpg', $order->khachHang->avatar_url);
            $this->assertArrayNotHasKey('MatKhau', $order->khachHang->taiKhoan->getAttributes());
            $this->assertSame('List fixture', $order->chiTietDonHangs->first()->dichVu->TenDichVu);
            $this->assertEquals(2, $order->chiTietDonHangs->sum('SoLuong'));
            $this->assertEquals(1.5, $order->chiTietDonHangs->sum('KhoiLuong'));
            $this->assertSame(7500.0, $order->ThanhTien);
            $this->assertTrue($order->has_paid_invoice);
            $this->assertTrue($order->has_successful_payment);
            $this->assertTrue($order->isPaid());
            $this->assertTrue($order->isLocked());
            $this->assertTrue($order->relationLoaded('statusChanges'));
            $this->assertCount(1, $order->statusChanges);
            $this->assertSame(OrderStatus::Received, $order->operationalStatusEnum());
            $this->assertSame(1, $page->total());
            $this->assertCount(10, $connection->getQueryLog(), 'Reading display values and avatars must not lazy load.');

            $html = view('admin.orders.index', ['orders' => $page])->render();
            $this->assertStringContainsString('src="https://example.test/avatars/customer-1.jpg"', $html);
            $this->assertStringContainsString('DH-01', $html);
            $this->assertCount(10, $connection->getQueryLog(), 'Rendering the orders view must not add queries.');
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_multiple_customers_and_missing_accounts_keep_avatar_queries_bounded(): void
    {
        for ($customerId = 2; $customerId <= 5; $customerId++) {
            DB::table('KhachHang')->insert(['KhachHangID' => $customerId, 'HoTen' => 'Customer '.$customerId]);
            if ($customerId < 5) {
                DB::table('TaiKhoan')->insert([
                    'TaiKhoanID' => 100 + $customerId,
                    'KhachHangID' => $customerId,
                    'AvatarURL' => "https://example.test/avatars/customer-{$customerId}.jpg",
                    'MatKhau' => 'fixture-password-must-not-be-selected',
                ]);
            }
        }
        for ($id = 1; $id <= 10; $id++) {
            $this->seedOrder($id, OrderStatus::Received, (($id - 1) % 5) + 1);
        }
        DB::table('HoaDon')->insert([
            ['DonHangID' => 1, 'TrangThai' => InvoiceStatus::Paid->value],
            ['DonHangID' => 3, 'TrangThai' => InvoiceStatus::Unpaid->value],
            ['DonHangID' => 4, 'TrangThai' => InvoiceStatus::Cancelled->value],
        ]);
        DB::table('ThanhToan')->insert([
            ['DonHangID' => 2, 'TrangThai' => PaymentStatus::Paid->value],
            ['DonHangID' => 3, 'TrangThai' => PaymentStatus::Pending->value],
            ['DonHangID' => 4, 'TrangThai' => PaymentStatus::Failed->value],
            ['DonHangID' => 5, 'TrangThai' => PaymentStatus::Refunded->value],
        ]);
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $page = app(OrderService::class)->getAll(['sort' => 'code_asc']);
            $this->assertCount(10, $connection->getQueryLog(), 'A full page with distinct customers must use the same ten queries.');
            $this->assertSame(10, $page->total());
            $this->assertCount(10, $page);
            $this->assertCount(5, $page->pluck('KhachHangID')->unique());

            foreach ($page as $order) {
                $this->assertTrue($order->relationLoaded('khachHang'));
                $customer = $order->khachHang;
                $customerId = (int) $order->KhachHangID;
                $this->assertTrue($customer->relationLoaded('taiKhoan'));
                if ($customerId === 5) {
                    $this->assertNull($customer->taiKhoan);
                    $this->assertSame(asset('assets/images/user_1.jpg'), $customer->avatar_url);
                } else {
                    $avatarUrl = "https://example.test/avatars/customer-{$customerId}.jpg";
                    $this->assertSame([
                        'TaiKhoanID' => 100 + $customerId,
                        'KhachHangID' => $customerId,
                        'AvatarURL' => $avatarUrl,
                    ], $customer->taiKhoan->getAttributes());
                    $this->assertArrayNotHasKey('MatKhau', $customer->taiKhoan->getAttributes());
                    $this->assertSame($avatarUrl, $customer->avatar_url);
                }
                $this->assertSame($order->DonHangID === 1, $order->has_paid_invoice);
                $this->assertSame($order->DonHangID === 2, $order->has_successful_payment);
                $this->assertSame($order->DonHangID === 1, $order->isPaid());
                $this->assertSame(in_array($order->DonHangID, [1, 2], true), $order->isLocked());
            }
            $this->assertCount(10, $connection->getQueryLog(), 'Avatar access and payment checks must not add per-order queries.');

            $html = view('admin.orders.index', ['orders' => $page])->render();
            for ($customerId = 1; $customerId <= 4; $customerId++) {
                $this->assertSame(2, substr_count($html, 'src="https://example.test/avatars/customer-'.$customerId.'.jpg"'));
            }
            $this->assertSame(2, substr_count($html, 'aria-label="Ảnh đại diện của Customer 5"'));
            $this->assertSame(8, substr_count($html, 'alt="" class="w-100 h-100 avatar-cover"'));
            $this->assertCount(10, $connection->getQueryLog(), 'Rendering every customer, including missing accounts, must not add queries.');
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
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

    private function seedOrder(int $id, OrderStatus $status, int $customerId = 1): void
    {
        DB::table('DonHang')->insert(['DonHangID' => $id, 'MaDonHang' => sprintf('DH-%02d', $id), 'KhachHangID' => $customerId, 'NhanVienID' => 1, 'BookingID' => 1, 'KhuyenMaiID' => 1, 'TrangThai' => $status->value, 'ThanhTien' => 7500, 'NgayTao' => '2026-10-08 09:00:00']);
        DB::table('ChiTietDonHang')->insert(['DonHangID' => $id, 'DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 1, 'SoLuong' => 2, 'KhoiLuong' => 1.5]);
    }
}
