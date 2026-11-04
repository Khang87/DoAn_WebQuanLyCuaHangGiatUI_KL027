<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetOtpMail extends Mailable
{
    public function __construct(
        public readonly string $otp,
        public readonly int $minutes,
        public readonly bool $isInternal = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã khôi phục mật khẩu');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-otp', with: [
            'otp' => $this->otp,
            'minutes' => $this->minutes,
            'isInternal' => $this->isInternal,
        ]);
    }
}
