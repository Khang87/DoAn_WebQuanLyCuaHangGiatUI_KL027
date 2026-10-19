<?php

namespace Tests\Unit;

use App\Mail\PasswordResetOtpMail;
use App\Services\OtpMailer;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class OtpMailerTest extends TestCase
{
    public function test_sends_an_otp_to_any_requested_address_using_gmail_mailer(): void
    {
        config([
            'mail.mailers.gmail.username' => 'sender@gmail.com',
            'mail.mailers.gmail.password' => 'gmail_app_password',
        ]);
        Mail::fake();

        app(OtpMailer::class)->send('recipient@example.test', '123456');

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail): bool {
            return $mail->hasTo('recipient@example.test')
                && $mail->from[0]['address'] === 'sender@gmail.com'
                && $mail->otp === '123456'
                && $mail->minutes === 15
                && $mail->usesMailer('gmail');
        });
        $this->assertSame('smtp.gmail.com', config('mail.mailers.gmail.host'));
        $this->assertSame(587, config('mail.mailers.gmail.port'));
        $this->assertSame('gmail_app_password', config('mail.mailers.gmail.password'));
    }

    public function test_password_reset_email_contains_the_otp_and_expiry(): void
    {
        $html = (new PasswordResetOtpMail('123456'))->render();

        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('15 phút', $html);
        $this->assertStringNotContainsString(route('internal-password.reset'), $html);
    }

    public function test_internal_reset_email_contains_the_internal_reset_link(): void
    {
        $mail = new PasswordResetOtpMail('654321', 10);
        $html = $mail->render();

        $this->assertStringContainsString('654321', $html);
        $this->assertStringContainsString(route('internal-password.reset'), $html);
    }

    public function test_missing_gmail_sender_fails_explicitly(): void
    {
        config(['mail.mailers.gmail.username' => null]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gmail SMTP sender address is not configured.');

        app(OtpMailer::class)->send('recipient@example.test', '123456');
    }
}
