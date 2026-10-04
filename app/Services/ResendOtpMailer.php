<?php

namespace App\Services;

use RuntimeException;

class ResendOtpMailer
{
    public function send(string $email, string $otp): void
    {
        $sandboxRecipient = config('services.resend.sandbox_to');
        if (
            is_string($sandboxRecipient)
            && trim($sandboxRecipient) !== ''
            && mb_strtolower(trim($sandboxRecipient)) !== mb_strtolower(trim($email))
        ) {
            throw new RuntimeException('The recipient is not the configured Resend sandbox address.');
        }

        $apiKey = config('services.resend.key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Resend API key is not configured.');
        }

        $from = config('services.resend.from');
        if (! is_string($from) || trim($from) === '') {
            throw new RuntimeException('Resend sender address is not configured.');
        }

        \Resend::client($apiKey)->emails->send([
            'from' => $from,
            'to' => $email,
            'subject' => 'Mã khôi phục mật khẩu',
            'html' => '<p>Mã OTP của bạn là: <strong>'.e($otp).'</strong></p>'
                .'<p>Mã có hiệu lực trong 15 phút. Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.</p>',
        ]);
    }
}
