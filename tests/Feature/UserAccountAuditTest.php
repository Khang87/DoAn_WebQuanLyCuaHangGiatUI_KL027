<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\NhatKyHeThong;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TenQuyen')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
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

        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
            $table->primary(['VaiTroID', 'QuyenID']);
        });

        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('TinNhanID')->nullable();
        });

        Schema::create('TinNhanAn', function (Blueprint $table): void {
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('TinNhanID');
        });

        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->unsignedInteger('NguoiGuiID');
            $table->unsignedInteger('NguoiNhanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('NoiDung')->default('');
            $table->string('TrangThai')->default('Đã gửi');
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('TrangThai');
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->string('TrangThai');
        });

        Schema::create('push_device_tokens', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('TaiKhoanID');
            $table->string('token');
        });

        Schema::create('LichSuThayDoiHoaDon', function (Blueprint $table): void {
            $table->increments('LichSuID');
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('HoaDonID')->nullable();
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
        $this->assertArrayNotHasKey('password', $creation->DuLieuMoi);
        $this->assertArrayNotHasKey('remember_token', $creation->DuLieuMoi);
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
        $this->assertArrayNotHasKey('password', $passwordChange->DuLieuCu);
        $this->assertArrayNotHasKey('password', $passwordChange->DuLieuMoi);
        $this->assertArrayNotHasKey('remember_token', $passwordChange->DuLieuCu);
        $this->assertArrayNotHasKey('remember_token', $passwordChange->DuLieuMoi);
        $this->assertSame(3, NhatKyHeThong::query()->count());
    }

    public function test_deleting_account_physically_removes_it_and_its_role_links(): void
    {
        $user = User::query()->create([
            'TenDangNhap' => 'removable@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'removable@example.com',
            'TrangThai' => 'Khóa',
        ]);
        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'VaiTroID' => 1,
        ]);

        $result = app(UserService::class)->delete($user);

        $this->assertNull($result['message']);
        $this->assertSame([], $result['orders']);
        $this->assertDatabaseMissing('TaiKhoan', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('TaiKhoan_VaiTro', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseHas('NhatKyHeThong', [
            'TaiKhoanID' => auth()->id(),
            'HanhDong' => 'Xóa tài khoản',
            'BanGhiID' => $user->TaiKhoanID,
        ]);
    }

    public function test_account_deletion_is_blocked_by_unfinished_orders_and_returns_order_details(): void
    {
        $customer = DB::table('KhachHang')->insertGetId(['HoTen' => 'Khách có đơn chưa xong']);
        $user = User::query()->create([
            'TenDangNhap' => 'unfinished@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'unfinished@example.com',
            'KhachHangID' => $customer,
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 101,
            'MaDonHang' => 'DH00101',
            'KhachHangID' => $customer,
            'TrangThai' => OrderStatus::Washing->value,
        ]);
        DB::table('HoaDon')->insert([
            'DonHangID' => 101,
            'TrangThai' => InvoiceStatus::Paid->value,
        ]);

        $result = app(UserService::class)->delete($user);

        $this->assertSame(
            'Không thể xóa tài khoản vì còn đơn hàng chưa hoàn tất hoặc chưa thanh toán.',
            $result['message'],
        );
        $this->assertSame([[
            'id' => 101,
            'code' => 'DH00101',
            'status' => OrderStatus::Washing->value,
            'payment_status' => InvoiceStatus::Paid->value,
        ]], $result['orders']);
        $this->assertDatabaseHas('TaiKhoan', ['TaiKhoanID' => $user->TaiKhoanID]);
    }

    public function test_account_deletion_is_blocked_by_a_delivered_but_unpaid_order(): void
    {
        $customer = DB::table('KhachHang')->insertGetId(['HoTen' => 'Khách chưa thanh toán']);
        $user = User::query()->create([
            'TenDangNhap' => 'unpaid@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'unpaid@example.com',
            'KhachHangID' => $customer,
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 102,
            'MaDonHang' => 'DH00102',
            'KhachHangID' => $customer,
            'TrangThai' => OrderStatus::Delivered->value,
        ]);
        DB::table('HoaDon')->insert([
            'DonHangID' => 102,
            'TrangThai' => InvoiceStatus::Unpaid->value,
        ]);

        $result = app(UserService::class)->delete($user);

        $this->assertCount(1, $result['orders']);
        $this->assertSame('DH00102', $result['orders'][0]['code']);
        $this->assertDatabaseHas('TaiKhoan', ['TaiKhoanID' => $user->TaiKhoanID]);
    }

    public function test_staff_account_deletion_is_blocked_by_an_unfinished_assigned_order(): void
    {
        $user = User::query()->create([
            'TenDangNhap' => 'assigned-staff@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'assigned-staff@example.com',
            'NhanVienID' => 9,
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 105,
            'MaDonHang' => 'DH00105',
            'NhanVienID' => 9,
            'TrangThai' => OrderStatus::Washing->value,
        ]);

        $result = app(UserService::class)->delete($user);

        $this->assertSame(105, $result['orders'][0]['id']);
        $this->assertDatabaseHas('TaiKhoan', ['TaiKhoanID' => $user->TaiKhoanID]);
    }

    public function test_blocking_orders_are_shown_as_direct_links_after_an_admin_attempts_deletion(): void
    {
        $customer = DB::table('KhachHang')->insertGetId(['HoTen' => 'Khách có đơn cần xử lý']);
        $user = User::query()->create([
            'TenDangNhap' => 'blocked@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'blocked@example.com',
            'KhachHangID' => $customer,
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 104,
            'MaDonHang' => 'DH00104',
            'KhachHangID' => $customer,
            'TrangThai' => OrderStatus::Pending->value,
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 105,
            'MaDonHang' => 'DH00105',
            'KhachHangID' => $customer,
            'TrangThai' => OrderStatus::Washing->value,
        ]);
        DB::table('HoaDon')->insert([
            'DonHangID' => 105,
            'TrangThai' => InvoiceStatus::Paid->value,
        ]);
        $actor = User::query()->create([
            'TenDangNhap' => 'admin@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'admin@example.com',
            'TrangThai' => 'Hoạt động',
        ]);
        Gate::before(fn (): bool => true);

        $this->withoutMiddleware(EnsureUserHasPermission::class)
            ->actingAs($actor)
            ->delete(route('accounts.destroy', $user->TaiKhoanID))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('blocking_orders.0.id', 104)
            ->assertSessionHas('blocking_orders.0.payment_status', InvoiceStatus::Unpaid->value)
            ->assertSessionHas('blocking_orders.1.payment_status', InvoiceStatus::Paid->value);

        $this->get(route('accounts.index'))
            ->assertOk()
            ->assertSee(route('orders.show', 104), false)
            ->assertSee('DH00104')
            ->assertSee(OrderStatus::Pending->value)
            ->assertSee(InvoiceStatus::Unpaid->value)
            ->assertSee(route('orders.show', 105), false)
            ->assertSee('DH00105')
            ->assertSee(OrderStatus::Washing->value)
            ->assertSee(InvoiceStatus::Paid->value);
    }

    public function test_deleting_account_removes_its_database_references_when_orders_are_settled(): void
    {
        $customer = DB::table('KhachHang')->insertGetId(['HoTen' => 'Khách có đơn đã xử lý']);
        $user = User::query()->create([
            'TenDangNhap' => 'settled@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'settled@example.com',
            'KhachHangID' => $customer,
            'TrangThai' => 'Khóa',
        ]);
        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'VaiTroID' => 1,
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 103,
            'MaDonHang' => 'DH00103',
            'KhachHangID' => $customer,
            'TrangThai' => OrderStatus::Delivered->value,
        ]);
        DB::table('HoaDon')->insert([
            'DonHangID' => 103,
            'TrangThai' => InvoiceStatus::Paid->value,
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 106,
            'MaDonHang' => 'DH00106',
            'KhachHangID' => $customer,
            'TrangThai' => OrderStatus::Cancelled->value,
        ]);
        DB::table('TinNhan')->insert([
            ['TinNhanID' => 201, 'NguoiGuiID' => $user->TaiKhoanID, 'NguoiNhanID' => auth()->id()],
            ['TinNhanID' => 202, 'NguoiGuiID' => auth()->id(), 'NguoiNhanID' => $user->TaiKhoanID],
        ]);
        DB::table('TinNhanAn')->insert([
            ['TaiKhoanID' => $user->TaiKhoanID, 'TinNhanID' => 201],
            ['TaiKhoanID' => auth()->id(), 'TinNhanID' => 202],
        ]);
        DB::table('ThongBao')->insert([
            ['TaiKhoanID' => $user->TaiKhoanID, 'TinNhanID' => 201],
            ['TaiKhoanID' => auth()->id(), 'TinNhanID' => 202],
        ]);
        DB::table('LichSuThayDoiHoaDon')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'HoaDonID' => 1,
        ]);
        DB::table('NhatKyHeThong')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'HanhDong' => 'Đăng nhập',
            'BangDuLieu' => 'TaiKhoan',
            'ThoiGian' => now(),
        ]);
        DB::table('push_device_tokens')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'token' => 'device-token',
        ]);

        $result = app(UserService::class)->delete($user);

        $this->assertNull($result['message']);
        $this->assertSame([], $result['orders']);
        $this->assertDatabaseMissing('TaiKhoan', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('TaiKhoan_VaiTro', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('TinNhan', ['NguoiGuiID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('TinNhan', ['NguoiNhanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('TinNhanAn', ['TinNhanID' => 201]);
        $this->assertDatabaseMissing('TinNhanAn', ['TinNhanID' => 202]);
        $this->assertDatabaseMissing('ThongBao', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('ThongBao', ['TinNhanID' => 202]);
        $this->assertDatabaseHas('ThongBao', ['TaiKhoanID' => auth()->id(), 'TinNhanID' => null]);
        $this->assertDatabaseMissing('LichSuThayDoiHoaDon', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('NhatKyHeThong', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('push_device_tokens', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseHas('DonHang', ['DonHangID' => 103]);
        $this->assertDatabaseHas('DonHang', ['DonHangID' => 106]);
        $this->assertDatabaseHas('KhachHang', ['KhachHangID' => $customer]);
    }

    public function test_an_account_cannot_hard_delete_itself(): void
    {
        $account = auth()->user();

        $result = app(UserService::class)->delete($account);

        $this->assertSame('Không thể xóa tài khoản đang đăng nhập.', $result['message']);
        $this->assertDatabaseHas('TaiKhoan', ['TaiKhoanID' => $account->TaiKhoanID]);
    }

    public function test_account_with_historical_references_can_be_deleted_and_its_related_history_is_removed(): void
    {
        $user = User::query()->create([
            'TenDangNhap' => 'referenced@example.com',
            'MatKhau' => 'not-used',
            'Email' => 'referenced@example.com',
            'TrangThai' => 'Khóa',
        ]);
        DB::table('NhatKyHeThong')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'HanhDong' => 'Đăng nhập',
            'BangDuLieu' => 'TaiKhoan',
            'ThoiGian' => now(),
        ]);

        $result = app(UserService::class)->delete($user);

        $this->assertNull($result['message']);
        $this->assertDatabaseMissing('TaiKhoan', ['TaiKhoanID' => $user->TaiKhoanID]);
        $this->assertDatabaseMissing('NhatKyHeThong', ['TaiKhoanID' => $user->TaiKhoanID]);
    }
}
