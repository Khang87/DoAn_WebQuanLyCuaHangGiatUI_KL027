<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->markTestSkipped('Profile update tests require isolated SQLite in-memory storage.');
        }

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap')->unique();
            $table->string('MatKhau')->nullable();
            $table->string('Email', 150)->nullable();
            $table->string('SoDienThoai', 15)->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
            $table->dateTime('NgayTao')->nullable();
            $table->string('UserAuthId')->nullable();
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen', 100);
            $table->string('SoDienThoai', 15);
            $table->string('Email', 150)->nullable();
            $table->string('DiaChi')->nullable();
            $table->string('ChucDanh')->nullable();
            $table->date('NgayVaoLam')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen', 100);
            $table->string('SoDienThoai', 15)->nullable();
            $table->string('Email', 150)->nullable();
            $table->string('DiaChi')->nullable();
            $table->dateTime('NgayTao')->nullable();
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
        });

        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
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
            $table->dateTime('ThoiGian')->nullable();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });

        DB::table('VaiTro')->insert([
            ['VaiTroID' => 1, 'TenVaiTro' => 'Nhân viên', 'TrangThai' => 'Hoạt động'],
            ['VaiTroID' => 2, 'TenVaiTro' => 'Chủ cửa hàng', 'TrangThai' => 'Hoạt động'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('NhatKyHeThong');
        Schema::dropIfExists('VaiTro_Quyen');
        Schema::dropIfExists('TaiKhoan_VaiTro');
        Schema::dropIfExists('Quyen');
        Schema::dropIfExists('VaiTro');
        Schema::dropIfExists('TaiKhoan');
        Schema::dropIfExists('NhanVien');
        Schema::dropIfExists('KhachHang');

        parent::tearDown();
    }

    public function test_staff_profile_updates_account_and_employee_details(): void
    {
        DB::table('NhanVien')->insert([
            'NhanVienID' => 7,
            'HoTen' => 'Tên cũ',
            'SoDienThoai' => '0900000001',
            'Email' => 'old@example.com',
        ]);
        DB::table('TaiKhoan')->insert([
            'TaiKhoanID' => 11,
            'TenDangNhap' => 'staff11',
            'Email' => 'old@example.com',
            'SoDienThoai' => '0900000001',
            'NhanVienID' => 7,
        ]);
        $this->assignRole(11, 1);

        $user = User::query()->findOrFail(11);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Tên mới',
                'email' => 'new@example.com',
                'phone' => '0900000002',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('TaiKhoan', [
            'TaiKhoanID' => 11,
            'TenDangNhap' => 'staff11',
            'Email' => 'new@example.com',
            'SoDienThoai' => '0900000002',
        ]);
        $this->assertDatabaseHas('NhanVien', [
            'NhanVienID' => 7,
            'HoTen' => 'Tên mới',
            'Email' => 'new@example.com',
            'SoDienThoai' => '0900000002',
        ]);
        $this->assertDatabaseHas('NhatKyHeThong', [
            'BanGhiID' => 11,
            'BangDuLieu' => 'TaiKhoan',
            'HanhDong' => 'Thay đổi tài khoản',
        ]);
    }

    public function test_customer_profile_updates_account_and_customer_details(): void
    {
        DB::table('KhachHang')->insert([
            'KhachHangID' => 9,
            'HoTen' => 'Tên cũ',
            'SoDienThoai' => '0900000003',
            'Email' => 'old@example.com',
        ]);
        DB::table('TaiKhoan')->insert([
            'TaiKhoanID' => 12,
            'TenDangNhap' => 'customer12',
            'Email' => 'old@example.com',
            'SoDienThoai' => '0900000003',
            'KhachHangID' => 9,
        ]);
        $this->assignRole(12, 2);

        $user = User::query()->findOrFail(12);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Tên khách mới',
                'email' => 'customer@example.com',
                'phone' => '',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('TaiKhoan', [
            'TaiKhoanID' => 12,
            'TenDangNhap' => 'customer12',
            'Email' => 'customer@example.com',
            'SoDienThoai' => null,
        ]);
        $this->assertDatabaseHas('KhachHang', [
            'KhachHangID' => 9,
            'HoTen' => 'Tên khách mới',
            'Email' => 'customer@example.com',
            'SoDienThoai' => null,
        ]);
    }

    public function test_profile_rejects_an_email_used_by_another_account(): void
    {
        DB::table('KhachHang')->insert([
            'KhachHangID' => 21,
            'HoTen' => 'Khách một',
            'SoDienThoai' => '0900000021',
        ]);
        DB::table('KhachHang')->insert([
            'KhachHangID' => 22,
            'HoTen' => 'Khách hai',
            'SoDienThoai' => '0900000022',
        ]);
        DB::table('TaiKhoan')->insert([
            [
                'TaiKhoanID' => 31,
                'TenDangNhap' => 'customer31',
                'Email' => 'first@example.com',
                'KhachHangID' => 21,
            ],
            [
                'TaiKhoanID' => 32,
                'TenDangNhap' => 'customer32',
                'Email' => 'taken@example.com',
                'KhachHangID' => 22,
            ],
        ]);
        $this->assignRole(31, 2);

        $user = User::query()->findOrFail(31);

        $this->actingAs($user)
            ->from(route('profile'))
            ->put(route('profile.update'), [
                'name' => 'Khách một',
                'email' => 'taken@example.com',
                'phone' => '',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('TaiKhoan', [
            'TaiKhoanID' => 31,
            'Email' => 'first@example.com',
        ]);
    }

    private function assignRole(int $accountId, int $roleId): void
    {
        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $accountId,
            'VaiTroID' => $roleId,
        ]);
    }
}
