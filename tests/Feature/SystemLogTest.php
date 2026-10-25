<?php

namespace Tests\Feature;

use App\Models\NhatKyHeThong;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemLogTest extends TestCase
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
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('AvatarURL')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('AvatarUrl')->nullable();
        });

        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro');
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
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
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('LoaiThongBao')->nullable();
            $table->string('TieuDe');
            $table->string('NoiDung');
            $table->dateTime('ThoiGianGui')->useCurrent();
            $table->boolean('DaDoc')->default(false);
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
        });

        DB::table('DonHang')->insert(['DonHangID' => 24, 'MaDonHang' => 'DH0024']);

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedBigInteger('BanGhiID')->nullable();
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian')->useCurrent();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
    }

    public function test_account_dropdown_filter_returns_only_logs_for_selected_account(): void
    {
        $owner = $this->createAccount('owner');
        $this->assignRole($owner, 'Owner');
        $selectedAccount = $this->createAccount('selected-account');
        $otherAccount = $this->createAccount('other-account');

        $selectedLog = $this->createLog($selectedAccount->TaiKhoanID, 'Selected account action');
        $this->createLog($otherAccount->TaiKhoanID, 'Other account action');

        $response = $this->actingAs($owner)
            ->get(route('admin.system-logs.index', ['TaiKhoanID' => $selectedAccount->TaiKhoanID]));

        $response->assertOk()
            ->assertViewHas('logs', function ($logs) use ($selectedLog): bool {
                return $logs->total() === 1
                    && $logs->items()[0]->NhatKyID === $selectedLog->NhatKyID;
            })
            ->assertSee('selected-account')
            ->assertDontSee('Other account action');
    }

    public function test_null_account_log_is_shown_as_system(): void
    {
        $owner = $this->createAccount('owner');
        $this->assignRole($owner, 'Owner');
        $this->createLog(null, 'Trigger-generated action');

        $this->actingAs($owner)
            ->get(route('admin.system-logs.index'))
            ->assertOk()
            ->assertSee('Hệ thống');
    }

    public function test_log_row_shows_target_record_code_and_one_line_timestamp(): void
    {
        $owner = $this->createAccount('owner');
        $this->assignRole($owner, 'Owner');
        NhatKyHeThong::query()->create([
            'TaiKhoanID' => $owner->TaiKhoanID,
            'HanhDong' => 'Chuyển trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => 24,
            'ThoiGian' => '2026-10-24 19:04:57',
        ]);

        $html = $this->actingAs($owner)
            ->get(route('admin.system-logs.index'))
            ->assertOk()
            ->getContent();

        preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $tableBody);
        $this->assertNotEmpty($tableBody);
        $this->assertStringContainsString('DH0024', $tableBody[1]);
        $this->assertStringContainsString('24/10/2026 19:04:57', $tableBody[1]);
        $this->assertStringNotContainsString('font-monospace', $html);
        $this->assertStringContainsString('bg-success-subtle text-success-emphasis', $tableBody[1]);
    }

    public function test_dropdown_and_account_column_show_linked_names(): void
    {
        $owner = $this->createAccount('owner');
        $this->assignRole($owner, 'Owner');
        $account = $this->createAccount('dropdown-account');
        $staffAccount = $this->createAccountWithStaff('login-account', 'Nhân viên kế toán');
        $mobileAccount = $this->createAccountWithCustomer('auth-0be30498-4fa2-4a4b-8e47-473b641ca000', 'Khoa Võ');

        $response = $this->actingAs($owner)->get(route('admin.system-logs.index'));

        $response->assertOk()
            ->assertViewHas('accounts', function ($accounts) use ($account): bool {
                $dropdownAccount = $accounts->firstWhere('TaiKhoanID', $account->TaiKhoanID);

                return $dropdownAccount !== null
                    && $dropdownAccount->TenDangNhap === 'dropdown-account'
                    && array_keys($dropdownAccount->getAttributes()) === ['TaiKhoanID', 'TenDangNhap', 'NhanVienID', 'KhachHangID', 'AvatarURL'];
            })
            ->assertSee('dropdown-account')
            ->assertSee('Nhân viên kế toán (login-account)')
            ->assertSee('Khoa Võ (auth-0be30498…)')
            ->assertDontSee('auth-0be30498-4fa2-4a4b-8e47-473b641ca000)')
            ->assertSee('name="TaiKhoanID"', false);

        $log = $this->createLog($staffAccount->TaiKhoanID, 'Account display action');

        $this->actingAs($owner)
            ->get(route('admin.system-logs.index', ['TaiKhoanID' => $staffAccount->TaiKhoanID]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->items()[0]->NhatKyID === $log->NhatKyID)
            ->assertSee('Nhân viên kế toán')
            ->assertDontSee('login-account (#');
    }

    public function test_staff_is_forbidden_from_accessing_system_logs(): void
    {
        $staff = $this->createAccount('staff');
        $this->assignRole($staff, 'Nhân viên');

        $this->actingAs($staff)
            ->getJson(route('admin.system-logs.index'))
            ->assertForbidden();
    }

    private function createAccount(string $username): User
    {
        return User::query()->create([
            'TenDangNhap' => $username,
            'MatKhau' => 'test-password',
            'TrangThai' => 'Hoạt động',
        ]);
    }

    private function createAccountWithStaff(string $username, string $staffName): User
    {
        $nhanVienId = DB::table('NhanVien')->insertGetId(['HoTen' => $staffName], 'NhanVienID');
        $account = $this->createAccount($username);
        $account->forceFill(['NhanVienID' => $nhanVienId])->save();

        return $account->fresh();
    }

    private function createAccountWithCustomer(string $username, string $customerName): User
    {
        $khachHangId = DB::table('KhachHang')->insertGetId(['HoTen' => $customerName], 'KhachHangID');
        $account = $this->createAccount($username);
        $account->forceFill(['KhachHangID' => $khachHangId])->save();

        return $account->fresh();
    }

    private function assignRole(User $user, string $roleName): void
    {
        $roleId = DB::table('VaiTro')->insertGetId([
            'TenVaiTro' => $roleName,
            'TrangThai' => 'Hoạt động',
        ], 'VaiTroID');

        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'VaiTroID' => $roleId,
        ]);
    }

    private function createLog(?int $accountId, string $action): NhatKyHeThong
    {
        return NhatKyHeThong::query()->create([
            'TaiKhoanID' => $accountId,
            'HanhDong' => $action,
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => 24,
            'ThoiGian' => now(),
        ]);
    }
}
