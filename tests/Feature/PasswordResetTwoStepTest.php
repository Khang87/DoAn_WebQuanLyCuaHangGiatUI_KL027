<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ResendOtpMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTwoStepTest extends TestCase
{
    private User $user;

    private string $otp = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.auth_store' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('TaiKhoan', function (Blueprint $table) {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('Email');
            $table->string('MatKhau');
            $table->string('TrangThai');
        });
        $this->user = User::create(['TenDangNhap' => 'reset@example.test', 'Email' => 'reset@example.test', 'MatKhau' => Hash::make('OriginalPass123!'), 'TrangThai' => 'Hoạt động']);
        $mailer = \Mockery::mock(ResendOtpMailer::class);
        $mailer->shouldReceive('send')->andReturnUsing(function (string $email, string $otp) {
            $this->otp = $otp;
        });
        $this->app->instance(ResendOtpMailer::class, $mailer);
    }

    private function sendOtp(): void
    {
        $this->post('/forgot-password', ['email' => $this->user->Email])->assertRedirect('/forgot-password');
    }

    private function verifyOtp(): void
    {
        $this->post('/forgot-password/verify', ['email' => strtoupper($this->user->Email), 'otp' => $this->otp])->assertRedirect('/reset-password');
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_two_screens_require_verification_and_password_update_is_single_use(): void
    {
        $this->get('/reset-password')->assertRedirect('/forgot-password');
        $this->sendOtp();
        $response = $this->get('/forgot-password')->assertOk()->assertSee('15 phút');
        $this->assertMatchesRegularExpression('/data-cooldown-seconds="(?:59|60)"/', $response->getContent());
        $response->assertSee('name="otp"', false)->assertDontSee('name="password"', false)->assertDontSee($this->otp);
        $hash = $this->user->MatKhau;
        $this->verifyOtp();
        $this->assertSame($hash, $this->user->fresh()->MatKhau);
        $this->get('/reset-password')->assertOk()->assertSee('name="password"', false)->assertDontSee('name="otp"', false)->assertDontSee('name="email"', false);
        $this->post('/reset-password', ['password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!', 'email' => 'victim@example.test'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword123!', $this->user->fresh()->MatKhau));
        $this->post('/reset-password', ['password' => 'AnotherPass123!', 'password_confirmation' => 'AnotherPass123!'])->assertRedirect('/forgot-password');
    }

    public function test_direct_post_with_valid_otp_cannot_skip_verification(): void
    {
        $this->sendOtp();
        $this->post('/reset-password', ['email' => $this->user->Email, 'otp' => $this->otp, 'password' => 'BypassPassword123!', 'password_confirmation' => 'BypassPassword123!'])->assertRedirect('/forgot-password');
        $this->assertTrue(Hash::check('OriginalPass123!', $this->user->fresh()->MatKhau));
    }

    public function test_cooldown_persists_on_reload_and_resend_invalidates_previous_otp(): void
    {
        $this->sendOtp();
        $old = $this->otp;
        $this->travel(30)->seconds();
        $this->assertMatchesRegularExpression(
            '/data-cooldown-seconds="(?:29|30)"/',
            $this->get('/forgot-password')->getContent(),
        );
        $this->post('/forgot-password', ['email' => strtoupper($this->user->Email)])->assertSessionHas('error');
        $this->assertSame($old, $this->otp);
        $this->travel(30)->seconds();
        $this->sendOtp();
        $this->post('/forgot-password/verify', ['email' => $this->user->Email, 'otp' => $old])->assertRedirect('/forgot-password')->assertSessionHas('error');
        $this->verifyOtp();
    }

    public function test_otp_expires_at_exactly_fifteen_minutes(): void
    {
        $this->sendOtp();
        $this->travel(15)->minutes();
        $this->post('/forgot-password/verify', ['email' => $this->user->Email, 'otp' => $this->otp])->assertRedirect('/forgot-password')->assertSessionHas('error');
        $this->get('/reset-password')->assertRedirect('/forgot-password');
    }

    public function test_otp_is_usable_just_before_expiry_but_cannot_be_verified_twice(): void
    {
        $this->sendOtp();
        $this->travel(899)->seconds();
        $this->verifyOtp();
        $this->post('/forgot-password/verify', ['email' => $this->user->Email, 'otp' => $this->otp])->assertRedirect('/forgot-password')->assertSessionHas('error');
    }

    public function test_five_wrong_codes_revoke_otp(): void
    {
        $this->sendOtp();
        $wrong = $this->otp === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password/verify', ['email' => $this->user->Email, 'otp' => $wrong])->assertRedirect('/forgot-password');
        }
        $this->post('/forgot-password/verify', ['email' => $this->user->Email, 'otp' => $this->otp])->assertRedirect('/forgot-password');
        $this->get('/reset-password')->assertRedirect('/forgot-password');
    }

    public function test_verified_grant_expires_and_resend_revokes_it(): void
    {
        $this->sendOtp();
        $this->verifyOtp();
        $this->travel(60)->seconds();
        $this->sendOtp();
        $this->get('/reset-password')->assertRedirect('/forgot-password');
        $this->verifyOtp();
        $this->travel(15)->minutes();
        $this->get('/reset-password')->assertRedirect('/forgot-password');
    }

    public function test_password_confirmation_failure_keeps_valid_grant_for_retry(): void
    {
        $this->sendOtp();
        $this->verifyOtp();
        $this->from('/reset-password')->post('/reset-password', ['password' => 'NewPassword123!', 'password_confirmation' => 'Mismatch'])->assertSessionHasErrors('password');
        $this->get('/reset-password')->assertOk();
        $this->post('/reset-password', ['password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertRedirect('/login');
    }

    public function test_copied_grant_cannot_be_used_in_another_session(): void
    {
        $this->sendOtp();
        $this->verifyOtp();
        $grant = session('password_reset_grant');
        session()->regenerate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->withSession(['password_reset_grant' => $grant]);
        $this->get('/reset-password')->assertRedirect('/forgot-password');
        $this->withSession(['password_reset_grant' => $grant])->post('/reset-password', ['password' => 'ReplayPass123!', 'password_confirmation' => 'ReplayPass123!'])->assertRedirect('/forgot-password');
        $this->assertTrue(Hash::check('OriginalPass123!', $this->user->fresh()->MatKhau));
    }

    public function test_grant_is_revoked_after_other_password_change(): void
    {
        $this->sendOtp();
        $this->verifyOtp();
        $this->user->forceFill(['MatKhau' => Hash::make('ChangedElsewhere123')])->save();
        $this->post('/reset-password', ['password' => 'ReplayPass123!', 'password_confirmation' => 'ReplayPass123!'])->assertRedirect('/forgot-password');
        $this->assertTrue(Hash::check('ChangedElsewhere123', $this->user->fresh()->MatKhau));
    }

    public function test_ip_send_limit_prevents_spam_across_different_emails(): void
    {
        $this->post('/forgot-password', ['email' => 'missing@example.test'])->assertRedirect('/forgot-password');
        $this->post('/forgot-password', ['email' => 'another@example.test'])->assertSessionHas('error');
        $this->travel(60)->seconds();
        $this->post('/forgot-password', ['email' => 'third@example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('otp_privacy_notice')
            ->assertSessionMissing('error');
    }

    public function test_unknown_email_does_not_send_otp_or_disclose_account_existence(): void
    {
        $this->post('/forgot-password', ['email' => 'missing@example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('otp_privacy_notice');

        $this->assertSame('', $this->otp);
    }

    public function test_locked_account_is_not_sent_recovery_email_and_receives_clear_error(): void
    {
        $this->user->forceFill(['TrangThai' => 'Khóa'])->save();

        $this->post('/forgot-password', ['email' => $this->user->Email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('error', 'Tài khoản đang bị khóa hoặc ngừng hoạt động nên không thể khôi phục mật khẩu.');

        $this->assertSame('', $this->otp);
    }

    public function test_account_pending_deletion_is_not_sent_recovery_email(): void
    {
        $this->user->forceFill(['TrangThai' => 'Chờ xử lý xóa'])->save();

        $this->post('/forgot-password', ['email' => $this->user->Email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('error', 'Tài khoản đang chờ xử lý xóa nên không thể khôi phục mật khẩu.');

        $this->assertSame('', $this->otp);
    }

    public function test_email_rate_limit_is_per_normalized_email_for_sixty_seconds(): void
    {
        $this->sendOtp();
        $firstOtp = $this->otp;
        $this->travel(60)->seconds();
        $this->post('/forgot-password', ['email' => strtoupper($this->user->Email)])
            ->assertRedirect('/forgot-password');

        $this->assertNotSame('', $this->otp);
        $this->assertNotSame($firstOtp, $this->otp);
    }

    public function test_email_rate_limit_is_shared_across_different_ip_addresses(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->sendOtp();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->post('/forgot-password', ['email' => strtoupper($this->user->Email)])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('error');
    }

    public function test_new_password_must_satisfy_system_strength_and_not_match_current_password(): void
    {
        $this->sendOtp();
        $this->verifyOtp();

        $this->post('/reset-password', [
            'password' => 'newpassword123!',
            'password_confirmation' => 'newpassword123!',
        ])->assertSessionHasErrors('password');
        $this->get('/reset-password')->assertOk();

        $this->post('/reset-password', [
            'password' => 'NewPassword!',
            'password_confirmation' => 'NewPassword!',
        ])->assertSessionHasErrors('password');
        $this->get('/reset-password')->assertOk();

        $this->post('/reset-password', [
            'password' => 'Aa1!aaa',
            'password_confirmation' => 'Aa1!aaa',
        ])->assertSessionHasErrors('password');
        $this->get('/reset-password')->assertOk();

        $this->post('/reset-password', [
            'password' => 'OriginalPass123!',
            'password_confirmation' => 'OriginalPass123!',
        ])->assertSessionHasErrors('password');
        $this->get('/reset-password')->assertOk();

        $this->post('/reset-password', [
            'password' => 'DifferentPass123!',
            'password_confirmation' => 'DifferentPass123!',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('DifferentPass123!', $this->user->fresh()->MatKhau));
    }

    public function test_account_disabled_after_otp_verification_cannot_reset_password(): void
    {
        $this->sendOtp();
        $this->verifyOtp();
        $this->user->forceFill(['TrangThai' => 'Khóa'])->save();

        $this->post('/reset-password', [
            'password' => 'DifferentPass123!',
            'password_confirmation' => 'DifferentPass123!',
        ])->assertRedirect('/forgot-password');

        $this->assertTrue(Hash::check('OriginalPass123!', $this->user->fresh()->MatKhau));
    }

    public function test_email_change_revokes_verified_grant(): void
    {
        $this->sendOtp();
        $this->verifyOtp();
        $this->user->forceFill(['Email' => 'changed@example.test'])->save();
        $this->get('/reset-password')->assertRedirect('/forgot-password');
        $this->assertTrue(Hash::check('OriginalPass123!', $this->user->fresh()->MatKhau));
    }
}
