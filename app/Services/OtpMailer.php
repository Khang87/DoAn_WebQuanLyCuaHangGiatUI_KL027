<?php

namespace App\Services;

use App\Mail\PasswordResetOtpMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OtpMailer
{
    public function send(string $email, string $otp, int $minutes = 15): void
    {
        $sender = config('mail.mailers.gmail.username');
        if (! is_string($sender) || trim($sender) === '') {
            throw new RuntimeException('Gmail SMTP sender address is not configured.');
        }

        Mail::mailer('gmail')
            ->to($email)
            ->send((new PasswordResetOtpMail($otp, $minutes))->from($sender)->mailer('gmail'));
    }
}
