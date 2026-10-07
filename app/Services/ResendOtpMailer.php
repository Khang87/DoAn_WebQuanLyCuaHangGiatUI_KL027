<?php

namespace App\Services;

use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;
use RuntimeException;

class ResendOtpMailer
{
    public function send(string $email, string $otp, int $minutes = 15): void
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

        $client = new Client(new HttpTransporter(
            new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5]),
            BaseUri::from(getenv('RESEND_BASE_URL') ?: 'api.resend.com'),
            Headers::withAuthorization(ApiKey::from($apiKey)),
        ));
        $client->emails->send([
            'from' => $from,
            'to' => $email,
            'subject' => 'Mã khôi phục mật khẩu',
            'html' => '<p>Mã OTP của bạn là: <strong>'.e($otp).'</strong></p>'
                .'<p>Mã có hiệu lực trong '.$minutes.' phút. Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.</p>'
                .($minutes === 10 ? '<p><a href="'.e(route('internal-password.reset')).'">Đặt lại mật khẩu nội bộ</a></p>' : ''),
        ]);
    }
}
