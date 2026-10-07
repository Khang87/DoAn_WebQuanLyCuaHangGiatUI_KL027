<?php

namespace Tests\Feature;

use App\Models\ThongBao;
use App\Models\User;
use App\Models\VaiTro;
use App\Services\DefaultNotificationPermission;
use App\Services\InternalPasswordOtpService;
use App\Services\NotificationService;
use App\Services\ResendOtpMailer;
use App\Services\RoleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InternalOtpTest extends TestCase
{
    private User $owner;

    private User $staff;

    private ?string $otp = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.internal_otp_store' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('TaiKhoan', function (Blueprint $t) {
            $t->increments('TaiKhoanID');
            $t->string('TenDangNhap');
            $t->string('Email')->nullable();
            $t->string('MatKhau');
            $t->integer('NhanVienID')->nullable();
            $t->integer('KhachHangID')->nullable();
            $t->string('TrangThai');
            $t->dateTime('NgayTao')->nullable();
        });
        Schema::create('VaiTro', function (Blueprint $t) {
            $t->increments('VaiTroID');
            $t->string('TenVaiTro')->unique();
            $t->string('MoTa')->nullable();
            $t->string('TrangThai');
        });
        Schema::create('Quyen', function (Blueprint $t) {
            $t->increments('QuyenID');
            $t->string('MaQuyen')->unique();
            $t->string('TenQuyen');
            $t->string('MoTa')->nullable();
            $t->string('TrangThai');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $t) {
            $t->integer('VaiTroID');
            $t->integer('QuyenID');
            $t->primary(['VaiTroID', 'QuyenID']);
        });
        Schema::create('TaiKhoan_VaiTro', function (Blueprint $t) {
            $t->integer('TaiKhoanID');
            $t->integer('VaiTroID');
            $t->primary(['TaiKhoanID', 'VaiTroID']);
        });
        Schema::create('ThongBao', function (Blueprint $t) {
            $t->increments('ThongBaoID');
            $t->integer('TaiKhoanID');
            $t->integer('DonHangID')->nullable();
            $t->string('LoaiThongBao', 50)->nullable();
            $t->string('TieuDe');
            $t->text('NoiDung');
            $t->dateTime('ThoiGianGui');
            $t->boolean('DaDoc')->default(false);
        });
        Schema::create('DonHang', fn (Blueprint $t) => $t->increments('DonHangID'));
        Schema::create('NhanVien', function (Blueprint $t) {
            $t->increments('NhanVienID');
            $t->string('HoTen');
        });
        Schema::create('KhachHang', function (Blueprint $t) {
            $t->increments('KhachHangID');
            $t->string('HoTen');
        });
        $ownerRole = VaiTro::create(['TenVaiTro' => VaiTro::OWNER, 'TrangThai' => 'Hoạt động']);
        $staffRole = VaiTro::create(['TenVaiTro' => 'Nhân viên', 'TrangThai' => 'Hoạt động']);
        $this->owner = User::create(['TenDangNhap' => 'admin', 'Email' => 'admin@example.test', 'MatKhau' => Hash::make('OldPassword123'), 'NhanVienID' => 1, 'TrangThai' => 'Hoạt động']);
        $this->staff = User::create(['TenDangNhap' => 'staff', 'Email' => 'staff@example.test', 'MatKhau' => Hash::make('OldPassword123'), 'NhanVienID' => 2, 'TrangThai' => 'Hoạt động']);
        $this->owner->vaiTros()->attach($ownerRole);
        $this->staff->vaiTros()->attach($staffRole);
        app(DefaultNotificationPermission::class)->backfill();
        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldReceive('send')->with($this->staff->Email, \Mockery::type('string'), 10)
            ->andReturnUsing(function ($email, $otp) {
                $this->otp = $otp;
            });
        $this->app->instance(ResendOtpMailer::class, $mailer);
    }

    private function issue(): void
    {
        $this->actingAs($this->owner)->post(route('accounts.reset-password', $this->staff))
            ->assertRedirect(route('accounts.show', $this->staff))->assertSessionHas('success');
    }

    private function reset(?string $otp = null)
    {
        return $this->post(route('internal-password.update'), ['email' => strtoupper($this->staff->Email),
            'otp' => $otp ?? $this->otp, 'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123']);
    }

    public function test_admin_sends_same_otp_by_mail_and_private_database_notification(): void
    {
        $old = $this->staff->MatKhau;
        $this->issue();
        $record = Cache::store('array')->get(app(InternalPasswordOtpService::class)->key($this->staff->Email));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->otp);
        $this->assertTrue(Hash::check($this->otp, $record['hash']));
        $this->assertNotSame($this->otp, $record['hash']);
        $this->assertSame(now()->addMinutes(10)->timestamp, $record['expires_at']);
        $this->assertSame($old, $this->staff->fresh()->MatKhau);
        $this->assertDatabaseHas('ThongBao', ['TaiKhoanID' => $this->staff->getKey(), 'LoaiThongBao' => 'internal_password_otp']);
        $this->assertStringContainsString($this->otp, ThongBao::first()->NoiDung);
        $this->assertStringNotContainsString($this->otp, session('success'));
        $this->assertDatabaseCount('ThongBao', 1);
    }

    public function test_success_consumes_otp_and_removes_notification_and_rejects_replay(): void
    {
        $this->issue();
        auth()->logout();
        $this->reset()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPassword123', $this->staff->fresh()->MatKhau));
        $this->assertDatabaseCount('ThongBao', 0);
        $this->reset()->assertSessionHas('error');
    }

    public function test_expiration_and_notification_visibility_are_ten_minutes(): void
    {
        $this->issue();
        $this->travel(10)->minutes();
        $this->assertSame(0, ThongBao::count());
        $this->reset()->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldPassword123', $this->staff->fresh()->MatKhau));
    }

    public function test_five_wrong_attempts_invalidate_code_without_extending_expiry(): void
    {
        $this->issue();
        $wrong = $this->otp === '100000' ? '100001' : '100000';
        for ($i = 0; $i < 5; $i++) {
            $this->reset($wrong)->assertSessionHas('error');
        }
        $this->reset()->assertSessionHas('error');
        $this->assertDatabaseCount('ThongBao', 0);
        $this->assertTrue(Hash::check('OldPassword123', $this->staff->fresh()->MatKhau));
    }

    public function test_resend_is_throttled_and_rotates_previous_code_after_cooldown(): void
    {
        $this->issue();
        $old = $this->otp;
        $this->post(route('accounts.reset-password', $this->staff))->assertSessionHas('error');
        $this->travel(61)->seconds();
        $this->issue();
        $this->assertDatabaseCount('ThongBao', 1);
        $record = Cache::store('array')->get(app(InternalPasswordOtpService::class)->key($this->staff->Email));
        $this->assertTrue(Hash::check($this->otp, $record['hash']));
        if ($old !== $this->otp) {
            $this->reset($old)->assertSessionHas('error');
        }
    }

    public function test_delivery_failure_rolls_back_notification_and_invalidates_code(): void
    {
        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new \RuntimeException('provider error'));
        $this->app->instance(ResendOtpMailer::class, $mailer);
        $this->actingAs($this->owner)->post(route('accounts.reset-password', $this->staff))->assertSessionHas('error');
        $this->assertDatabaseCount('ThongBao', 0);
        $this->assertNull(Cache::store('array')->get(app(InternalPasswordOtpService::class)->key($this->staff->Email)));
    }

    public function test_only_authorized_admin_can_trigger_and_customer_cannot_be_targeted(): void
    {
        $this->actingAs($this->staff)->post(route('accounts.reset-password', $this->staff))->assertForbidden();
        $this->staff->update(['NhanVienID' => null, 'KhachHangID' => 1]);
        $this->actingAs($this->owner)->post(route('accounts.reset-password', $this->staff))->assertSessionHas('error');
        $this->assertDatabaseCount('ThongBao', 0);
    }

    public function test_changed_email_or_locked_account_cannot_use_issued_otp(): void
    {
        $this->issue();
        $this->staff->update(['TrangThai' => 'Khóa']);
        $this->reset()->assertSessionHas('error');
        $this->staff->update(['TrangThai' => 'Hoạt động', 'Email' => 'changed@example.test']);
        $this->post(route('internal-password.update'), ['email' => 'staff@example.test', 'otp' => $this->otp,
            'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123'])->assertSessionHas('error');
    }

    public function test_admin_cannot_view_mark_read_or_edit_another_accounts_otp(): void
    {
        $this->issue();
        $notification = ThongBao::first();
        $this->get(route('notifications.show', $notification))->assertNotFound();
        $this->patch(route('notifications.mark-read', $notification))->assertNotFound();
        $this->get(route('notifications.edit', $notification))->assertNotFound();
        $this->delete(route('notifications.destroy', $notification))->assertNotFound();
        $this->assertFalse($notification->fresh()->DaDoc);
    }

    public function test_inbox_ignores_requested_recipient_and_marks_only_own_notifications(): void
    {
        $this->issue();
        $this->get(route('notifications.index', ['user_id' => $this->staff->getKey(), 'id' => ThongBao::first()->getKey()]))
            ->assertOk()->assertDontSee($this->otp);
        $this->post(route('notifications.mark-all-read'))->assertRedirect();
        $this->assertFalse(ThongBao::first()->DaDoc);
        $this->actingAs($this->staff)->get(route('notifications.show', ThongBao::first()))->assertOk()->assertSee($this->otp);
        $this->assertTrue(ThongBao::first()->DaDoc);
    }

    public function test_default_permission_is_idempotent_and_survives_all_permission_editors(): void
    {
        $role = app(RoleService::class)->createRole(['TenVaiTro' => 'Nhóm mới']);
        $permission = app(DefaultNotificationPermission::class)->permission();
        $this->artisan('notifications:grant-default')->assertExitCode(0);
        app(DefaultNotificationPermission::class)->backfill();
        app(DefaultNotificationPermission::class)->backfill();
        $this->assertSame(3, DB::table('VaiTro_Quyen')->where('QuyenID', $permission->getKey())->count());
        app(RoleService::class)->syncRolePermissions($role, []);
        app(RoleService::class)->syncPermissions([$role->getKey() => []]);
        app(RoleService::class)->syncPermissionRoles($permission, []);
        $this->assertTrue($role->quyens()->where('MaQuyen', DefaultNotificationPermission::CODE)->exists());
        $this->assertSame(1, $role->quyens()->count());
    }

    public function test_customer_group_can_access_personal_inbox(): void
    {
        $role = app(RoleService::class)->createRole(['TenVaiTro' => 'Khách hàng']);
        $this->staff->vaiTros()->sync([$role->getKey()]);
        $this->staff->update(['NhanVienID' => null, 'KhachHangID' => 1]);
        $this->actingAs($this->staff)->get(route('notifications.index'))->assertOk();
        $this->get(route('notifications.create'))->assertForbidden();
    }

    public function test_staff_broadcast_includes_internal_accounts_with_custom_roles(): void
    {
        $role = app(RoleService::class)->createRole(['TenVaiTro' => 'Kế toán mới']);
        $this->staff->vaiTros()->sync([$role->getKey()]);
        $count = app(NotificationService::class)->createForRecipient('all_staff', [
            'TieuDe' => 'Thông báo nội bộ', 'NoiDung' => 'Nội dung',
        ]);
        $this->assertSame(2, $count);
        $this->assertDatabaseHas('ThongBao', ['TaiKhoanID' => $this->staff->getKey(), 'TieuDe' => 'Thông báo nội bộ']);
    }

    public function test_shared_email_and_inactive_targets_are_rejected_before_delivery(): void
    {
        $this->staff->update(['TrangThai' => 'Khóa']);
        $this->actingAs($this->owner)->post(route('accounts.reset-password', $this->staff))->assertSessionHas('error');
        $this->staff->update(['TrangThai' => 'Hoạt động']);
        $this->owner->update(['Email' => strtoupper($this->staff->Email)]);
        $this->post(route('accounts.reset-password', $this->staff))->assertSessionHas('error');
        $this->assertDatabaseCount('ThongBao', 0);
    }

    public function test_reset_logs_out_the_recipient_when_already_signed_in(): void
    {
        $this->issue();
        $this->actingAs($this->staff);
        $this->reset()->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_roles_with_only_default_permission_can_log_in_to_inbox(): void
    {
        $role = app(RoleService::class)->createRole(['TenVaiTro' => 'Nhóm chỉ nhận thông báo']);
        $this->staff->vaiTros()->sync([$role->getKey()]);
        $this->post(route('login'), ['email' => $this->staff->Email, 'password' => 'OldPassword123'])
            ->assertRedirect(route('notifications.index'));
        $this->get(route('dashboard'))->assertRedirect(route('notifications.index'));
        $this->get(route('notifications.index'))->assertOk()
            ->assertSee('<span>Thông báo</span>', false)
            ->assertDontSee('<span>Khuyến mãi</span>', false)
            ->assertDontSee('<span>Báo cáo & Thống kê</span>', false)
            ->assertDontSee('<span>Tài khoản</span>', false);
    }

    public function test_customers_with_default_permission_can_log_in_to_inbox_only(): void
    {
        $role = app(RoleService::class)->createRole(['TenVaiTro' => 'Khách hàng']);
        $this->staff->vaiTros()->sync([$role->getKey()]);
        $this->staff->update(['NhanVienID' => null, 'KhachHangID' => 1]);
        $this->post(route('login'), ['email' => $this->staff->Email, 'password' => 'OldPassword123'])
            ->assertRedirect(route('notifications.index'));
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('orders.index'))->assertForbidden();
    }
}
