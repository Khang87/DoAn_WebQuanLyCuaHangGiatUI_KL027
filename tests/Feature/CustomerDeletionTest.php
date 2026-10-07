<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Services\CustomerService;
use Illuminate\Database\Schema\Blueprint;
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

        foreach (['DonHang', 'Booking', 'DanhGia', 'TaiKhoan'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('KhachHangID')->nullable();
            });
        }

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['DiemTichLuy', 'TaiKhoan', 'DanhGia', 'Booking', 'DonHang', 'KhachHang'] as $tableName) {
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
        DB::table('DonHang')->insert([
            'KhachHangID' => $customer->KhachHangID,
        ]);

        $error = app(CustomerService::class)->delete($customer);

        $this->assertSame('Không thể xóa khách hàng vì còn dữ liệu liên quan: 1 đơn hàng.', $error);
        $this->assertDatabaseHas('KhachHang', ['KhachHangID' => $customer->KhachHangID]);
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
            ->andReturn('Không thể xóa khách hàng vì còn dữ liệu liên quan: 1 đơn hàng.');

        $this->app->instance(CustomerService::class, $customerService);

        $response = $this->withoutMiddleware()
            ->delete(route('customers.destroy', 42));

        $response->assertRedirect(route('customers.index'))
            ->assertSessionHas('error', 'Không thể xóa khách hàng vì còn dữ liệu liên quan: 1 đơn hàng.')
            ->assertSessionMissing('success');
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
