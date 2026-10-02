<?php

namespace Tests\Feature;

use App\Models\NhatKyHeThong;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserAccountAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('MatKhau');
            $table->string('Email')->nullable();
            $table->string('SoDienThoai')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai');
            $table->dateTime('NgayTao')->nullable();
            $table->string('UserAuthId')->nullable();
        });

        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro');
            $table->string('TrangThai');
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen')->nullable();
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
        });

        Schema::create('TaiKhoan_VaiTro', function (Blueprint $table): void {
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('VaiTroID');
            $table->primary(['TaiKhoanID', 'VaiTroID']);
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
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });

        DB::table('VaiTro')->insert([
            ['VaiTroID' => 1, 'TenVaiTro' => 'Nhân viên', 'TrangThai' => 'Hoạt động'],
            ['VaiTroID' => 2, 'TenVaiTro' => 'Quản lý', 'TrangThai' => 'Hoạt động'],
        ]);
        DB::table('NhanVien')->insert(['NhanVienID' => 9, 'HoTen' => 'Nhân viên kiểm thử']);

        $actor = User::query()->create([
            'TenDangNhap' => 'actor@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'actor@example.com',
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($actor);
    }

    public function test_account_creation_and_changes_are_audited_without_recording_passwords(): void
    {
        $service = app(UserService::class);
        $user = $service->create([
            'email' => 'staff@example.com',
            'password' => 'SecretPass123!',
            'role' => 'nhan-vien',
            'NhanVienID' => 9,
        ]);

        $creation = NhatKyHeThong::query()->where('BanGhiID', $user->TaiKhoanID)->firstOrFail();
        $this->assertSame('Tạo tài khoản', $creation->HanhDong);
        $this->assertSame('TaiKhoan', $creation->BangDuLieu);
        $this->assertSame('staff@example.com', $creation->DuLieuMoi['Email']);
        $this->assertArrayNotHasKey('MatKhau', $creation->DuLieuMoi);
        $this->assertSame(1, $creation->DuLieuMoi['VaiTroIDs'][0]);

        $service->update($user, [
            'email' => 'manager@example.com',
            'phone' => '0901234567',
            'role' => 'quan-ly',
        ]);

        $change = NhatKyHeThong::query()
            ->where('BanGhiID', $user->TaiKhoanID)
            ->where('HanhDong', 'Thay đổi tài khoản')
            ->firstOrFail();
        $this->assertSame('staff@example.com', $change->DuLieuCu['Email']);
        $this->assertSame('manager@example.com', $change->DuLieuMoi['Email']);
        $this->assertSame([1], $change->DuLieuCu['VaiTroIDs']);
        $this->assertSame([2], $change->DuLieuMoi['VaiTroIDs']);

        $service->update($user, ['password' => 'ChangedPass123!']);

        $passwordChange = NhatKyHeThong::query()
            ->where('HanhDong', 'Đổi mật khẩu tài khoản')
            ->firstOrFail();
        $this->assertArrayNotHasKey('MatKhau', $passwordChange->DuLieuCu);
        $this->assertArrayNotHasKey('MatKhau', $passwordChange->DuLieuMoi);
        $this->assertSame(3, NhatKyHeThong::query()->count());
    }
}
