<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use App\Models\VaiTro;
use App\Services\RememberedLogin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
        ]);
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
        });
        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro');
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
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai');
        });

        $role = VaiTro::query()->create([
            'TenVaiTro' => 'Nhân viên',
            'TrangThai' => 'Hoạt động',
        ]);
        foreach (['DASHBOARD_VIEW', 'ORDER_VIEW'] as $permissionCode) {
            $permissionId = DB::table('Quyen')->insertGetId([
                'MaQuyen' => $permissionCode,
                'TrangThai' => 'Hoạt động',
            ], 'QuyenID');
            DB::table('VaiTro_Quyen')->insert([
                'VaiTroID' => $role->VaiTroID,
                'QuyenID' => $permissionId,
            ]);
        }

        $this->staff = User::query()->create([
            'TenDangNhap' => 'nhanvien@example.test',
            'Email' => 'nhanvien@example.test',
            'MatKhau' => Hash::make('InitialPass123'),
            'TrangThai' => 'Hoạt động',
        ]);
        $this->staff->vaiTros()->attach($role->VaiTroID);
        Cache::store('file')->forget(
            'auth:password-reset-throttle:'.hash('sha256', $this->staff->Email),
        );
        Cache::store('file')->forget('auth:password-reset:'.$this->staff->getKey());
        Cache::store('file')->forget('auth:remember-user:'.$this->staff->getKey());
    }

    public function test_login_sets_persistent_remember_cookie_without_a_database_remember_column(): void
    {
        $response = $this->post('/login', [
            'email' => $this->staff->Email,
            'password' => 'InitialPass123',
            'remember' => '1',
        ]);

        $this->assertAuthenticatedAs($this->staff);
        $response->assertRedirect(route('staff.dashboard'));
        $response->assertSessionHas('success', 'Đăng nhập thành công.');
        $response->assertCookie(RememberedLogin::COOKIE_NAME);
        $this->assertArrayNotHasKey('remember_token', $this->staff->getAttributes());
    }

    public function test_remember_cookie_restores_the_user_after_the_session_is_lost(): void
    {
        $rememberedLogin = app(RememberedLogin::class);
        $token = $rememberedLogin->issue($this->staff);
        $this->assertSame($this->staff->getKey(), $rememberedLogin->resolve($token)?->getKey());

        $response = $this->withCookie(RememberedLogin::COOKIE_NAME, $token)
            ->get('/dashboard');

        $response->assertRedirect(route('staff.dashboard'));
        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_logout_revokes_the_remember_cookie_token(): void
    {
        $rememberedLogin = app(RememberedLogin::class);
        $token = $rememberedLogin->issue($this->staff);

        $this->withCookie(RememberedLogin::COOKIE_NAME, $token)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($rememberedLogin->resolve($token));
    }

    public function test_login_failure_is_shown_as_a_popup_message(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => $this->staff->Email,
            'password' => 'incorrect-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('error', 'Email hoặc mật khẩu không đúng.');
        $this->get('/login')
            ->assertOk()
            ->assertSee('Đăng nhập thất bại')
            ->assertSee('Swal.fire');
    }

    public function test_password_reset_email_link_updates_password_and_cannot_be_reused(): void
    {
        Mail::fake();
        $rememberToken = app(RememberedLogin::class)->issue($this->staff);

        $this->post(route('password.email'), [
            'email' => $this->staff->Email,
        ])->assertRedirect();

        $resetUrl = null;
        Mail::assertSent(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use (&$resetUrl): bool {
            $resetUrl = $mail->resetUrl;

            return true;
        });

        $this->assertNotNull($resetUrl);
        $path = parse_url($resetUrl, PHP_URL_PATH);
        $token = basename((string) $path);
        parse_str((string) parse_url($resetUrl, PHP_URL_QUERY), $query);

        $this->get($resetUrl)
            ->assertOk()
            ->assertSee('Đặt lại mật khẩu');

        $this->post(route('password.update', ['token' => $token]), [
            'email' => $query['email'],
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassword123', $this->staff->fresh()->MatKhau));
        $this->assertNull(app(RememberedLogin::class)->resolve($rememberToken));
        $this->get($resetUrl)->assertRedirect(route('password.request'));
    }

    public function test_password_reset_request_does_not_disclose_unknown_emails(): void
    {
        Mail::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'missing@example.test',
        ]);

        $response->assertSessionHas('status');
        Mail::assertNothingSent();
    }
}
