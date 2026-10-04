<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
            $table->string('DiaChi')->nullable();
            $table->dateTime('NgayTao')->nullable();
            $table->string('TrangThai')->nullable();
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
        Schema::dropIfExists('DiemTichLuy');
        Schema::dropIfExists('KhachHang');

        parent::tearDown();
    }

    public function test_customer_can_be_updated_without_changing_the_existing_email(): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Tên cũ',
            'Email' => 'customer@example.com',
        ], 'KhachHangID');

        $response = $this->withoutMiddleware()->put(route('customers.update', $customerId), [
            'HoTen' => 'Tên mới',
            'Email' => 'customer@example.com',
            'SoDienThoai' => '0901234567',
            'DiaChi' => 'Địa chỉ mới',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('KhachHang', [
            'KhachHangID' => $customerId,
            'HoTen' => 'Tên mới',
            'Email' => 'customer@example.com',
            'SoDienThoai' => '0901234567',
            'DiaChi' => 'Địa chỉ mới',
        ]);
    }

    public function test_edit_form_shows_the_customer_email(): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Tên khách',
            'Email' => 'customer@example.com',
        ], 'KhachHangID');

        $response = $this->withViewErrors([])
            ->withoutMiddleware()
            ->get(route('customers.edit', $customerId));

        $response->assertOk()
            ->assertSee('value="customer@example.com"', false);
    }
}
