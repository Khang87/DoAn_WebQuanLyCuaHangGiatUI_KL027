<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Services\CustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CustomerDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('Customer deletion tests require isolated SQLite in-memory storage.');
        }

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
            $table->string('DiaChi')->nullable();
            $table->dateTime('NgayTao')->nullable();
            $table->string('TrangThai')->nullable();
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('KhachHangID');
            $table->string('TrangThai');
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID')->unique();
            $table->string('TrangThai');
        });

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->dateTime('ThoiGian')->nullable();
        });

        foreach (['Booking', 'DanhGia'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('KhachHangID')->nullable();
            });
        }

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('Email')->nullable();
            $table->string('TenDangNhap')->nullable();
            $table->string('UserAuthId')->nullable();
        });

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            'DiemTichLuy',
            'TaiKhoan',
            'DanhGia',
            'Booking',
            'NhatKyHeThong',
            'HoaDon',
            'DonHang',
            'KhachHang',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_customer_without_transactional_records_can_be_deleted_with_its_points_balance(): void
    {
        $customer = $this->createCustomer();
        DB::table('DiemTichLuy')->insert([
            'KhachHangID' => $customer->KhachHangID,
            'DiemHienTai' => 0,
        ]);

        $error = app(CustomerService::class)->delete($customer);

        $this->assertNull($error);
        $this->assertDatabaseMissing('KhachHang', ['KhachHangID' => $customer->KhachHangID]);
        $this->assertDatabaseMissing('DiemTichLuy', ['KhachHangID' => $customer->KhachHangID]);
    }

    public function test_customer_with_related_orders_is_not_deleted_and_returns_details(): void
    {
        $customer = $this->createCustomer();
        $orderId = DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH100000',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Đang giặt',
        ], 'DonHangID');

        $result = app(CustomerService::class)->delete($customer);

        $this->assertSame('Không thể xóa khách hàng vì dữ liệu lịch sử và tài khoản liên kết cần được giữ lại.', $result['message']);
        $this->assertSame([], $result['accounts']);
        $this->assertSame([[
            'id' => $orderId,
            'code' => 'DH100000',
            'status' => 'Đang giặt',
            'payment_status' => 'Chưa thanh toán',
        ]], $result['orders']);
        $this->assertDatabaseHas('KhachHang', ['KhachHangID' => $customer->KhachHangID]);
    }

    public function test_customer_deletion_returns_each_order_with_its_payment_and_processing_status(): void
    {
        $customer = $this->createCustomer();
        $unpaidOrderId = DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH100000',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Đang giặt',
        ], 'DonHangID');
        $paidOrderId = DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH100001',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Đang giao',
        ], 'DonHangID');
        DB::table('HoaDon')->insert([
            'DonHangID' => $paidOrderId,
            'TrangThai' => 'Đã thanh toán',
        ]);
        $settledOrderId = DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH100002',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Đã giao',
        ], 'DonHangID');
        DB::table('HoaDon')->insert([
            'DonHangID' => $settledOrderId,
            'TrangThai' => 'Đã thanh toán',
        ]);

        $result = app(CustomerService::class)->delete($customer);

        $this->assertSame([
            [
                'id' => $unpaidOrderId,
                'code' => 'DH100000',
                'status' => 'Đang giặt',
                'payment_status' => 'Chưa thanh toán',
            ],
            [
                'id' => $paidOrderId,
                'code' => 'DH100001',
                'status' => 'Đang giao',
                'payment_status' => 'Đã thanh toán',
            ],
        ], $result['orders']);
        $this->assertSame([], $result['accounts']);
    }

    public function test_settled_delivered_order_is_not_listed_but_linked_account_is(): void
    {
        $customer = $this->createCustomer();
        $orderId = DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH100002',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Đã giao',
        ], 'DonHangID');
        DB::table('HoaDon')->insert([
            'DonHangID' => $orderId,
            'TrangThai' => 'Đã thanh toán',
        ]);
        $accountId = DB::table('TaiKhoan')->insertGetId([
            'KhachHangID' => $customer->KhachHangID,
            'Email' => 'linked@example.test',
            'TenDangNhap' => 'linked-user',
            'UserAuthId' => 'google-subject',
        ], 'TaiKhoanID');

        $result = app(CustomerService::class)->delete($customer);

        $this->assertSame([], $result['orders']);
        $this->assertSame([[
            'id' => $accountId,
            'email' => 'linked@example.test',
            'username' => 'linked-user',
            'provider' => 'Google',
        ]], $result['accounts']);
        $this->assertSame('Không thể xóa khách hàng vì dữ liệu lịch sử và tài khoản liên kết cần được giữ lại.', $result['message']);
    }

    public function test_legacy_paid_order_that_was_delivered_is_not_listed_as_blocking(): void
    {
        $customer = $this->createCustomer();
        $orderId = DB::table('DonHang')->insertGetId([
            'MaDonHang' => 'DH100003',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Đã thanh toán',
        ], 'DonHangID');
        DB::table('HoaDon')->insert([
            'DonHangID' => $orderId,
            'TrangThai' => 'Đã thanh toán',
        ]);
        DB::table('NhatKyHeThong')->insert([
            'HanhDong' => 'Thay đổi trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => $orderId,
            'DuLieuCu' => json_encode(['TrangThai' => 'Đã giao']),
            'DuLieuMoi' => json_encode(['TrangThai' => 'Đã thanh toán']),
            'ThoiGian' => now(),
        ]);

        $result = app(CustomerService::class)->delete($customer);

        $this->assertSame([], $result['orders']);
        $this->assertSame(
            'Không thể xóa khách hàng vì dữ liệu lịch sử và tài khoản liên kết cần được giữ lại.',
            $result['message']
        );
    }

    public function test_delete_failure_is_reported_as_an_error_flash_not_success(): void
    {
        $customer = new KhachHang;
        $customer->KhachHangID = 42;

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('find')->once()->with(42)->andReturn($customer);
        $customerService->shouldReceive('delete')
            ->once()
            ->with($customer)
            ->andReturn([
                'message' => 'Không thể xóa khách hàng vì dữ liệu lịch sử và tài khoản liên kết cần được giữ lại.',
                'orders' => [],
                'accounts' => [[
                    'id' => 2,
                    'email' => 'linked@example.test',
                    'username' => 'linked-user',
                    'provider' => 'Google',
                ]],
            ]);
        $customerService->shouldReceive('getAll')
            ->once()
            ->with(['search' => null, 'sort' => null])
            ->andReturn(new LengthAwarePaginator([], 0, 10));

        $this->app->instance(CustomerService::class, $customerService);

        $response = $this->withoutMiddleware()
            ->delete(route('customers.destroy', 42));

        $response->assertRedirect(route('customers.index'))
            ->assertSessionHas('error', 'Không thể xóa khách hàng vì dữ liệu lịch sử và tài khoản liên kết cần được giữ lại.')
            ->assertSessionHas('customer_blocking_orders', [])
            ->assertSessionHas('customer_linked_accounts.0.email', 'linked@example.test')
            ->assertSessionMissing('success');

        $this->get(route('customers.index'))
            ->assertOk()
            ->assertSee('linked@example.test')
            ->assertSee('Google')
            ->assertSee('Không thể xóa khách hàng', false);
    }

    public function test_successful_delete_is_reported_as_a_success_flash(): void
    {
        $customer = new KhachHang;
        $customer->KhachHangID = 43;

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('find')->once()->with(43)->andReturn($customer);
        $customerService->shouldReceive('delete')->once()->with($customer)->andReturnNull();

        $this->app->instance(CustomerService::class, $customerService);

        $response = $this->withoutMiddleware()
            ->delete(route('customers.destroy', 43));

        $response->assertRedirect(route('customers.index'))
            ->assertSessionHas('success', 'Khách hàng đã được xóa.')
            ->assertSessionMissing('error');
    }

    private function createCustomer(): KhachHang
    {
        return KhachHang::create([
            'HoTen' => 'Khách hàng kiểm thử',
        ]);
    }
}
