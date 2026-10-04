<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaiTro;
use App\Services\RememberedLogin;
use App\Services\ResendOtpMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Resend\Exceptions\ErrorException;
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
            'cache.auth_store' => 'file',
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
        Cache::store('file')->forget('pwd_reset_'.$this->staff->Email);
        Cache::store('file')->forget(
            'auth:password-reset-throttle:'.hash('sha256', 'missing@example.test'),
        );
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

    public function test_remember_cookie_restores_user_before_showing_login_form(): void
    {
        $token = app(RememberedLogin::class)->issue($this->staff);

        $this->withCookie(RememberedLogin::COOKIE_NAME, $token)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->staff);
    }

    public function test_authenticated_user_is_redirected_away_from_login_form(): void
    {
        $this->actingAs($this->staff)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_remember_token_cannot_restore_a_disabled_account(): void
    {
        $rememberedLogin = app(RememberedLogin::class);
        $token = $rememberedLogin->issue($this->staff);
        $this->staff->update(['TrangThai' => 'Khóa']);

        $this->assertNull($rememberedLogin->resolve($token));
    }

    public function test_logout_revokes_the_remember_cookie_token(): void
    {
        $rememberedLogin = app(RememberedLogin::class);
        $token = $rememberedLogin->issue($this->staff);

        $response = $this->withCookie(RememberedLogin::COOKIE_NAME, $token)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $response->assertCookieExpired(RememberedLogin::COOKIE_NAME);
        $this->assertGuest();
        $this->assertNull($rememberedLogin->resolve($token));
    }

    public function test_logout_revokes_remember_token_even_when_cookie_is_missing(): void
    {
        $rememberedLogin = app(RememberedLogin::class);
        $token = $rememberedLogin->issue($this->staff);

        $this->actingAs($this->staff)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

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

    public function test_password_reset_otp_updates_password_and_cannot_be_reused(): void
    {
        $rememberToken = app(RememberedLogin::class)->issue($this->staff);
        $otp = null;
        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->with($this->staff->Email, \Mockery::on(function (string $sentOtp) use (&$otp): bool {
                $otp = $sentOtp;

                return preg_match('/^\d{6}$/', $sentOtp) === 1;
            }));
        $this->app->instance(ResendOtpMailer::class, $mailer);

        $this->post(route('password.email'), [
            'email' => $this->staff->Email,
        ])->assertRedirect(route('password.reset'))
            ->assertSessionHas('status')
            ->assertSessionMissing('simulation_otp');

        $this->assertNotNull($otp);
        $this->assertSame(
            hash('sha256', $otp),
            Cache::store('file')->get('pwd_reset_'.$this->staff->Email),
        );
        $this->get(route('password.reset'))
            ->assertOk()
            ->assertDontSee($otp);

        $this->post(route('password.update'), [
            'email' => $this->staff->Email,
            'otp' => $otp,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPassword123', $this->staff->fresh()->MatKhau));
        $this->assertNull(app(RememberedLogin::class)->resolve($rememberToken));
        $this->assertNull(Cache::store('file')->get('pwd_reset_'.$this->staff->Email));

        $this->post(route('password.update'), [
            'email' => $this->staff->Email,
            'otp' => $otp,
            'password' => 'AnotherPass123',
            'password_confirmation' => 'AnotherPass123',
        ])->assertRedirect(route('password.reset'))
            ->assertSessionHas('error');
    }

    public function test_password_reset_request_does_not_disclose_unknown_emails(): void
    {
        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldNotReceive('send');
        $this->app->instance(ResendOtpMailer::class, $mailer);

        $response = $this->post(route('password.email'), [
            'email' => 'missing@example.test',
        ]);

        $response->assertRedirect(route('password.reset'))
            ->assertSessionHas('status')
            ->assertSessionMissing('simulation_otp');
    }

    public function test_password_reset_email_failure_does_not_leave_a_valid_otp(): void
    {
        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Resend API unavailable.'));
        $this->app->instance(ResendOtpMailer::class, $mailer);

        $this->post(route('password.email'), [
            'email' => $this->staff->Email,
        ])->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'Không thể gửi email mã OTP lúc này. Vui lòng thử lại sau.');

        $this->assertNull(Cache::store('file')->get('pwd_reset_'.$this->staff->Email));
        $this->assertFalse(Cache::store('file')->has(
            'auth:password-reset-throttle:'.hash('sha256', $this->staff->Email),
        ));
    }

    public function test_resend_failure_log_redacts_emails_otp_and_api_credentials(): void
    {
        config(['services.resend.key' => 'resend_test_secret']);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $providerMessage = $context['provider_message'];

                $this->assertStringNotContainsString($this->staff->Email, $providerMessage);
                $this->assertStringNotContainsString('another@example.test', $providerMessage);
                $this->assertStringNotContainsString('123456', $providerMessage);
                $this->assertStringNotContainsString('resend_test_secret', $providerMessage);
                $this->assertStringNotContainsString('re_abcdefghijklmnopqrst', $providerMessage);
                $this->assertStringNotContainsString('Bearer test-bearer-secret', $providerMessage);
                $this->assertSame('validation_error', $context['provider_error_type']);
                $this->assertSame(403, $context['provider_error_code']);

                return $message === 'Unable to send password reset OTP through Resend.';
            });

        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (string $email, string $otp): void {
                throw new ErrorException([
                    'message' => "Failed to send {$otp} to {$email} and another@example.test; key resend_test_secret re_abcdefghijklmnopqrst; Bearer test-bearer-secret",
                    'name' => 'validation_error',
                    'statusCode' => 403,
                ]);
            });
        $this->app->instance(ResendOtpMailer::class, $mailer);

        $this->post(route('password.email'), [
            'email' => $this->staff->Email,
        ])->assertRedirect(route('password.request'))
            ->assertSessionHas('error');

        $this->assertNull(Cache::store('file')->get('pwd_reset_'.$this->staff->Email));
        $this->assertFalse(Cache::store('file')->has(
            'auth:password-reset-throttle:'.hash('sha256', $this->staff->Email),
        ));
    }

    public function test_resend_sandbox_rejects_an_email_that_is_not_the_configured_recipient(): void
    {
        config([
            'services.resend.sandbox_to' => 'resend-owner@example.test',
        ]);

        try {
            app(ResendOtpMailer::class)->send('another-account@example.test', '123456');
            $this->fail('The Resend sandbox must not send OTPs to an unverified recipient.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'The recipient is not the configured Resend sandbox address.',
                $exception->getMessage(),
            );
        }
    }

    public function test_expired_password_reset_otp_cannot_be_used(): void
    {
        Cache::store('file')->put(
            'pwd_reset_'.$this->staff->Email,
            hash('sha256', '123456'),
            now()->subMinute(),
        );

        $this->post(route('password.update'), [
            'email' => $this->staff->Email,
            'otp' => '123456',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('password.reset'))
            ->assertSessionHas('error', 'Email hoặc mã OTP không đúng hoặc mã đã hết hạn.');
    }
}
