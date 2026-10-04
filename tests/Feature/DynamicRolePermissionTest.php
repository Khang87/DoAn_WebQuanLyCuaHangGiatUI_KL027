<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Models\Quyen;
use App\Models\User;
use App\Models\VaiTro;
use App\Services\RoleService;
use App\Support\PermissionCache;
use Database\Seeders\RbacCatalogSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicRolePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('MatKhau')->nullable();
            $table->string('Email')->nullable()->unique();
            $table->string('SoDienThoai')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai');
            $table->dateTime('NgayTao')->nullable();
        });
        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen');
            $table->string('Email')->nullable();
            $table->string('SoDienThoai')->unique();
            $table->string('ChucDanh')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('Email')->nullable();
            $table->string('SoDienThoai')->nullable()->unique();
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro')->unique();
            $table->string('MoTa')->nullable();
            $table->string('TrangThai');
        });
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen')->unique();
            $table->string('TenQuyen');
            $table->string('MoTa')->nullable();
            $table->string('TrangThai');
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
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID')->nullable();
            $table->text('DuLieuCu')->nullable();
            $table->text('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian')->nullable();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
    }

    public function test_custom_role_slug_uses_a_developer_registered_permission(): void
    {
        $role = $this->createRole('Kế toán');
        $permission = Quyen::query()->create([
            'MaQuyen' => 'ACCOUNTING_VIEW',
            'TenQuyen' => 'Xem chức năng kế toán',
            'TrangThai' => 'Hoạt động',
        ]);
        $role->quyens()->attach($permission->getKey());
        $user = $this->createAccount($role);

        $this->assertSame('ke-toan', $user->roleSlug());
        $this->assertTrue($user->canPermission('accounting.view'));
        $this->assertTrue(Gate::forUser($user)->allows('accounting.view'));
        $this->assertFalse($user->canPermission('payroll.view'));
    }

    public function test_role_service_creates_and_deactivates_a_custom_role_instead_of_breaking_account_links(): void
    {
        $service = app(RoleService::class);
        $role = $service->createRole([
            'TenVaiTro' => 'Kế toán',
            'MoTa' => 'Quản lý nghiệp vụ kế toán.',
        ]);
        $user = $this->createAccount($role);

        $this->assertSame('deactivated', $service->deleteRole($role));
        $this->assertSame('Ngừng hoạt động', $role->fresh()->TrangThai);

        PermissionCache::forgetAll();
        $this->assertSame([], $user->fresh()->roleNames());
    }

    public function test_owner_can_create_roles_but_cannot_create_permissions_from_the_ui(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));

        $this->actingAs($owner)
            ->post(route('roles.store'), [
                'TenVaiTro' => 'Kế toán',
                'MoTa' => 'Theo dõi tài chính.',
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('VaiTro', ['TenVaiTro' => 'Kế toán']);

        $this->actingAs($owner)
            ->post('/permissions', [
                'MaQuyen' => 'accounting.view',
                'TenQuyen' => 'Xem chức năng kế toán',
                'TrangThai' => 'Hoạt động',
                'return_to' => 'roles',
            ])
            ->assertStatus(405);

        $this->assertDatabaseMissing('Quyen', ['MaQuyen' => 'ACCOUNTING_VIEW']);
        $this->actingAs($owner)->get('/permissions/create')->assertNotFound();

        $this->actingAs($owner)
            ->get(route('permissions.index'))
            ->assertOk()
            ->assertDontSee('Thêm quyền')
            ->assertDontSee('Sửa quyền');
    }

    public function test_account_creation_form_shows_only_the_profile_matching_the_selected_role(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));

        $response = $this->actingAs($owner)
            ->get(route('accounts.create'))
            ->assertOk();
        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/<div[^>]*id="employee-profile-field"[^>]*>/', $content);
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*id="employee-profile-field"[^>]*\shidden(?:\s|>)/', $content);
        $this->assertMatchesRegularExpression('/<div[^>]*id="customer-profile-field"[^>]*\shidden(?:\s|>)/', $content);
        $this->assertMatchesRegularExpression('/<select[^>]*id="NhanVienID"[^>]*\srequired(?:\s|>)/', $content);
        $this->assertMatchesRegularExpression('/<select[^>]*id="KhachHangID"[^>]*\sdisabled(?:\s|>)/', $content);
        $this->assertStringContainsString('customerRole = "khach-hang"', $content);
        $this->assertStringContainsString('Thêm nhanh hồ sơ nhân viên', $content);
        $this->assertStringContainsString(route('accounts.quick-create-employee'), $content);
        $this->assertStringNotContainsString('name="phone"', $content);
        $this->assertMatchesRegularExpression('/<input[^>]*id="profilePhone"[^>]*readonly/', $content);
    }

    public function test_account_form_only_lists_profiles_without_an_account_and_populates_profile_phone_data(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $linkedEmployee = NhanVien::query()->create([
            'HoTen' => 'Nhân viên đã liên kết',
            'SoDienThoai' => '0901000001',
            'TrangThai' => 'Hoạt động',
        ]);
        User::query()->create([
            'TenDangNhap' => 'linked-employee@example.test',
            'Email' => 'linked-employee@example.test',
            'NhanVienID' => $linkedEmployee->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        $availableEmployee = NhanVien::query()->create([
            'HoTen' => 'Nhân viên chưa liên kết',
            'SoDienThoai' => '0901000002',
            'TrangThai' => 'Hoạt động',
        ]);

        $linkedCustomer = KhachHang::query()->create([
            'HoTen' => 'Khách đã liên kết',
            'SoDienThoai' => '0902000001',
            'TrangThai' => 'Hoạt động',
        ]);
        User::query()->create([
            'TenDangNhap' => 'linked-customer@example.test',
            'Email' => 'linked-customer@example.test',
            'KhachHangID' => $linkedCustomer->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        $availableCustomer = KhachHang::query()->create([
            'HoTen' => 'Khách chưa liên kết',
            'SoDienThoai' => '0902000002',
            'TrangThai' => 'Hoạt động',
        ]);

        $response = $this->actingAs($owner)->get(route('accounts.create'))->assertOk();

        $response->assertSee('value="'.$availableEmployee->getKey().'" data-phone="0901000002"', false)
            ->assertDontSee('value="'.$linkedEmployee->getKey().'" data-phone="0901000001"', false)
            ->assertSee('value="'.$availableCustomer->getKey().'" data-phone="0902000002"', false)
            ->assertDontSee('value="'.$linkedCustomer->getKey().'" data-phone="0902000001"', false)
            ->assertDontSee('Không chọn nhân viên')
            ->assertDontSee('Không chọn khách hàng');
    }

    public function test_account_creation_rejects_a_profile_that_already_has_an_account(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $this->createRole('Nhân viên');
        $employee = NhanVien::query()->create([
            'HoTen' => 'Nhân viên đã có tài khoản',
            'SoDienThoai' => '0903000001',
            'TrangThai' => 'Hoạt động',
        ]);
        User::query()->create([
            'TenDangNhap' => 'existing-staff@example.test',
            'Email' => 'existing-staff@example.test',
            'NhanVienID' => $employee->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->from(route('accounts.create'))
            ->post(route('accounts.store'), [
                'email' => 'duplicate-profile@example.test',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
                'role' => 'nhan-vien',
                'NhanVienID' => $employee->getKey(),
            ])
            ->assertRedirect(route('accounts.create'))
            ->assertSessionHasErrors('NhanVienID');

        $this->assertDatabaseMissing('TaiKhoan', ['Email' => 'duplicate-profile@example.test']);
    }

    public function test_owner_can_quick_create_an_active_employee_profile_for_account_creation(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));

        $response = $this->actingAs($owner)
            ->postJson(route('accounts.quick-create-employee'), [
                'HoTen' => 'Nhân viên tạo nhanh',
                'SoDienThoai' => '0901234567',
                'ChucDanh' => 'Chi nhánh Quận 1',
            ])
            ->assertCreated()
            ->assertJsonPath('employee.HoTen', 'Nhân viên tạo nhanh')
            ->assertJsonPath('employee.SoDienThoai', '0901234567');

        $employeeId = $response->json('employee.NhanVienID');
        $this->assertDatabaseHas('NhanVien', [
            'NhanVienID' => $employeeId,
            'HoTen' => 'Nhân viên tạo nhanh',
            'SoDienThoai' => '0901234567',
            'ChucDanh' => 'Chi nhánh Quận 1',
            'TrangThai' => 'Hoạt động',
        ]);
    }

    public function test_quick_employee_creation_requires_valid_unique_name_and_phone(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        NhanVien::query()->create([
            'HoTen' => 'Nhân viên hiện có',
            'SoDienThoai' => '0901234567',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->postJson(route('accounts.quick-create-employee'), [
                'HoTen' => '',
                'SoDienThoai' => '0901234567',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['HoTen', 'SoDienThoai']);

        $this->assertDatabaseCount('NhanVien', 1);
    }

    public function test_account_creation_links_internal_roles_to_employee_profiles_only(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $this->createRole('Kế toán');
        $employee = NhanVien::query()->create([
            'HoTen' => 'Nhân viên thử nghiệm',
            'SoDienThoai' => '0901234567',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->post(route('accounts.store'), [
                'email' => 'staff-new@example.test',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
                'role' => 'ke-toan',
                'NhanVienID' => $employee->getKey(),
            ])
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('TaiKhoan', [
            'Email' => 'staff-new@example.test',
            'NhanVienID' => $employee->getKey(),
            'KhachHangID' => null,
            'SoDienThoai' => null,
        ]);
        $this->assertSame('0901234567', User::query()->where('Email', 'staff-new@example.test')->firstOrFail()->phone);
    }

    public function test_account_creation_links_customer_role_to_customer_profile_only(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $this->createRole('Khách hàng');
        $customer = KhachHang::query()->create([
            'HoTen' => 'Khách hàng thử nghiệm',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->post(route('accounts.store'), [
                'email' => 'customer-new@example.test',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
                'role' => 'khach-hang',
                'KhachHangID' => $customer->getKey(),
            ])
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('TaiKhoan', [
            'Email' => 'customer-new@example.test',
            'NhanVienID' => null,
            'KhachHangID' => $customer->getKey(),
        ]);
    }

    public function test_account_creation_rejects_a_profile_from_the_wrong_role_type(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $this->createRole('Nhân viên');
        $this->createRole('Khách hàng');
        $employee = NhanVien::query()->create([
            'HoTen' => 'Nhân viên thử nghiệm',
            'SoDienThoai' => '0904000001',
            'TrangThai' => 'Hoạt động',
        ]);
        $customer = KhachHang::query()->create([
            'HoTen' => 'Khách hàng thử nghiệm',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->from(route('accounts.create'))
            ->post(route('accounts.store'), [
                'email' => 'wrong-internal-profile@example.test',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
                'role' => 'nhan-vien',
                'KhachHangID' => $customer->getKey(),
            ])
            ->assertRedirect(route('accounts.create'))
            ->assertSessionHasErrors('NhanVienID');

        $this->actingAs($owner)
            ->from(route('accounts.create'))
            ->post(route('accounts.store'), [
                'email' => 'wrong-customer-profile@example.test',
                'password' => 'Password123!@#',
                'password_confirmation' => 'Password123!@#',
                'role' => 'khach-hang',
                'NhanVienID' => $employee->getKey(),
            ])
            ->assertRedirect(route('accounts.create'))
            ->assertSessionHasErrors('KhachHangID');

        $this->assertDatabaseMissing('TaiKhoan', ['Email' => 'wrong-internal-profile@example.test']);
        $this->assertDatabaseMissing('TaiKhoan', ['Email' => 'wrong-customer-profile@example.test']);
    }

    public function test_role_cards_show_assigned_permissions_and_refresh_after_role_update(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $role = $this->createRole('Kế toán');
        $member = $this->createAccount($role);
        $reportPermission = Quyen::query()->create([
            'MaQuyen' => 'REPORT_VIEW',
            'TenQuyen' => 'Xem báo cáo',
            'TrangThai' => 'Hoạt động',
        ]);
        $invoicePermission = Quyen::query()->create([
            'MaQuyen' => 'INVOICE_VIEW',
            'TenQuyen' => 'Xem hóa đơn',
            'TrangThai' => 'Hoạt động',
        ]);
        $role->quyens()->attach([$reportPermission->getKey(), $invoicePermission->getKey()]);

        $response = $this->actingAs($owner)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Quản lý nhóm quyền')
            ->assertSee('Quyền có thể cấp')
            ->assertSee('Tài khoản đã được phân nhóm')
            ->assertSee('Kế toán')
            ->assertSee('Xem báo cáo')
            ->assertSee('Xem hóa đơn');
        $response->assertViewHas('metrics', [
            'roles' => 2,
            'permissions' => 2,
            'assignedAccounts' => 2,
        ]);
        $response->assertSee('data-permission-role="'.$role->getKey().'"', false)
            ->assertDontSee('rbacMatrixForm');

        $this->actingAs($owner)
            ->put(route('permissions.roles.update', $invoicePermission->getKey()), [
                'vai_tro_ids' => [],
            ])
            ->assertRedirect(route('roles.index'));

        $this->actingAs($owner)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Quản lý nhóm quyền')
            ->assertSee('Kế toán')
            ->assertSee('Xem báo cáo')
            ->assertSee('data-permission-role="'.$role->getKey().'"', false)
            ->assertSee('data-permission-id="'.$reportPermission->getKey().'"', false)
            ->assertDontSee('data-permission-id="'.$invoicePermission->getKey().'"', false);

        $this->assertDatabaseHas('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $reportPermission->getKey(),
        ]);
        $this->assertDatabaseMissing('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $invoicePermission->getKey(),
        ]);
        $this->assertDatabaseHas('TaiKhoan_VaiTro', [
            'TaiKhoanID' => $member->getKey(),
            'VaiTroID' => $role->getKey(),
        ]);
    }

    public function test_role_detail_updates_assigned_permissions_and_preserves_inactive_assignments(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $role = $this->createRole('Kế toán');
        $currentPermission = Quyen::query()->create([
            'MaQuyen' => 'ACCOUNTING_VIEW',
            'TenQuyen' => 'Xem chức năng kế toán',
            'TrangThai' => 'Hoạt động',
        ]);
        $newPermission = Quyen::query()->create([
            'MaQuyen' => 'REPORT_VIEW',
            'TenQuyen' => 'Xem báo cáo',
            'TrangThai' => 'Hoạt động',
        ]);
        $inactivePermission = Quyen::query()->create([
            'MaQuyen' => 'LEGACY_VIEW',
            'TenQuyen' => 'Quyền cũ',
            'TrangThai' => 'Ngừng hoạt động',
        ]);
        $role->quyens()->attach([$currentPermission->getKey(), $inactivePermission->getKey()]);

        $this->actingAs($owner)
            ->get(route('roles.edit', $role->getKey()))
            ->assertOk()
            ->assertSee('Quyền của nhóm')
            ->assertSee('Những người thuộc nhóm này')
            ->assertSee('Xem chức năng kế toán');

        $this->actingAs($owner)
            ->put(route('roles.permissions.update', $role->getKey()), [
                'permission_ids' => [$newPermission->getKey()],
            ])
            ->assertRedirect(route('roles.edit', $role->getKey()));

        $this->assertDatabaseMissing('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $currentPermission->getKey(),
        ]);
        $this->assertDatabaseHas('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $newPermission->getKey(),
        ]);
        $this->assertDatabaseHas('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $inactivePermission->getKey(),
        ]);
    }

    public function test_role_detail_cannot_grant_owner_only_permissions_or_change_owner_permissions(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $role = $this->createRole('Kế toán');
        $ownerOnlyPermission = Quyen::query()->create([
            'MaQuyen' => 'ACCOUNT_MANAGE',
            'TenQuyen' => 'Quản lý tài khoản',
            'TrangThai' => 'Hoạt động',
        ]);
        $ownerRole = VaiTro::query()->where('TenVaiTro', 'Chủ cửa hàng')->firstOrFail();

        $this->actingAs($owner)
            ->put(route('roles.permissions.update', $role->getKey()), [
                'permission_ids' => [$ownerOnlyPermission->getKey()],
            ])
            ->assertSessionHasErrors('permission_ids');

        $this->assertDatabaseMissing('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $ownerOnlyPermission->getKey(),
        ]);

        $this->actingAs($owner)
            ->put(route('roles.permissions.update', $ownerRole->getKey()), [
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_permission_not_defined_by_the_developer_cannot_be_assigned_to_a_role(): void
    {
        $role = $this->createRole('Kế toán');
        $permission = Quyen::query()->create([
            'MaQuyen' => 'PAYROLL_VIEW',
            'TenQuyen' => 'Xem bảng lương',
            'TrangThai' => 'Hoạt động',
        ]);

        try {
            app(RoleService::class)->syncRolePermissions($role, [$permission->getKey()]);
            $this->fail('An unregistered permission must not be assignable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('permission_ids', $exception->errors());
        }

        $this->assertDatabaseMissing('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $permission->getKey(),
        ]);
    }

    public function test_role_ui_updates_description_task_permissions_and_account_assignment(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $role = $this->createRole('Kế toán');
        $permission = Quyen::query()->create([
            'MaQuyen' => 'ACCOUNTING_VIEW',
            'TenQuyen' => 'Xem chức năng kế toán',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->put(route('roles.update-role', $role->getKey()), [
                'TenVaiTro' => 'Kế toán',
                'MoTa' => 'Theo dõi hóa đơn và báo cáo.',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertSame('Theo dõi hóa đơn và báo cáo.', $role->fresh()->MoTa);

        $this->actingAs($owner)
            ->put(route('permissions.roles.update', $permission->getKey()), [
                'vai_tro_ids' => [$role->getKey()],
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('VaiTro_Quyen', [
            'VaiTroID' => $role->getKey(),
            'QuyenID' => $permission->getKey(),
        ]);

        $staffRole = $this->createRole('Nhân viên');
        $account = $this->createAccount($staffRole);
        $this->actingAs($owner)
            ->post(route('accounts.update-role', $account->getKey()), [
                'vai_tro_ids' => [$role->getKey()],
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('TaiKhoan_VaiTro', [
            'TaiKhoanID' => $account->getKey(),
            'VaiTroID' => $role->getKey(),
        ]);
        $this->assertDatabaseMissing('TaiKhoan_VaiTro', [
            'TaiKhoanID' => $account->getKey(),
            'VaiTroID' => $staffRole->getKey(),
        ]);
    }

    public function test_role_detail_lists_member_accounts_with_names_contacts_and_exact_count(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $role = $this->createRole('Nhân viên bán hàng');
        $employee = NhanVien::query()->create([
            'HoTen' => 'Nguyễn Văn An',
            'SoDienThoai' => '0904000002',
            'Email' => 'an@example.test',
        ]);
        $employeeAccount = User::query()->create([
            'TenDangNhap' => 'vanan',
            'Email' => 'account-an@example.test',
            'NhanVienID' => $employee->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        $customer = KhachHang::query()->create([
            'HoTen' => 'Trần Thị Bình',
            'Email' => 'binh@example.test',
        ]);
        $customerAccount = User::query()->create([
            'TenDangNhap' => 'thibinh',
            'Email' => 'account-binh@example.test',
            'KhachHangID' => $customer->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        $role->taiKhoans()->attach([$employeeAccount->getKey(), $customerAccount->getKey()]);

        $this->actingAs($owner)
            ->get(route('roles.edit', $role->getKey()))
            ->assertOk()
            ->assertSee('2 tài khoản được phân vào nhóm.')
            ->assertSee('Nguyễn Văn An')
            ->assertSee('account-an@example.test')
            ->assertSee('vanan')
            ->assertSee('N')
            ->assertSee('Trần Thị Bình')
            ->assertSee('account-binh@example.test')
            ->assertSee('T')
            ->assertSee('Thêm người')
            ->assertSee('data-confirm-icon="warning"', false)
            ->assertSee('mất quyền truy cập được cấp thông qua nhóm', false)
            ->assertSee('Gỡ');
    }

    public function test_role_member_can_be_added_and_removed_through_role_detail_actions(): void
    {
        $owner = $this->createAccount($this->createRole('Chủ cửa hàng'));
        $role = $this->createRole('Kế toán');
        $member = User::query()->create([
            'TenDangNhap' => 'accountant',
            'Email' => 'accountant@example.test',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($owner)
            ->post(route('roles.members.store', $role->getKey()), [
                'TaiKhoanID' => $member->getKey(),
            ])
            ->assertRedirect(route('roles.edit', $role->getKey()))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('TaiKhoan_VaiTro', [
            'TaiKhoanID' => $member->getKey(),
            'VaiTroID' => $role->getKey(),
        ]);

        $this->actingAs($owner)
            ->get(route('roles.edit', $role->getKey()))
            ->assertOk()
            ->assertSee('1 tài khoản được phân vào nhóm.');

        $this->actingAs($owner)
            ->delete(route('roles.members.destroy', [$role->getKey(), $member->getKey()]))
            ->assertRedirect(route('roles.edit', $role->getKey()))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('TaiKhoan_VaiTro', [
            'TaiKhoanID' => $member->getKey(),
            'VaiTroID' => $role->getKey(),
        ]);

        $this->actingAs($owner)
            ->get(route('roles.edit', $role->getKey()))
            ->assertOk()
            ->assertSee('0 tài khoản được phân vào nhóm.');
    }

    public function test_member_actions_require_role_management_permission_and_protect_owner_role(): void
    {
        $manager = $this->createAccount($this->createRole('Quản lý'));
        $account = $this->createAccount($this->createRole('Nhân viên'));
        $role = $this->createRole('Kế toán');
        $ownerRole = $this->createRole('Chủ cửa hàng');

        $this->actingAs($manager)
            ->post(route('roles.members.store', $role->getKey()), [
                'TaiKhoanID' => $account->getKey(),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('TaiKhoan_VaiTro', [
            'TaiKhoanID' => $account->getKey(),
            'VaiTroID' => $role->getKey(),
        ]);

        $owner = $this->createAccount($ownerRole);
        $this->actingAs($owner)
            ->post(route('roles.members.store', $ownerRole->getKey()), [
                'TaiKhoanID' => $account->getKey(),
            ])
            ->assertForbidden();
    }

    public function test_unused_custom_roles_can_be_deleted(): void
    {
        $role = $this->createRole('Kế toán');

        $this->assertSame('deleted', app(RoleService::class)->deleteRole($role));
        $this->assertDatabaseMissing('VaiTro', ['VaiTroID' => $role->getKey()]);
    }

    public function test_catalog_seeder_reuses_legacy_permission_codes_and_creates_an_accountant_role(): void
    {
        app(RbacCatalogSeeder::class)->run();

        $this->assertDatabaseHas('Quyen', ['MaQuyen' => 'ORDER_VIEW']);
        $this->assertDatabaseMissing('Quyen', ['MaQuyen' => 'ORDERS_VIEW']);
        $this->assertDatabaseHas('Quyen', ['MaQuyen' => 'ACCOUNTING_VIEW']);

        $accountant = VaiTro::query()->where('TenVaiTro', 'Kế toán')->firstOrFail();
        $user = $this->createAccount($accountant);

        $this->assertTrue($user->canPermission('reports.view'));
        $this->assertTrue($user->canPermission('invoices.view'));
        $this->assertTrue($user->canPermission('payments.view'));
        $this->assertTrue($user->canPermission('accounting.view'));
    }

    private function createRole(string $name): VaiTro
    {
        return VaiTro::query()->create([
            'TenVaiTro' => $name,
            'TrangThai' => 'Hoạt động',
        ]);
    }

    private function createAccount(VaiTro $role): User
    {
        $accountNumber = User::query()->count() + 1;
        $user = User::query()->create([
            'TenDangNhap' => 'account'.$accountNumber.'@example.test',
            'Email' => 'account'.$accountNumber.'@example.test',
            'TrangThai' => 'Hoạt động',
        ]);
        $user->vaiTros()->attach($role->getKey());

        return $user;
    }
}
